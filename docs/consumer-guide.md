# Consumer guide — pulling CCCBR properties into the Orbitas Marketplace

For the team building the import. It covers how to reach the endpoint, how to walk the whole
collection without missing or duplicating a property, what each field means, and — just as important — **what
the source data does not contain**, so the import can be designed around real gaps instead of
discovering them at run time.

The exhaustive field-by-field contract is [`contract.md`](contract.md); this guide is the practical
path through it. **Every example below is synthetic** — invented ids, names and files. No real
record appears in this repository.

---

## 1. In one paragraph

One public endpoint returns every CCCBR property that is **not in the trash** —
published and draft alike — with its custom fields, location, coordinates, images, the data of the
associate who created it, and a per-property list of data-quality issues. It is paginated with a
cursor on the WordPress post id, so walking it from start to finish is safe even while people keep
editing properties. At the time of writing that is **2672 properties** (2612 published + 60 draft);
a full walk is 27 requests and takes about 20 seconds.

**Trashed properties are deliberately excluded.** CCCBR decided that a property someone deleted must
not reach the Marketplace. There were 383 of them in the source. You cannot ask for them:
`?status=trash` is a `400`.

---

## 2. Access

**The endpoint is public.** No credentials, no token, no header: a plain `GET` is enough.

```bash
curl -s "https://camara.cr/wp-json/cccbr-orbitas/v1/propiedades?per_page=100"
```

- There is no `401` and no `403`. Nothing on your side has to be configured, stored or rotated.
- Request it over **HTTPS**, so the payload is not readable in transit. It contains personal data:
  the name and email of the associate who created each property.
- Responses carry `Cache-Control: no-store`. Do not put the endpoint behind a shared cache or CDN,
  and do not log full response bodies.
- The endpoint is public only for the duration of the migration, and CCCBR will remove it once the
  import is done. Treat the URL as the only thing standing between this data and the open internet:
  keep it out of tickets, client-side code and public repositories, and tell CCCBR when you are
  finished so they can take it down.

---

## 3. The two requests

```
GET /wp-json/cccbr-orbitas/v1/propiedades          # the collection, paginated
GET /wp-json/cccbr-orbitas/v1/propiedades/{id}     # one property
```

Both are public. The collection's arguments:

| Argument | Default | Range | Meaning |
|---|---|---|---|
| `per_page` | `50` | 1 – **100** | page size. **101 is a 400**, never a silent clamp |
| `after_id` | `0` | ≥ 0 | cursor: return properties whose id is **greater than** this |
| `status` | all exported | `publish`, `draft`, `pending`, `private`, `future` | optional filter; accepts `?status=publish,draft` or `?status[]=publish&status[]=draft`. **`trash` is rejected with a `400`**, as is any list containing it |

`GET` is the only method; `POST` returns `404`. `OPTIONS` on the route returns the full JSON Schema,
so you can generate types from the live endpoint instead of transcribing this document.

Use `per_page=100` always. A request for one property is **no faster** than a request for a hundred —
the cost is dominated by WordPress booting, not by the data — so a smaller page size just multiplies
the number of requests.

### One property by id

```bash
curl -s "https://camara.cr/wp-json/cccbr-orbitas/v1/propiedades/184589"
```

The body is **the property object itself** — no `data`, no `pagination`, no `totals` — and it is
**identical** to the same property as the collection returns it, field for field. Both routes run
the same code; the reconciliation checks it with `===` on a sample every run.

Use it to refresh **one** record: after a webhook, on a retry, or when someone asks for a specific
listing. Do **not** walk the collection and then re-fetch each item by id — the collection already
gives you the complete object, and 2672 single requests cost far more than 27 pages.

A `404` means "no exported property has that id", and that is all it means. The id may be unknown,
belong to something that is not a property, or be a **trashed** property — the response is the same
in every case (`cccbr_orbitas_property_not_found`), on purpose, so the endpoint cannot be used to
probe what exists. For your import the handling is the same anyway: the property is not available,
so stop tracking it.

---

## 4. Walking the whole collection

```
after_id ← 0
loop:
    GET /propiedades?per_page=100&after_id={after_id}
    process response.data
    if response.pagination.has_more is false:  stop
    after_id ← response.pagination.next_after_id
```

```bash
# Bash equivalent, writing one JSON file per page.
after=0; page=0
while : ; do
  page=$((page+1))
  curl -sS \
    "https://camara.cr/wp-json/cccbr-orbitas/v1/propiedades?per_page=100&after_id=$after" \
    -o "page_$page.json"
  more=$(jq -r '.pagination.has_more' "page_$page.json")
  [ "$more" = "true" ] || break
  after=$(jq -r '.pagination.next_after_id' "page_$page.json")
done
```

**Why this cannot skip or repeat a row.** The page is `WHERE ID > after_id ORDER BY ID ASC LIMIT n`
on the primary key. A property inserted or deleted anywhere in the set while you are walking cannot
shift another property across a page boundary — which is exactly what makes `OFFSET` pagination
unsafe. `has_more` is computed by asking the database for one row beyond the page, so it stays
correct as the data changes.

### Taking a clean snapshot

`totals` is recomputed on every request, so during a long walk it can drift. If you want exactly the
set that existed when the walk started:

1. From the **first** page, record `pagination.max_id` and `generated_at`.
2. Ignore any property whose `id` is greater than that `max_id`.

The result is the set as of `generated_at`, minus anything deleted in the meantime. Properties added
during the walk get **higher** ids, so they arrive at the end and are easy to exclude — or to keep,
if you would rather have them.

### Detecting what changed since last time

Every property carries `modified_at_gmt`. An incremental import can walk the whole set and skip
anything whose `modified_at_gmt` has not moved since the previous run. There is no
"modified since" filter on the endpoint: with 3055 properties a full walk costs ~25 seconds, so the
filter would add contract surface for very little gain.

---

## 5. Errors and retries

| HTTP | `code` | Retry? | What to do |
|---|---|---|---|
| **400** | `rest_invalid_param` | **No** | A bad argument. The specific reason is in `data.details.<argument>.code`: `cccbr_orbitas_invalid_per_page`, `…_invalid_after_id`, `…_invalid_status`, `…_invalid_id`. Fix the request |
| **404** | `cccbr_orbitas_property_not_found` | **No** | Single route only: no exported property has that id. Unknown, wrong type and trashed are indistinguishable by design. Stop tracking that id |
| **404** | `rest_no_route` | **No** | Wrong path or method |
| **429 / 5xx** | — | **Yes** | Transient. Retry the **same `after_id`** with exponential backoff |

There is **no `401` and no `403`**: the endpoint is public, so a request is never rejected for a
missing credential or a missing permission.

Match on `code`, never on `message` — messages are translated and may be reworded.

**Retrying is safe.** The same `after_id` always returns the same page for unchanged data, so a
failed page can simply be requested again; no cursor is consumed and nothing is skipped. Keep the
last successful `after_id` in your job state and a crashed run resumes from there instead of
starting over.

Timeouts: a page of 100 takes roughly 0.5 s against the replica; allow a generous client timeout
(30 s) and treat a timeout as a retryable failure on the same cursor.

---

## 6. A property, in full

Synthetic, but every field is present exactly as the endpoint shapes it.

```json
{
  "id": 123456,
  "title": "Casa en condominio con vista al valle",
  "slug": "casa-en-condominio-con-vista-al-valle",
  "url": "https://camara.cr/propiedad/casa-en-condominio-con-vista-al-valle/",
  "status": "publish",
  "created_at_gmt": "2026-02-14T15:04:22Z",
  "modified_at_gmt": "2026-08-01T09:12:51Z",
  "content": "<p>Casa de dos plantas en condominio con seguridad 24/7...</p>",
  "description_en": "<p>Two-storey home in a gated community...</p>",
  "expiration": { "at_gmt": "2027-02-14T15:04:22Z", "action": "draft" },

  "operation":     [ { "key": "Venta", "label": "Venta" } ],
  "property_type": [ { "key": "Casa en condominio", "label": "Casa en condominio" } ],
  "land_use":      [ { "key": "usoresidencial", "label": "Residencial" } ],
  "exclusivity":   [ { "key": "completa", "label": "Completa" } ],
  "sold":          [],
  "share":         [ { "key": "compartir_propiedad", "label": "Compartir Propiedad" } ],
  "amenities":     [],

  "price": {
    "sale":       185000,
    "rent":       null,
    "final_sale": null,
    "currency":   "Dólares"
  },
  "features": {
    "bedrooms":   3,
    "bathrooms":  2.5,
    "garage":     2,
    "floors":     2,
    "land_area":  350,
    "built_area": null,
    "year_built": 2015
  },

  "registry_number": "1-234567-000",
  "purchase_option_terms": "",
  "video_url": "https://www.youtube.com/watch?v=EXAMPLE",
  "map_raw": "Condominio Example, Santa Ana",

  "location": {
    "province": { "id": 3222, "name": "San José",  "slug": "san-jose" },
    "canton":   { "id": 9001, "name": "Santa Ana", "slug": "santa-ana" },
    "district": { "id": 9002, "name": "Pozos",     "slug": "pozos" }
  },
  "coordinates": { "lat": 9.9325, "lng": -84.1835, "source": "jetengine_geocode:mapa" },

  "media": {
    "featured": {
      "id": 90210,
      "url": "https://camara.cr/wp-content/uploads/2026/02/example-front.jpg",
      "mime_type": "image/jpeg",
      "width": 1600,
      "height": 1067,
      "alt": "Fachada principal"
    },
    "gallery": [
      {
        "id": 90210,
        "url": "https://camara.cr/wp-content/uploads/2026/02/example-front.jpg",
        "mime_type": "image/jpeg",
        "width": 1600, "height": 1067, "alt": "Fachada principal"
      },
      {
        "id": 90211,
        "url": "https://camara.cr/wp-content/uploads/2026/02/example-living.jpg",
        "mime_type": "image/jpeg",
        "width": 1600, "height": 1067, "alt": ""
      }
    ]
  },

  "private_documents": { "has_contract_photo": true, "has_attachments": false },

  "creator":     { "id": 1234, "display_name": "A. Example", "email": "a.example@example.org" },
  "responsible": null,
  "company":     { "id": 99, "display_name": "Example Bienes Raíces S.A." },
  "associate_id": "1234",
  "marketplace_id": null,
  "leads_count": 0,
  "origin": { "source": "wp_user_frontend", "wpuf_form_id": 9536 },

  "issues": [
    { "code": "number_unparsable", "field": "features.built_area", "detail": "180 m2" }
  ]
}
```

### Reading it

- **No field is ever omitted.** A missing value is `null`, `[]` or `false`, so you can map
  positionally without existence checks.
- **There is no trash field and no trashed property.** `status` is `publish` or `draft` today. If a
  property you imported earlier stops appearing in a later walk, it was deleted or trashed — treat
  both the same way, and see §5 on reconciling a shrinking total.
- **Classification fields are always arrays** of `{ key, label }` — including `property_type`, which
  holds zero or one entry. Import the `key`; show the `label`. A `label` of `null` means the stored
  token matched no known option and is reported in `issues`; the raw token is still in `key`.
- **Every number is a number or `null`.** It is filled only when the stored text is unambiguously
  numeric. When it is `null` and the source was not empty, the `issues` list carries the reason
  **and the original string** — in the example above, `built_area` is `null` because the source
  holds `180 m2`. See §7.
- **`location` names its three levels**: `province`, `canton`, `district`, each `{ id, name, slug }`
  or `null`. All three members are always present. A property assigned only a distrito still arrives
  with its cantón and province, because the taxonomy itself states that relationship.
- **A `null` level means the source is silent or self-contradictory**, never that the endpoint gave
  up quietly: look for `location_missing`, `location_incomplete` or `location_ambiguous` in
  `issues`. Two cantones on one property, or a distrito belonging to a different cantón, clear the
  affected level rather than picking a winner.
- **`coordinates` are geocoded from the `map_raw` text**, not surveyed — hence the explicit
  `source`. Treat them as approximate.
- **The featured image can also appear inside the gallery.** That is how the property was saved in
  the source, and the example above shows it: `media.featured.id` is `90210` and the gallery's first
  entry is the same `90210`. The endpoint does **not** filter it out, because `media.gallery` is
  deliberately the order CCCBR saved. If you import both into one list, **de-duplicate by the media
  `id`**.
- **Only the original image URL is returned.** WordPress's generated thumbnail sizes are not part of
  the payload (they were removed in 0.2.0): derive whatever sizes you need on your side.
- **`content` is the raw stored HTML.** No shortcode is expanded and no page-builder markup is
  injected, so what you get is the text CCCBR saved. It may contain `<p>`, `<strong>` and `<br />`.
- **`issues` describes the data, not the request.** A property with issues is still returned in
  full; the codes tell you which fields you should not trust.

---

## 7. Why a number can be `null`

The source stores these fields as free text, and people have typed into them for years. Measured
across the ten numeric fields of all 3055 properties:

| | Count | Example | What the endpoint does |
|---|---|---|---|
| Unambiguously numeric | the large majority of non-empty feature values | `185000`, `2.5` | the field is the number |
| **Ambiguous separators** | **309** | `150.000`, `150,000`, `1.250,75` | `null` + `number_ambiguous`, `detail` = the original string. `150.000` is either one hundred fifty thousand or a number with three decimals, and 333 properties genuinely use decimals for half-bathrooms, so guessing would corrupt prices |
| **Not a number at all** | **78** | `180 m2`, `$120k`, `2 a 3`, `consultar` | `null` + `number_unparsable`, `detail` = the original string |

So: use the field when it is not `null`; when it is, look for the matching issue and queue its
`detail` for human review. **Do not re-parse `detail` with a permissive parser** — that
re-introduces exactly the ambiguity the endpoint refused to resolve. If CCCBR cleans these fields,
the same properties will start returning a number with no change on your side.

Matching an issue to a field is direct: `issues[].field` holds the contract path, for example
`features.built_area` or `price.sale`.

---

## 8. What the source does not contain

Plan for these. The counts are measured, not estimated, and come from
[`reconciliation.md`](reconciliation.md).

| Gap | Scale | What you receive | Suggested handling |
|---|---|---|---|
| **No Marketplace / Orbitas id anywhere** | all 2672 | `marketplace_id: null` always | Keep your own mapping keyed on the WordPress `id`, which is stable. If you need a round-trip id, CCCBR has to add the field first |
| **Currency almost never recorded** | only **9** in the source, 9 exported | `price.currency: null` + `price_currency_missing` | **Never infer** a currency from the amount. Import the price as unknown-currency and ask CCCBR, or agree one default in writing |
| **No location** | **1019** of 2672 | all three members `null` + `location_missing` | Fall back to `map_raw`, which is free text, or leave the listing unplaced. **1626** properties have all three levels |
| **Partial location** | **27** properties | the missing member `null` + `location_incomplete` (`detail` lists which) | Import what resolved. Today all 34 are province-only |
| **Conflicting location** | **0** today | the contested member `null` + `location_ambiguous` | It can appear if someone assigns two cantones or a mismatched distrito. A conflict is a cleanup item for CCCBR, never something to resolve on import |
| **No coordinates** | most have none; a further **105** failed geocoding | `coordinates: null` (+ `coordinates_geocode_error` for the 108) | Geocode on your side from `location` + `map_raw` if you need a pin |
| **Stale coordinates** | 2 | coordinates **plus** `coordinates_stale` | The address changed after geocoding; re-geocode if precision matters |
| **Broken image references** | attachment ids that no longer exist, over **326** references | the id is omitted from `gallery` and reported as `media_attachment_missing` | Import the images that resolve; the report tells CCCBR what to re-upload |
| **No featured image** | **49** | `media.featured: null` + `media_featured_missing` | Use the first gallery image, or a placeholder |
| **No gallery** | **75** | `gallery: []` + `media_gallery_empty` | — |
| **Images without dimensions** | 12, all HEIC | `width`/`height` `null` | WordPress stores no metadata for HEIC. Fetch the original and size it yourself |
| **Unknown origin** | **478** | `origin.source: "unknown"` + `origin_unknown` | Audit only; nothing to import |
| **No associate number** | **8** properties | `associate_id: null` + `associate_id_missing` | Map by `creator.id` |
| **No responsible associate** | all 2672 | `responsible: null` | The field exists in CCCBR's form but has never been filled. Treat the `creator` as responsible |
| **No company** | 2267 | `company: null` | Only one associate currently belongs to a company relation |
| **Amenities never filled** | all 2672 | `amenities: []` | The field exists but is wired to no option list in CCCBR's configuration |
| **Unmapped option tokens** | 53 occurrences, 2 distinct values | `{ key: "<raw token>", label: null }` + `option_value_unmapped` | The two are `lote_residencial_/_comercial` (59) and `uso mixto` (1) — decide with CCCBR what they map to |

### Issue codes you will actually see

Measured over a full walk of all 3055 properties:

| Code | Occurrences |
|---|---|
| `price_currency_missing` | 2503 |
| `location_missing` | 1019 |
| `origin_unknown` | 478 |
| `media_attachment_missing` | 326 |
| `number_ambiguous` | 309 |
| `coordinates_geocode_error` | 105 |
| `number_unparsable` | 78 |
| `media_gallery_empty` | 75 |
| `number_missing` | 56 |
| `option_value_unmapped` | 53 |
| `media_featured_missing` | 49 |
| `location_incomplete` | 27 |
| `associate_id_missing` | 8 |
| `coordinates_stale` | 2 |
| `location_ambiguous` | **0** — no property in the current data has two terms on one level or a contradicted ancestry |

A token that matched a known option only after folding accents, case or underscores is **not**
reported: you receive the canonical `key` and `label`, so there is nothing to act on. Only a token
that matches nothing at all appears, as `option_value_unmapped`.

---

## 9. Known limitations of the endpoint itself

1. **`media_file_missing` has never been exercised.** The check is implemented, but the development
   replica has no media files at all (uploads are served from production), so it is switched off
   there. Before you rely on that specific code, ask for one verification run on an environment
   that holds the real uploads directory. Broken attachment **references** — the 301 above — are
   fully verified.
2. **`totals` is a per-request snapshot**, not a transaction across the walk. Use the `max_id`
   recipe in §4 if you need a fixed set.
3. **`url` may not resolve** for trashed properties. It is there for audit, not for linking.
4. **`video_url` and `map_raw` are raw user input** — neither validated nor normalized. Expect
   non-URL text in `video_url` on a handful of properties.
5. **No "modified since" filter**, by design — see §4.

---

## 10. A checklist for the first import

1. Confirm the URL with CCCBR, and agree when the endpoint will be taken down again.
2. `curl` one page of 1, with no credentials, and confirm you get `200` and the envelope.
3. `OPTIONS` the route and generate your types from the schema.
4. Walk with `per_page=100`, storing each page verbatim before transforming it. **27 pages** today.
5. Record `pagination.max_id` and `generated_at` from the first page.
6. Reconcile: your row count must equal the first page's `totals.total` (plus anything added during
   the walk), and your distinct ids must equal that count.
7. Import. Everywhere a number is `null`, queue the matching issue's `detail` for review.
8. Aggregate the `issues` codes into a report for CCCBR — that list is the data-cleanup backlog, and
   it is the same list in §8 above.
9. Agree with CCCBR on the three decisions the data cannot make for you: the currency default,
   whether a Marketplace id field should exist, and what the two unmapped option tokens mean.
10. For ongoing refreshes, use `…/propiedades/{id}` for single records and a full walk for the
    periodic sweep.
