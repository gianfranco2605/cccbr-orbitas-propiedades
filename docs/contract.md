# Response contract — `GET /wp-json/cccbr-orbitas/v1/propiedades`

Version **0.1.0**. Derived from the measured source schema in [`docs/schema.md`](schema.md) — every
field below names the real source key it comes from. Where the contract sketch in `LOOP_PROMPT.md`
§6 had to change, the reason is given in [§9](#9-changes-against-the-contract-sketch).

All examples are **synthetic**. The real database contains personal data of real people; no sample
in this repository comes from it.

---

## 1. Route, method, authentication, authorization

| | |
|---|---|
| Route | `GET /wp-json/cccbr-orbitas/v1/propiedades` |
| Namespace / version | `cccbr-orbitas/v1` |
| Methods | `GET` (and `HEAD`). The route is **read-only**: it has no side effects and registers no writable method |
| Collection only | There is **no** `…/propiedades/<id>` single-item route |
| Authentication | WordPress **Application Passwords** over HTTP Basic. No custom auth, no JWT, no API key |
| Authorization | capability **`cccbr_orbitas_read_propiedades`** |
| Who has it | granted to the `administrator` role on plugin activation; removed on uninstall. The plugin never creates users or application passwords |
| Content type | `application/json; charset=UTF-8` |
| Caching | the response sets `no-store` (WordPress merges it with its own REST directives, so the header reads `no-cache, must-revalidate, max-age=0, no-store, private`) — the payload contains personal data of the property creator |

```bash
curl -s -u "<user>:<application password>" \
  "https://camara.cr/wp-json/cccbr-orbitas/v1/propiedades?per_page=100"
```

The application password must be sent as the HTTP Basic password with its spaces intact (WordPress
accepts them with or without). Credentials are **never** accepted as query parameters.

---

## 2. Request arguments

| Arg | Type | Default | Constraints | Meaning |
|---|---|---|---|---|
| `per_page` | integer | `50` | `1` … **`100`**; anything else → **400** | page size. No `-1`, no "all" |
| `after_id` | integer | `0` | `>= 0`; non-integer → **400** | keyset cursor: return properties with `ID > after_id` |
| `status` | string or array of strings | *(all)* | each value one of `publish`, `draft`, `pending`, `private`, `future`, `trash`; anything else → **400** | optional status filter. Accepts a comma-separated list (`?status=publish,draft`) or repeated params (`?status[]=publish&status[]=draft`) |

Unknown query parameters are ignored (standard WordPress REST behaviour). `_fields` and
`_envelope` work as they do for any WordPress REST route.

**Statuses present in the source today** are only `publish` (2612), `trash` (383) and `draft` (60).
The other three are accepted so the contract does not change if they ever appear.
`auto-draft` and `inherit` are **never** returned and are **rejected** as filter values:
`auto-draft` is an unsaved editor placeholder with no property data, and `inherit` belongs to
revisions and attachments, not properties.

---

## 3. Collection response (HTTP 200)

```json
{
  "data": [ { "…one property object…": "see §4" } ],
  "pagination": {
    "per_page": 50,
    "after_id": 0,
    "next_after_id": 81042,
    "has_more": true,
    "max_id": 184589
  },
  "totals": {
    "total": 3055,
    "by_status": {
      "publish": 2612,
      "trash": 383,
      "draft": 60
    }
  },
  "generated_at": "2026-10-13T17:04:51Z"
}
```

| Field | Type | Notes |
|---|---|---|
| `data` | array of property objects | ordered by `id` **ascending**, always; never empty unless the page is past the end or the filter matches nothing |
| `pagination.per_page` | integer | the effective page size (the validated request value) |
| `pagination.after_id` | integer | the cursor this page was requested with (echoed back) |
| `pagination.next_after_id` | integer\|null | the `id` of the **last** item in `data`; pass it as the next `after_id`. `null` when `data` is empty |
| `pagination.has_more` | boolean | `true` when at least one more property exists after `next_after_id` under the same filter |
| `pagination.max_id` | integer\|null | largest property `id` in the source at request time, under the same filter. Added for snapshot consistency (§5); `null` when the filter matches nothing |
| `totals.total` | integer | number of properties matching the filter **at request time** |
| `totals.by_status` | object | count per status matching the filter at request time. Statuses with zero matches are omitted |
| `generated_at` | string | UTC, ISO 8601 (`YYYY-MM-DDTHH:MM:SSZ`), the moment the response was built |

`totals` is computed with one aggregate query per request, so it is a snapshot of the moment, not of
the whole walk (§5).

---

## 4. The property object

English `snake_case`. **No field is ever omitted**: a missing source value is `null`, `[]` or
`false` as the type below says, so the shape is stable for every item.

Nothing is inferred. When a value is missing, ambiguous or broken, the item says so in `issues`
(§4.11) instead of being filled with a guess.

### 4.1 Identity and lifecycle

| Field | Type | Source | Notes |
|---|---|---|---|
| `id` | integer | `wp_posts.ID` | stable WordPress id, the cursor key |
| `title` | string | `post_title` | raw, unfiltered |
| `slug` | string | `post_name` | for trashed properties this still carries the `__trashed` suffix (373 of 383) |
| `url` | string\|null | `get_permalink()` | `null` when WordPress cannot build one (e.g. trashed). Historical URL for audit, not a guarantee the page resolves |
| `status` | string | `post_status` | `publish`, `draft` or `trash` in the current data |
| `trash` | object\|null | the WordPress trash meta | `null` unless `status` is `trash`. Otherwise `{ "historical_slug", "status_before_trash", "trashed_at_gmt" }`, each a string or `null`. Grouped into one nullable object in 0.2.0: as three top-level fields they were `null` on **2672 of 3055** properties. `historical_slug` comes from `_wp_desired_post_slug`, not from stripping the `__trashed` suffix, because 10 of the 383 trashed properties have no suffix |
| `created_at_gmt` | string | `post_date_gmt` | UTC ISO 8601. No zero dates exist in the source |
| `modified_at_gmt` | string | `post_modified_gmt` | UTC ISO 8601. Use it to detect records that changed during a walk (§5) |
| `content` | string | `post_content` | **raw stored content**. `the_content` filters are never applied, so no shortcode is expanded and no Elementor markup is injected |
| `description_en` | string | `descripcion-en-ingles` | raw stored wysiwyg value; `""` when absent (2739 of 3055 properties have the key) |
| `expiration` | object\|null | `_jet_pep_period`, `_jet_pep_action` | `{ "at_gmt": "<UTC ISO 8601>", "action": "draft" }`, or `null` when the property has no expiration (541 of 3055 do). `action` is `draft` for all 541 today |

### 4.2 Classification — the `{ key, label }` shape

`operation`, `property_type`, `land_use`, `exclusivity`, `sold`, `share` and `amenities` are
**always arrays** of `{ "key": string, "label": string|null }`, even for the single-select
`property_type` (an array of 0 or 1 entries), so the consumer never branches on the shape.

| Field | Source key | Option set | Today |
|---|---|---|---|
| `operation` | `estatus-propiedad` | glossary **15** (`Alquiler`, `Venta`, `opciondecompra` → "Alquiler con opción de compra") | `Venta` 2595, `Alquiler` 508, option-of-purchase 24 across two spellings |
| `property_type` | `tipo-de-propiedad` | glossary **12** (15 entries) | 18 distinct raw values; 3 do not resolve |
| `land_use` | `uso-de-suelo` | field options (`usoresidencial`→Residencial, `usocomercial`→Comercial, `usoindustrial`→Industrial, `usoagricola`→Agrícola) | 42 distinct raw values |
| `exclusivity` | `exclusividad` | field options (`completa`→Completa, `solopublicacion`→Sólo publicación) | 1230 properties empty |
| `sold` | `propiedad_vendida` | field option (`vendida`→Propiedad vendida) | 86 properties sold |
| `share` | `compartir_propiedad` | field option (`compartir_propiedad`→Compartir Propiedad) | 954 shared |
| `amenities` | `amenidades` | **none wired** (the field has empty options and a stale `glossary_id: 11`) | all 5 stored values empty → always `[]` today |

**Normalization (the same for all seven), because the source uses six storage shapes:**

1. `maybe_unserialize` the stored value and flatten arrays. `NULL` is treated as missing.
2. Split every scalar on `|`, trimming whitespace — a single value may hold several selections
   (`Alquiler | Venta`), including inside a serialized array.
3. Drop empty tokens and the junk values `false`, `true`, `-` and `-1`.
4. Resolve each remaining token against the option set in this order: exact option key → exact
   label → accent/case-folded label → slugified label (`local_comercial` style). First match wins
   and yields `{ key: <canonical option key>, label: <option label> }`.
5. A token that does not resolve at all is returned **verbatim** as
   `{ "key": "<raw token>", "label": null }` plus the issue `option_value_unmapped`. The raw token
   is never dropped and a label is never guessed.
6. Duplicate tokens within one field are collapsed.

**How** a token matched is not reported. Until 0.2.0 a match that needed folding raised
`option_value_normalized` on 367 properties — but a successful mapping is not a data problem, and
the consumer receives the canonical key and label either way.

### 4.3 Price

```json
"price": {
  "sale":       185000,
  "rent":       null,
  "final_sale": null,
  "currency":   null
}
```

| Field | Source | Notes |
|---|---|---|
| `price.sale` | `precio-de-venta` | number or `null` (§4.5) |
| `price.rent` | `precio-de-alquiler` | number or `null` |
| `price.final_sale` | `precio-final-de-venta` | number or `null`; the key exists on 1032 properties and only 14 have a value |
| `price.currency` | `moneda` | `"Colones"`, `"Dólares"` or **`null`**. Only **9 of 3055** properties have a currency. It is **never inferred** from the amount, the location or anything else; `price_currency_missing` is reported instead |

### 4.4 Features

```json
"features": {
  "bedrooms":   3,
  "bathrooms":  2,
  "garage":     null,
  "floors":     1,
  "land_area":  350,
  "built_area": null,
  "year_built": 2015
}
```

Sources: `habitaciones`, `banos`, `garage`, `plantas`, `terreno`, `construccion`,
`ano-de-construccion`. All seven are a number or `null`. In the example `built_area` is `null`
because the source holds `180 m2`, and the issue list carries
`{ "code": "number_unparsable", "field": "features.built_area", "detail": "180 m2" }`.

### 4.5 The rule for every number

Every numeric field is **a number or `null`** — never a wrapper object. A number is produced
**only** for `^-?\d+$` (integer) and `^-?\d+\.\d{1,2}$` (a decimal with one or two places).
Anything else is `null`, and the reason **together with the original string** goes into `issues`:

| Stored value | Field value | Issue (`code` / `detail`) |
|---|---|---|
| `185000` | `185000` | — |
| `1250.50` | `1250.5` | — |
| `150.000` | `null` | `number_ambiguous` / `"150.000"` — dot-thousands or a 3-decimal number cannot be told apart |
| `150,000` | `null` | `number_ambiguous` / `"150,000"` — comma-thousands or decimal comma cannot be told apart |
| `180 m2`, `$120k`, `2 a 3` | `null` | `number_unparsable` / the original string |
| `consultar` (no digits) | `null` | `number_unparsable` / `"consultar"` |
| `""`, missing, `NULL` | `null` | none, except `number_missing` for `price.sale` (see §4.11) |

**The issue's `detail` is the only place a raw source value appears.** Until 0.2.0 every numeric
field was a `{ raw, value }` pair; that put a wrapper on 10 fields of all 3055 properties to carry
information that only 436 of them actually needed. The raw string is still available — it has simply
moved to where the problem is reported.

This matters because **every one of the ten numeric source fields contains non-numeric values**
(`docs/schema.md` §2.4), including the four JetEngine declares as `number`. A consumer should read
`null` as "ask CCCBR", not as zero, and should **not** re-parse `detail` with a permissive parser:
that re-introduces exactly the ambiguity the endpoint refused to resolve.

### 4.6 Text fields

| Field | Type | Source | Notes |
|---|---|---|---|
| `registry_number` | string | `numero-de-finca` | *número de finca*; `""` on 2214 properties |
| `purchase_option_terms` | string | `opciondecompracondiciones` | raw wysiwyg; the key exists on 5 properties |
| `video_url` | string | `video_propiedad` | raw stored string; 638 are URLs, 7 are something else, 2410 empty. **Not validated or rewritten** |
| `map_raw` | string | `mapa` | the raw address/location string the geocoder was fed; `""` on 2464 properties |

### 4.7 Location

```json
"location": {
  "province": { "id": 3222, "name": "San José",  "slug": "san-jose" },
  "canton":   { "id": 9001, "name": "Santa Ana", "slug": "santa-ana" },
  "district": { "id": 9002, "name": "Pozos",     "slug": "pozos" }
}
```

Source: taxonomy **`ubicacion-propiedad`** — the only taxonomy the CPT uses.

- **Always an object with exactly these three members.** Each is `{ id, name, slug }` or `null`.
  Until 0.2.0 this was a flat array of terms carrying a `level` number; naming the levels says what
  the data is, and the property form confirms it by labelling its three selects
  **"Provincia / Cantón / Distrito"**. The taxonomy is exactly three levels deep and its seven root
  terms are the seven provinces of Costa Rica.
- A member is `null` when the source does not say, **or when the source contradicts itself**.
- A level not assigned directly is completed from the **ancestry of a deeper assigned term** — a
  district alone yields its cantón and province, because the taxonomy itself states that
  relationship. That is reading the data, not guessing, and raises no issue.

**Conflicts are reported, never resolved.** The source does not guarantee one term per level:

| Situation | Result | Issue |
|---|---|---|
| no term at all, or no assigned term exists in the taxonomy | all three `null` | `location_missing` |
| at least one member resolved, at least one `null` | the resolved members | `location_incomplete`, `detail` = the missing members (`"canton,district"`) |
| two or more terms on the same level (two cantones, say) | that member `null` | `location_ambiguous`, `detail` = the member name |
| a term whose parent is not the term assigned one level up | the **deeper** member `null`, the assigned shallower one kept | `location_ambiguous`, `detail` = the cleared member |
| a term deeper than the taxonomy's three levels | not placed | `location_ambiguous`, `detail` = the term id |
| nothing resolvable after the conflicts | all three `null` | `location_ambiguous` **and** `location_missing` |

A location is never inferred from `map_raw` or from the coordinates.

### 4.8 Coordinates

```json
"coordinates": { "lat": 9.9281, "lng": -84.0907, "source": "jetengine_geocode:mapa" }
```

| | |
|---|---|
| Source | `md5('mapa')_lat` / `_lng` / `_hash` — JetEngine Maps Listings' geocoding cache for the `mapa` field (verified, `docs/schema.md` §5) |
| `lat`, `lng` | numbers, validated like JetEngine does: numeric, \|lat\| ≤ 90, \|lng\| ≤ 180 |
| `source` | always the literal `"jetengine_geocode:mapa"` — these are **geocoded**, not surveyed, coordinates |
| `null` when | the property has no coordinate meta (2557 of 3055), **or** the stored value is a JetEngine geocoding error marker (`je-coord-error…`, **107 of 498**) → issue `coordinates_geocode_error`, **or** the value is out of range → issue `coordinates_invalid` |
| Stale cache | when `_hash ≠ md5( mapa )` the address changed after geocoding (**7 of 498**): the coordinates are still returned, with the issue `coordinates_stale` |

### 4.9 Media

```json
"media": {
  "featured": {
    "id": 90210, "url": "https://camara.cr/wp-content/uploads/2026/01/example.jpg",
    "mime_type": "image/jpeg", "width": 1600, "height": 1067,
    "alt": "Example alt text"
  },
  "gallery": [ { "…same shape…": "…" } ]
}
```

| | |
|---|---|
| `featured` | from `_thumbnail_id` (2994 usable ids, all pointing at a real attachment). When absent, `imagen-principal` is used as a fallback (5 properties, `value_format: both`). `null` when neither resolves |
| `gallery` | from `imagenes`, **in stored order**, duplicates removed, the featured image **not** removed. `[]` when empty |
| Gallery parsing | the source uses three shapes — serialized array (2092), **comma-separated id list (899)**, single id (26) — plus 13 `NULL` rows. All are parsed; a non-numeric token raises `media_gallery_unparsable` |
| Item fields | `id` (integer), `url` (string\|null) — the **original** file, `mime_type` (string\|null), `width`/`height` (integer\|null), `alt` (string) |
| Derivatives | **not exposed.** Orbitas has not asked for WordPress's generated sizes, and shipping them inflated a page of 100 properties by tens of thousands of URLs for data the importer would most likely discard. Only the original `url` is returned; a consumer that needs thumbnails can derive its own. Removed in 0.2.0 |
| The featured image may also appear in the gallery | The source frequently stores the same attachment in both places, so `media.featured.id` can repeat inside `media.gallery`. It is **not** filtered out, because the gallery order is the order CCCBR saved. De-duplicate by `id` if you import them into one list |
| Broken references | a gallery/featured id with no attachment post → the id is reported in `issues` as `media_attachment_missing:<id>` and **omitted** from `gallery` |
| Missing files | when the attachment's file is missing on disk the issue is `media_file_missing:<id>`. **This check cannot be verified on the local replica** (`uploads` is not local and 302-redirects to production) — see Q3 in `docs/OPEN-QUESTIONS.md` |

### 4.10 People, relations and audit

| Field | Type | Source | Notes |
|---|---|---|---|
| `creator` | object\|null | `post_author` → `wp_users` | `{ "id": int, "display_name": string, "email": string }`. **Only those three fields, ever.** `null` if the author does not exist (0 cases today) |
| `responsible` | object\|null | `corredor_asignado` (user id) | same shape; `null` when unset or equal to the creator. **`null` for every property today** — all 5 stored values are empty |
| `company` | object\|null | JetEngine relation **131** (`wp_jet_rel_131`), parent of the creator | `{ "id": int, "display_name": string }`. **448 properties** resolve a company; the other 2607 get `null`. The relation has no child under two parents, so the lookup is unambiguous |
| `associate_id` | string\|null | user meta **`numero-de-asociado`** of the creator | the CCCBR associate number (1623 users have it). `null` + issue `associate_id_missing` when absent. See Q4 in `docs/OPEN-QUESTIONS.md` |
| `marketplace_id` | null | **no source exists** | always `null`, with no per-property issue (§4.11). A key-name search across all user and post meta found no Marketplace / Orbitas / Azure field. The Wasi CRM keys are a different system and `wasi_token` is a credential — nothing is inferred from them |
| `leads_count` | integer | `COUNT(*)` on `wp_jet_rel_171` by `parent_object_id` (and `rel_id = 171`) | 21 properties have leads today. **No lead data is exposed**, only the count |
| `origin` | object | `_wpuf_form_id` | `{ "source": "wp_user_frontend"\|"unknown", "wpuf_form_id": int\|null }`. 2512 properties → form `9536`; **540 have no origin marker at all** and report `source: "unknown"` + issue `origin_unknown`. JetFormBuilder leaves no per-post marker, so its authorship is never claimed |
| `private_documents` | object | `foto-del-contrato`, `documentos-anexos` | `{ "has_contract_photo": bool, "has_attachments": bool }` — **booleans only, never the files or ids**. True for 133 and 0 properties respectively (953 of the 1089 `foto-del-contrato` rows hold `NULL`) |

**Never exposed for any person:** login, password, hash, activation key, session tokens, API
tokens, capabilities, roles, phone numbers, national id (`cedula_corredor`), birth date, sex,
payment status, affiliation dates or any other user meta. People objects are assembled from the
explicit allow-list above; user meta is never iterated.

### 4.11 `issues` — machine-readable data-quality codes

`issues` is an array (`[]` when the property is clean) of
`{ "code": string, "field": string|null, "detail": string|null }`. Codes are **stable**; `detail`
is a short machine-oriented string (an id or token), never a sentence and never personal data.

| Code | Raised when |
|---|---|
| `title_name_mismatch` | `nombre-de-propiedad` has a value that differs from `post_title` |
| `option_value_unmapped` | an option token matched no option at all (returned raw, `label: null`) |
| `number_ambiguous` | a numeric value could be thousands-separated or decimal (`150.000`) |
| `number_unparsable` | a non-empty numeric field could not be parsed at all |
| `number_missing` | `precio-de-venta` is empty while `operation` includes `Venta` |
| `price_currency_missing` | a price has a value but `moneda` is empty |
| `location_missing` | nothing could be resolved: no term, no known term, or every level lost to a conflict |
| `location_incomplete` | at least one of province / cantón / distrito is `null` while another resolved. `detail` lists the missing members |
| `location_ambiguous` | the source contradicts itself — two terms on one level, a term whose ancestry disagrees with another assigned term, or a term deeper than three levels. `detail` names the cleared member or the offending term id |
| `coordinates_geocode_error` | the stored lat/lng is a `je-coord-error…` marker |
| `coordinates_invalid` | the stored lat/lng is non-numeric or out of range |
| `coordinates_stale` | `md5( mapa ) ≠` the stored `_hash` |
| `media_featured_missing` | neither `_thumbnail_id` nor `imagen-principal` resolves |
| `media_gallery_empty` | the gallery is empty |
| `media_gallery_unparsable` | a gallery token is not an id |
| `media_attachment_missing` | a referenced attachment post does not exist (`detail` = the id) |
| `media_file_missing` | the attachment exists but its file does not (**unverifiable locally**) |
| `expiration_invalid` | `_jet_pep_period` is not a usable timestamp |
| `creator_missing` | `post_author` points at a user that does not exist |
| `responsible_missing_user` | `corredor_asignado` points at a user that does not exist |
| `associate_id_missing` | the creator has no `numero-de-asociado` |
| `origin_unknown` | no `_wpuf_form_id` and no other origin marker |

There is deliberately **no** `marketplace_id` issue code. `marketplace_id` is `null` for every
property because the field does not exist anywhere in the source (§4.10), and an issue identical on
all 3055 records would be noise in the consumer's log rather than information about a property.
The absence is documented here and in the consumer guide instead.

`issues` describes **this property's data**, not a request error. A property with issues is still
returned in full.

---

## 5. Pagination, limits and consistency

### 5.1 Walking the whole set

```
after_id = 0
loop:
  GET /propiedades?per_page=100&after_id=<after_id>
  process response.data
  if not response.pagination.has_more: stop
  after_id = response.pagination.next_after_id
```

- **Keyset, not offset.** The page is `WHERE post_type = 'cccbr-propiedades' AND ID > after_id
  ORDER BY ID ASC LIMIT per_page`. `ID` is the primary key, so ordering is total and stable.
- **No omissions, no duplicates.** Because the cursor is a strictly increasing primary key, a row
  inserted or deleted anywhere in the set while the walk is running cannot shift another row across
  a page boundary — the failure mode that makes `OFFSET` unsafe.
- `has_more` is computed by asking for one row beyond the page, not from `totals`, so it stays
  correct while the data changes.
- Re-requesting the **same `after_id`** returns the **same page** for unchanged data — the walk is
  idempotent and safe to retry.
- `per_page` is capped at **100** server-side; `per_page=101` is a **400**, never a silent clamp.

### 5.2 Records that change during a walk

| Event during the walk | Effect | How the consumer sees it |
|---|---|---|
| Property **added** | gets a new, higher `ID` → appears at the end of the walk | included as an extra item; never duplicated. Compare against `pagination.max_id` from the first page to exclude it from the snapshot |
| Property **deleted** (or trashed to a filtered-out status) | its `ID` is simply absent | the item never appears; the count will be below `totals.total` from the first page |
| Property **edited** | the next page containing it carries the new values | `modified_at_gmt` is later than the walk's start; re-fetchable |
| Property edited **after** its page was fetched | the consumer holds the older version | detectable by re-walking and comparing `modified_at_gmt` |

**Snapshot recipe:** record `pagination.max_id` and `generated_at` from the **first** page, then
ignore any item with `id > max_id`. The result is exactly the set that existed when the walk
started, minus anything deleted in the meantime. `totals` is recomputed per request, so it may
drift during a long walk — that is by design and is why the reconciliation in Task 11 compares
unique ids, not only counts.

### 5.3 Empty and final pages

- Past the end: `200` with `data: []`, `next_after_id: null`, `has_more: false`.
- A filter that matches nothing: `200` with `data: []`, `totals.total: 0`, `by_status: {}`.
  An empty result is never a `404`.

---

## 6. Errors

Standard WordPress REST error envelope, with a **stable `code`** and a translated `message`.

### 6.1 401 and 403 — our code is at the top level

```json
{ "code": "cccbr_orbitas_forbidden", "message": "…", "data": { "status": 403 } }
```

| HTTP | `code` | Raised when |
|---|---|---|
| **401** | `cccbr_orbitas_not_authenticated` | no authenticated user (missing or rejected credentials) |
| **403** | `cccbr_orbitas_forbidden` | authenticated, but the user lacks `cccbr_orbitas_read_propiedades` |
| **404** | `rest_no_route` | wrong path or method (WordPress core) |
| **500** | `cccbr_orbitas_unexpected` | an unexpected server error; the message never leaks internals |

### 6.2 400 — our code is nested, because WordPress wraps argument errors

**Verified against the running endpoint.** WordPress validates the declared arguments before the
route callback runs and always wraps the result in its own `rest_invalid_param` envelope, so the
specific code appears under `data.details.<argument>.code`:

```json
{
  "code": "rest_invalid_param",
  "message": "Invalid parameter(s): per_page",
  "data": {
    "status": 400,
    "params":  { "per_page": "The per_page argument must be an integer between 1 and 100." },
    "details": {
      "per_page": {
        "code": "cccbr_orbitas_invalid_per_page",
        "message": "The per_page argument must be an integer between 1 and 100.",
        "data": null
      }
    }
  }
}
```

| HTTP | Top-level `code` | `data.details.<arg>.code` | Raised when |
|---|---|---|---|
| **400** | `rest_invalid_param` | `cccbr_orbitas_invalid_per_page` | `per_page` is not an integer in `1…100` (verified for `101`, `0`, `abc`, `50.5`) |
| **400** | `rest_invalid_param` | `cccbr_orbitas_invalid_after_id` | `after_id` is not an integer `>= 0` (verified for `-1`, `xyz`) |
| **400** | `rest_invalid_param` | `cccbr_orbitas_invalid_status` | a `status` value is not one of the six allowed statuses (verified for `auto-draft`, `inherit`, `publish,bogus`) |

So a consumer checks **`data.details.<argument>.code`** for the specific reason and
`data.params` for the human message. The same stable codes also appear at the top level if the
argument ever reaches the callback unvalidated (for example if a filter stripped the route's
`args`), because the controller re-applies the identical checks.

`message` is translatable (text domain `cccbr-orbitas-propiedades`) and may change wording. **Match
on `code`, never on `message`.** No error body ever contains a credential, a file path, a stack
trace, or the rejected value echoed back.

### 6.3 Discovering the schema

`OPTIONS` on the route returns the full collection schema (verified: title
`cccbr_orbitas_propiedades_collection`, the four envelope members, **38** documented property
fields) together with the three arguments and their constraints, so a consumer can generate types
from the live endpoint instead of transcribing this document.

---

## 7. Guarantees and non-guarantees

**Guaranteed**

- Read-only: no `GET` to this route changes any data.
- Every status in the source is reachable; there is **no** implicit filter by date, publication,
  featured flag or associate status.
- The item shape is stable: no field is ever omitted.
- Field names and `code`/`issues` values are stable within `v1`; new fields may be **added**.
- No secret, credential or personal field outside the allow-list in §4.10 is ever returned.

**Not guaranteed**

- `totals` is a per-request snapshot, not a transaction across the walk (§5.2).
- `url` may point at a page that no longer resolves (trashed properties).
- `video_url` and `map_raw` are raw user input, neither validated nor normalized.
- Coordinates are **geocoded from a text field**, not surveyed, and 107 of 498 failed geocoding.
- Media **file** existence is implemented but unverified in the replica environment (Q3).

---

## 8. Source → contract map (quick reference)

| Contract field | Source |
|---|---|
| `id`, `title`, `slug`, `status`, `created_at_gmt`, `modified_at_gmt`, `content` | `wp_posts` |
| `url` | `get_permalink()` |
| `historical_slug`, `status_before_trash`, `trashed_at_gmt` | `_wp_desired_post_slug`, `_wp_trash_meta_status`, `_wp_trash_meta_time` |
| `description_en` | `descripcion-en-ingles` |
| `expiration` | `_jet_pep_period`, `_jet_pep_action` |
| `operation`, `property_type` | `estatus-propiedad` (glossary 15), `tipo-de-propiedad` (glossary 12) |
| `land_use`, `exclusivity`, `sold`, `share`, `amenities` | `uso-de-suelo`, `exclusividad`, `propiedad_vendida`, `compartir_propiedad`, `amenidades` |
| `price` | `precio-de-venta`, `precio-de-alquiler`, `precio-final-de-venta`, `moneda` |
| `features` | `habitaciones`, `banos`, `garage`, `plantas`, `terreno`, `construccion`, `ano-de-construccion` |
| `registry_number`, `purchase_option_terms`, `video_url`, `map_raw` | `numero-de-finca`, `opciondecompracondiciones`, `video_propiedad`, `mapa` |
| `location` | taxonomy `ubicacion-propiedad` |
| `coordinates` | `md5('mapa')_lat` / `_lng` / `_hash` |
| `media.featured`, `media.gallery` | `_thumbnail_id` (fallback `imagen-principal`), `imagenes` |
| `private_documents` | existence of `foto-del-contrato`, `documentos-anexos` |
| `creator` | `post_author` → `wp_users` (id, display name, email only) |
| `responsible` | `corredor_asignado` |
| `company` | relation 131, `wp_jet_rel_131` |
| `associate_id` | user meta `numero-de-asociado` |
| `marketplace_id` | — none — |
| `leads_count` | relation 171, `wp_jet_rel_171` |
| `origin` | `_wpuf_form_id` |

Excluded source keys and the reason for each: `docs/schema.md` §3.2.

---

## 9. Changes against the contract sketch in `LOOP_PROMPT.md` §6

Each change is required by a measurement in `docs/schema.md`; none of them touches a decision in
§2.3.

| Sketch | Contract | Why |
|---|---|---|
| `price: { sale, rent, final_sale, currency }` with bare numbers | **unchanged** — bare numbers, `null` when unparsable | 0.1.0 shipped `{ raw, value }`; 0.2.0 returned to the sketch's shape and moved the raw string into the issue's `detail` (§4.5, Q5) |
| `features`: `bedrooms`, `bathrooms`, `garage`, `floors` as "number or `null`" | **unchanged** — and `land_area`, `built_area`, `year_built` follow the same rule rather than keeping raw text | the raw text is still reachable, in the issue that explains why the number is missing (§4.5, Q5) |
| `operation`, `property_type`, … "arrays of `{ key, label }`" | unchanged in shape, with an explicit 7-step normalizer and `label: null` for unmapped tokens | the source mixes keys, labels, folded labels, slugs, pipe-joined strings and literal `false` (§4.2) |
| `coordinates: { lat, lng, source }` | same, plus `null` for the 107 geocode-error markers and a `coordinates_stale` issue | measured in `docs/schema.md` §5 |
| `origin: { source, wpuf_form_id }` | same, with `source: "unknown"` for 540 properties | no JetFormBuilder marker exists (§4.10, Q6) |
| `pagination: { per_page, after_id, next_after_id, has_more }` | plus **`max_id`** | gives the consumer a cheap snapshot boundary for a multi-hour walk; it costs nothing (same aggregate query as `totals`) and only adds a field (§5.2) |
| `associate_id` "from a real source field" | user meta `numero-de-asociado` | the only real source; flagged as Q4 because it is user meta |
| `marketplace_id` "from a real source field, else `null`" | always `null` | no such field exists anywhere in the source (§4.10) |
| `issues`: "machine-readable codes" | the fixed 22-code list in §4.11, each `{ code, field, detail }` | needed a concrete, stable enumeration to be consumable |
