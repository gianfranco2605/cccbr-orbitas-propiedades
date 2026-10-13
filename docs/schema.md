# Source schema — `cccbr-propiedades`

Everything in this document was measured on the **local replica** (production dump of
13 Oct 2026, table prefix `wp_`) on **2026-10-13** with read-only aggregate queries
(`wp db query … --skip-plugins --skip-themes`). No value of any personal field was read or printed:
only key names, counts, shapes and non-personal option/glossary labels.

Facts are marked **VERIFIED** (measured here) or **TO VERIFY** (deferred to a later task).
Where a measurement contradicts `LOOP_PROMPT.md` §4, the real number is used and the difference is
listed in [§10](#10-differences-from-loop_promptmd-4).

---

## 1. The post type

| Fact | Value | Evidence |
|---|---|---|
| Post type | `cccbr-propiedades` | **VERIFIED** |
| JetEngine definition | `wp_jet_post_types` row **id 10**, `status = publish`, name "CCCBR Propiedades" | **VERIFIED** |
| `meta_fields` of that row | `a:0:{}` (6 bytes) — empty, fields live in a meta box instead | **VERIFIED** |
| Total posts | **3055** | **VERIFIED** |
| ID range | `80187` … `184589` (ascending keyset pagination is safe) | **VERIFIED** |
| Creation dates (GMT) | `2024-01-02 01:47:15` … `2026-10-13 03:23:05` | **VERIFIED** |
| Zero/invalid dates | none (`post_date`, `post_date_gmt`, `post_modified_gmt` all non-zero) | **VERIFIED** |
| Empty `post_name` | none | **VERIFIED** |

### 1.1 Statuses present

| Status | Posts |
|---|---|
| `publish` | 2612 |
| `trash` | 383 |
| `draft` | 60 |
| **Total** | **3055** |

**VERIFIED.** No `pending`, `private`, `future`, `auto-draft` or `inherit` post exists for this CPT
today. The endpoint still accepts and documents the full status list from the decisions table so the
contract does not change if one appears later; `auto-draft` and `inherit` stay excluded by design
(`auto-draft` is an unsaved editor placeholder, `inherit` belongs to revisions and attachments).

### 1.2 Trashed posts

| Fact | Value |
|---|---|
| `_wp_trash_meta_status`, `_wp_trash_meta_time`, `_wp_desired_post_slug` | **383 posts each — exactly the trashed set** (**VERIFIED**) |
| Trashed posts whose `post_name` still carries the `__trashed` suffix | 373 of 383 (**VERIFIED**) |

So `historical_slug` must come from `_wp_desired_post_slug` (present for all 383), not from
stripping the suffix: 10 trashed posts have no suffix to strip.

### 1.3 Related post types (context, not exposed)

| Post type | Posts | Why it matters |
|---|---|---|
| `leads-cccbr` | 50 (49 `publish`, 1 `private`) | child side of relation 171 — only **counted** |
| `comentarios-de-leads` | 16 (`publish`) | child side of relation 175 — **not used** |
| `propiedades-correos` | 9 (8 `publish`, 1 `draft`) | **shares the same meta box** as the properties CPT, but is a different post type and is **out of scope** |

---

## 2. Custom fields — JetEngine meta box "Campos propiedades"

Stored in option `jet_engine_meta_boxes`, key **`meta-5`**, name **"Campos Propiedades"**,
`object_type = post`, `allowed_post_type = ["cccbr-propiedades", "propiedades-correos"]`,
**29 fields** (`LOOP_PROMPT.md` §4 says 28 — the real count is 29). **VERIFIED**

`posts` = number of distinct properties that have a row for the key (3055 = every property).
A key present on all 3055 rows means **"empty vs missing" must be decided on the value**, not on the
key's presence.

| Field | JetEngine type | Options source | posts | Exposed as |
|---|---|---|---|---|
| `nombre-de-propiedad` | text | manual | 5 | `issues` only — the real name is `post_title` |
| `tipo-de-propiedad` | select | **glossary 12** | 3055 | `property_type` |
| `estatus-propiedad` | checkbox (`is_array`) | **glossary 15** | 3055 | `operation` |
| `opciondecompracondiciones` | wysiwyg | manual | 5 | `purchase_option_terms` |
| `numero-de-finca` | text | manual | 3055 | `registry_number` |
| `uso-de-suelo` | checkbox (`is_array`) | manual (4 options) | 3055 | `land_use` |
| `exclusividad` | checkbox (`is_array`) | manual (2 options) | 2517 | `exclusivity` |
| `foto-del-contrato` | media | manual | 1089 | `private_documents.has_contract_photo` (boolean only) |
| `moneda` | select | manual (`Colones`, `Dólares`) | 11 | `price.currency` |
| `precio-de-venta` | number | manual | 3055 | `price.sale` |
| `precio-de-alquiler` | number | manual | 3055 | `price.rent` |
| `habitaciones` | number | manual | 3055 | `features.bedrooms` |
| `banos` | number | manual | 3055 | `features.bathrooms` |
| `garage` | number | manual | 3055 | `features.garage` |
| `terreno` | text | manual | 3055 | `features.land_area` |
| `construccion` | text | manual | 3055 | `features.built_area` |
| `plantas` | number | manual | 3055 | `features.floors` |
| `ano-de-construccion` | text | manual | 3055 | `features.year_built` |
| `mapa` | text | manual | 3055 | `map_raw` (+ source of the geocode cache, §5) |
| `imagen-principal` | media (`value_format: both`) | manual | 5 | `media.featured` fallback |
| `imagenes` | gallery (`value_format: id`) | manual | 3030 | `media.gallery` |
| `amenidades` | checkbox (`is_array`) | manual, `options: []`, stale `glossary_id: 11` | 5 | `amenities` |
| `video_propiedad` | text | manual | 3055 | `video_url` |
| `propiedad_vendida` | checkbox (`is_array`) | manual (1 option) | 1268 | `sold` |
| `precio-final-de-venta` | **text** | manual | 1032 | `price.final_sale` |
| `descripcion-en-ingles` | wysiwyg | manual | 2739 | `description_en` |
| `documentos-anexos` | media (`value_format: both`) | manual | 5 | `private_documents.has_attachments` (boolean only) |
| `corredor_asignado` | select | **query 132** | 5 | `responsible` |
| `compartir_propiedad` | checkbox | manual (1 option) | 1630 | `share` |

### 2.1 Glossaries (`wp_jet_post_types`, `status = 'glossary'`, 95 rows)

Entries are stored in the `meta_fields` column as a serialized array of
`{ value, label, is_checked }`. **VERIFIED**

| Glossary | Name | Entries | Keys vs labels |
|---|---|---|---|
| **12** | Tipo de propiedad | 15 | `value === label` for all 15 (`Casa independiente`, `Casa en condominio`, `Apartamento`, `Condominio`, `Lote residencial`, `Lote comercial`, `Lote industrial`, `Edificio`, `Oficina`, `Local comercial`, `Finca agrícola`, `Finca de recreo`, `Quinta`, `Bodega`, `Proyecto`) |
| **15** | Estatus de propiedad | 3 | `Alquiler` / `Venta` have `value === label`; the third is **`opciondecompra` → "Alquiler con opción de compra"** — key ≠ label |
| **11** | Amenidades | 14 | `value === label` (`Piscina`, `Casa Club`, `Gimnasio`, `Acceso controlado`, `Areas infantiles`, `Cancha multiuso`, `Área de coworking`, `Seguridad 24/7`, `Senderos`, `Electrificación Subterránea`, `Fibra óptica`, `Calles de concreto`, `Calles adoquinadas`, `Ciclovía`) |

`LOOP_PROMPT.md` §4 reads "glossary 12 / 15 / 11" as a **glossary id**, not an entry count — the
real entry counts are 15, 3 and 14. The `amenidades` field has `options_from_glossary: ""` (falsy)
and an empty `options` array, so **glossary 11 is not actually wired to the field**: stored values
cannot be resolved through it with certainty. All 5 stored `amenidades` values are empty anyway
(§2.3), so the practical effect today is nil.

### 2.2 How checkbox / select values are really stored — **the main finding of Task 1**

`LOOP_PROMPT.md` §4 states "checkbox values are serialized PHP arrays". **That is only one of six
shapes present in the data.** Measured distinct shapes:

| Shape | Example (real, non-personal) | Note |
|---|---|---|
| Serialized PHP array | `a:1:{i:0;s:5:"Venta";}` | the documented shape |
| Plain scalar string | `Venta` | 1190 rows for `estatus-propiedad` alone |
| **Pipe-joined string** | `Alquiler \| Venta`, `Residencial \| Comercial \| agrícola` | a *single* meta value holding several selections |
| **Pipe-joined inside a serialized array** | `a:1:{i:0;s:23:"Residencial \| agrícola";}` | both shapes nested |
| Literal `false` tokens | `false \| false \| false`, `a:1:{s:19:"compartir_propiedad";s:5:"false";}` | must be dropped, not exposed |
| Empty / absent | `''`, `a:0:{}`, `NULL`, or no row at all | four different "nothing" |

And the **tokens themselves mix option keys, option labels and free text**:

- `uso-de-suelo` option keys are `usoresidencial`, `usocomercial`, `usoindustrial`, `usoagricola`
  with labels `Residencial`, `Comercial`, `Industrial`, `Agrícola`. The data contains the keys
  (400+ rows), the exact labels (1591 rows), **lowercased variants** that match neither
  (`agrícola`, `comercial`, `industrial`), and junk (`-`, `uso mixto`).
- `estatus-propiedad` contains the glossary keys (`Venta`, `Alquiler`, `opciondecompra`) **and**
  `alquiler_con_opción_de_compra`, which is in neither the glossary value nor its label.
- `tipo-de-propiedad` (3055 non-empty values, **18** distinct): 13 exact glossary labels plus
  `finca agrícola` (84), `lote_residencial_/_comercial` (59, **no glossary counterpart**),
  `local_comercial` (55), `proyecto` (16) and `-1` (1, a select placeholder).

**Consequence for the mapper (decided here, implemented in Task 6):** one tolerant normalizer for
every select/checkbox field —

1. `maybe_unserialize`, then flatten arrays; treat `NULL` like a missing value.
2. Split every resulting scalar on `|` (surrounding whitespace trimmed).
3. Drop empty tokens, the literal strings `false` and `true`, `-` and `-1`.
4. Resolve each token against the field's option set: exact key → exact label → case/accent-folded
   label → slugified label (`local_comercial` style). First match wins.
5. Unresolvable token → `{ "key": "<raw token>", "label": null }` plus an `issues` code. **Never
   guess a label and never drop the raw token.**

### 2.3 Enumerated fields: measured value distribution

| Field | Measured |
|---|---|
| `estatus-propiedad` | 13 distinct raw values. As selection tokens: `Venta` **2595**, `Alquiler` **508**, `opciondecompra` **10**, `alquiler_con_opción_de_compra` **14**; 6 empty; 1 row of `false \| false \| false` |
| `uso-de-suelo` | **42** distinct raw values; `Residencial`/`usoresidencial` dominate; 178 empty; junk values `-`, `uso mixto` |
| `exclusividad` | `completa` and `solopublicacion` in key, label and pipe-joined form; **1230 empty**, 3 `a:0:{}` |
| `propiedad_vendida` | `vendida` on **86** properties (78 scalar + 8 serialized); 842 empty, 340 `a:0:{}` |
| `compartir_propiedad` | `compartir_propiedad` on **954**; 672 empty; 4 rows storing the string `false` |
| `moneda` | **`Colones` 7, `Dólares` 2, empty 2** → only **9 of 3055** properties carry a currency |
| `amenidades` | all 5 rows empty → **no property has amenities data** |
| `corredor_asignado` | all 5 rows empty → **no property has a responsible associate** |

### 2.4 Numeric fields: nothing may be cast blindly

`LOOP_PROMPT.md` §4 flags only `terreno`, `construccion`, `ano-de-construccion` and
`precio-final-de-venta` as text that "may hold non-numeric values". **Measured: every one of the ten
numeric-ish fields contains non-numeric values, including the four `number`-typed ones.**

| Field | Rows | Empty string | Plain numeric | **Non-numeric** |
|---|---|---|---|---|
| `precio-de-venta` | 3055 | 521 | 2466 | **68** |
| `terreno` | 3055 | 431 | 2561 | **63** |
| `construccion` | 3055 | 930 | 2104 | **21** |
| `precio-de-alquiler` | 3055 | 2539 | 510 | **6** |
| `ano-de-construccion` | 3055 | 2021 | 1031 | **3** |
| `precio-final-de-venta` | 1032 | 1018 | 12 | **2** |
| `garage` | 3055 | 1105 | 1948 | **2** |
| `habitaciones` | 3055 | 1090 | 1963 | **2** |
| `plantas` | 3055 | 1451 | 1602 | **2** |
| `banos` | 3055 | 965 | 2088 | **2** |

Shapes of the non-numeric values (classified by pattern, values never printed):

| Shape | Where it appears |
|---|---|
| thousands separated by **dot** (`150.000`) | `precio-de-venta` 41, `terreno` 5, `precio-de-alquiler` 1, `construccion` 1 |
| thousands separated by **comma** (`150,000`) | `terreno` 29, `precio-de-venta` 5, `construccion` 1, `precio-final-de-venta` 1 |
| digits with units or symbols (`350 m2`, `$120k`) | 44 rows across 9 fields |
| no digits at all | 12 rows across 8 fields |
| other mixed (ranges, two numbers, notes) | 30 rows, mostly `construccion` and `terreno` |

**Consequence (decided here, implemented in Task 6):** every numeric-ish field is exposed as
`{ "raw": "<stored string>", "value": <number|null> }`. A number is produced **only** for
`^-?\d+$` and for `^-?\d+\.\d{1,2}$`; `150.000` and `150,000` are *ambiguous* (thousands separator
vs decimal) and yield `value: null` with the `ambiguous_number` issue code, the raw string always
preserved. This widens the contract's "number or `null`" for four feature fields — justified
because the measurement shows those fields need the same treatment as the text ones, and because
dropping the raw string would lose data Orbitas cannot recover.

---

## 3. Every other meta key on the CPT, with its decision

Complete list (`wp_postmeta` joined to the CPT, grouped by `meta_key`). `posts` = distinct
properties carrying the key. Nothing on the CPT is omitted from this table.

### 3.1 Included (mapped to the contract)

| Key | posts | Exposed as | Note |
|---|---|---|---|
| `_thumbnail_id` | 2998 | `media.featured` | 2994 integer ids (all pointing at a real `attachment`, **VERIFIED**), **4 rows with a `NULL` value** |
| `_jet_pep_period` | 541 | `expiration.at_gmt` | Unix timestamps, all numeric; range `2026-12-22 14:54:57` … `2027-04-11 03:23:05` UTC |
| `_jet_pep_action` | 541 | `expiration.action` | single value `draft` for all 541 |
| `4437ad177c1679b1090c78835bb6c221_lat` | 498 | `coordinates.lat` | see §5 |
| `4437ad177c1679b1090c78835bb6c221_lng` | 498 | `coordinates.lng` | see §5 |
| `4437ad177c1679b1090c78835bb6c221_hash` | 498 | staleness check only | see §5 |
| `_wp_desired_post_slug` | 383 | `historical_slug` | trashed posts only |
| `_wp_trash_meta_status` | 383 | `status_before_trash` | trashed posts only |
| `_wp_trash_meta_time` | 383 | `trashed_at_gmt` | Unix timestamp |
| `_wpuf_form_id` | 2515 | `origin.wpuf_form_id` | 2512 rows = form **9536** (`wpuf_forms` post "PublicarPropiedades"), 3 empty |
| the 29 meta-box fields of §2 | — | see §2 | — |

### 3.2 Excluded, with the reason

| Key | posts | Reason for exclusion |
|---|---|---|
| `_eael_post_view_count` | 3055 | Essential Addons for Elementor view counter — theme/plugin analytics, not property data |
| `astra_style_timestamp_css` | 3055 | Astra theme CSS cache-busting timestamp |
| `_elementor_page_assets` | 2646 | Elementor asset registry for rendering |
| `wpuf_post_status` | 2438 | WP User Frontend internal workflow state (decisions table: "internal WPUF workflow meta") |
| `wpuf_ai_post_review` | 969 | WPUF AI moderation payload — internal workflow |
| `wpuf_ai_review_status` | 969 | WPUF AI moderation verdict — internal workflow |
| `jet_tax__ubicacion-propiedad` | 707 | **Raw JetFormBuilder submission artifact.** The key is the form-field convention `jet_tax__<taxonomy>` consumed by JetFormBuilder's *Insert/Update Post* action (verified in `jetformbuilder/modules/actions-v2/insert-post/properties/post-terms-property.php`). All **704** numeric values are **already assigned as real terms** (**VERIFIED**), and the real term relationships cover 1992 properties against 707 here, so the authoritative source is the taxonomy (§4) |
| `jet_engine_store_count_favoritos` | 78 | JetEngine "favourites" data-store counter — site engagement, explicitly excluded by the decisions table |
| `_edit_last` | 35 | WordPress editor lock metadata |
| `_edit_lock` | 32 | WordPress editor lock metadata |
| `_oembed_<hash>` | 73 (52 distinct keys, 103 rows) | WordPress oEmbed HTML cache |
| `_oembed_time_<hash>` | 5 (5 distinct keys, 7 rows) | WordPress oEmbed cache timestamps |
| `_wp_page_template` | 10 | Theme template selection |
| `_wpuf_lock_editing_post` | 5 | WPUF internal workflow |
| `_wpuf_res_display` | 5 | WPUF internal workflow |
| `_yoast_wpseo_estimated-reading-time-minutes` | 5 | Yoast SEO derived value |
| `_yoast_wpseo_primary_ubicacion-propiedad` | 5 | Yoast SEO primary-term helper |
| `_yoast_wpseo_content_score` | 4 | Yoast SEO score |
| `_yoast_wpseo_meta_description_score` | 4 | Yoast SEO score |
| `_yoast_wpseo_seo_title_score` | 4 | Yoast SEO score |
| `adv-header-id-meta` | 5 | Astra advanced header |
| `ast-below-header-display` | 5 | Astra layout toggle |
| `ast-hfb-above-header-display` | 5 | Astra layout toggle |
| `ast-hfb-below-header-display` | 5 | Astra layout toggle |
| `ast-hfb-mobile-header-display` | 5 | Astra layout toggle |
| `ast-site-content-layout` | 5 | Astra layout |
| `astra-migrate-meta-layouts` | 5 | Astra migration flag |
| `header-below-stick-meta` | 5 | Astra sticky header |
| `site-content-layout` | 5 | Astra layout |
| `site-content-style` | 5 | Astra layout |
| `site-sidebar-layout` | 5 | Astra layout |
| `site-sidebar-style` | 5 | Astra layout |
| `stick-header-meta` | 5 | Astra sticky header |
| `theme-transparent-header-meta` | 5 | Astra transparent header |
| `foto-del-contrato`, `documentos-anexos` | 1089 / 5 | **Private documents.** Exposed only as the booleans `private_documents.has_contract_photo` / `has_attachments` per the decisions table |
| `nombre-de-propiedad` | 5 | Superseded by `post_title`; a value that differs from the title raises an `issues` code instead of a field |

### 3.3 `NULL` meta values — a trap worth naming

`wp_postmeta.meta_value` is **nullable** and three keys use it:

| Key | Rows with `meta_value IS NULL` | of total rows |
|---|---|---|
| `foto-del-contrato` | **953** | 1089 |
| `imagenes` | **13** | 3030 |
| `_thumbnail_id` | **4** | 2998 |

**VERIFIED.** So `has_contract_photo` is true for only **133** properties (130 integer ids +
3 serialized values), not 1089, and the gallery/featured mappers must treat `null` as "no value".
`maybe_unserialize( null )` returns `null`, not an array: every mapper guards against it.

---

## 4. Taxonomy

| Fact | Value |
|---|---|
| Taxonomies used by the CPT | **only `ubicacion-propiedad`** (**VERIFIED** — one `GROUP BY taxonomy` over the CPT's term relationships) |
| Term relationships | 5908 |
| Properties with at least one term | **1992 of 3055** → **1063 properties have no location at all** (reported, never inferred) |
| Terms | 577 total: **7 roots / 82 level 2 / 488 level 3** — exactly 3 levels (**VERIFIED**) |
| Level semantics | The 7 roots are `Alajuela`, `Cartago`, `Guanacaste`, `Heredia`, `Limón`, `Puntarenas`, `San José` → the 7 **provinces** of Costa Rica, so level 2 = *cantón* and level 3 = *distrito* (**VERIFIED** by name, public geography) |

`location` therefore returns every assigned term with its full ancestor path and a `level`
(1 = province, 2 = cantón, 3 = district), or `[]` plus an `issues` code.

---

## 5. Coordinates — origin **VERIFIED**

The meta prefix `4437ad177c1679b1090c78835bb6c221` is **`md5( 'mapa' )`** (computed and compared).
JetEngine's Maps Listings module builds its storage keys as
`md5( <field name> ) . '_lat' | '_lng' | '_hash'`:

- `jet-engine/includes/modules/maps-listings/inc/map-field.php:242` —
  `get_field_prefix()` returns `apply_filters( 'jet-engine/maps-listings/map-field-prefix', md5( $field_name ), $field_name )`
- `…/inc/sources/posts.php:28` — writes `$hash . '_hash'`, `$hash . '_lat'`, `$hash . '_lng'` via `update_post_meta()`
- `…/inc/lat-lng.php:447` — the stored `_hash` is `md5( $location )`, the geocoded address string

So these three keys are the **JetEngine geocoding cache for the `mapa` text field**, which settles
the "origin not verified" note in `LOOP_PROMPT.md` §4. `coordinates.source` is reported as
`jetengine_geocode:mapa`.

Two measured hazards:

| Measurement | Value | Handling |
|---|---|---|
| `…_lat` holding a **geocoding error marker** (`je-coord-error…`, prefix from `lat-lng.php:12`) | **107 of 498** | `coordinates: null` + `coordinates_geocode_error` issue. Never exposed as a value |
| `…_lat` holding a plain number | 391 of 498 | validated like JetEngine does: numeric and \|lat\| ≤ 90, \|lng\| ≤ 180 |
| `…_hash` **≠** `md5( mapa )` → cache is stale for the current address | **7 of 498** | coordinates still returned, plus a `coordinates_stale` issue |

---

## 6. Media

| Fact | Value |
|---|---|
| `imagenes` (gallery, `value_format: id`) | 3030 properties. **Three storage shapes**: serialized array 2092, **comma-separated id list 899**, single integer 26, other 13, `NULL` 13 (**VERIFIED**) — `LOOP_PROMPT.md` §4 documents only the serialized form |
| `_thumbnail_id` | 2998 rows; 2994 integer ids, **all pointing at an existing `attachment` post** (**VERIFIED**); 4 `NULL` |
| `imagen-principal` (`value_format: both`) | 5 rows: 4 empty, 1 serialized array. Used only as a fallback when `_thumbnail_id` is absent |
| `foto-del-contrato` | 1089 rows: 953 `NULL`, 130 integer id, 3 serialized, 3 empty → `has_contract_photo` true for **133** |
| `documentos-anexos` | 5 rows, **all empty** → `has_attachments` is false for every property today |
| Gallery id integrity (do all gallery ids resolve to real attachments? any broken file references?) | **TO VERIFY — Task 8.** Parsing the three shapes belongs in the media mapper, not in a SQL aggregate |
| Media **file existence** | **Cannot be verified on the replica**: `wp-content/uploads` is not local and every request 302-redirects to production, which is off limits. Implemented, documented as unverifiable (Q3 in `docs/OPEN-QUESTIONS.md`) |

---

## 7. Relations (JetEngine, `wp_jet_post_types` `status = 'relation'`, 3 rows)

| id | Name | Parent → child | Type | Storage | Measured |
|---|---|---|---|---|---|
| **131** | Relación Empresa Agentes | `mix::users` → `mix::users` | one_to_many | `db_table: true` → **`wp_jet_rel_131`** | 14 rows, all `rel_id = 131`; **2** distinct parents, **14** distinct children |
| **171** | Propiedad Leads | `posts::cccbr-propiedades` → `posts::leads-cccbr` | one_to_many | `db_table: true` → **`wp_jet_rel_171`** (**VERIFIED** — resolves the "storage table not verified" note) | 25 rows, all `rel_id = 171`; **21** properties have leads, 25 distinct leads |
| 175 | Leads y comentarios | `posts::leads-cccbr` → `posts::comentarios-de-leads` | one_to_many | `db_table: false` → shared `wp_jet_rel_default` (16 rows, `rel_id = 175`) | **not used** |

Relation table columns: `_ID`, `created`, `rel_id`, `parent_rel`, `parent_object_id`,
`child_object_id`. Queries always filter on `rel_id` as well as the object id, even on a dedicated
table, because the column exists and is populated.

Measured integrity of relation 131 (company → associate):

| Check | Result |
|---|---|
| Children belonging to more than one parent | **0** → the company lookup is unambiguous (**VERIFIED**) |
| Parent roles | both parents have role `empresa_activa` |
| Child roles | 11 `um_asociado-activo-cccbr`, 1 `um_asociado-inactivo-cccbr` |
| Children that **no longer exist** in `wp_users` | **2 of 14** → stale relation rows; every user lookup must tolerate a missing user (`LOOP_PROMPT.md` §4 says 12 children; the table holds 14, 12 of which still exist) |
| Property authors that are a child in relation 131 | **1 of 250** → **448 properties get a `company`**, the other 2607 get `company: null` |
| Leads | `leads_count` is `COUNT(*)` on `wp_jet_rel_171` by `parent_object_id`; 21 properties > 0, the rest 0. No lead content is exposed |

---

## 8. People

| Fact | Value |
|---|---|
| Distinct property authors | **250** (**VERIFIED**) |
| `post_author = 0` | **0** |
| Authors missing from `wp_users` | **0** → `creator` is non-null for every property today, though the code still handles `null` |
| `corredor_asignado` (responsible associate) | present on 5 properties, **all values empty** → `responsible` is `null` for every property today. Query **132** ("ChildUsers de empresa") confirms the field is meant to hold a **user id** of a sub-agent: a users query on role `um_asociado-activo-cccbr`, dynamically restricted to `%rel_get_items\|131\|child_object\|queried_user\|%` — i.e. the children of the current user in relation 131 |
| Exposed user fields | **only** `id`, `display_name`, `email` (decisions table) |

### 8.1 Associate number — **Q2 answered**

User meta key **`numero-de-asociado`**, present on **1623 users** (**VERIFIED**, key names and
counts only). It is the CCCBR associate/broker number, defined in the JetEngine user meta box
"CorredorUserBOX" (`meta-3`) as a `text` field. Exposed as `associate_id`.

This is the one user meta key the response reads beyond id/display name/email. It is deliberately
allow-listed because the ticket requires "the link to the associate ID" for mapping; it is an
organisational identifier, not contact or identity data. Flagged for confirmation as Q4 in
`docs/OPEN-QUESTIONS.md`.

### 8.2 Marketplace / Orbitas ID — **Q2 answered: it does not exist**

A key-name search over **all** of `wp_usermeta` and `wp_postmeta` for `%market%`, `%orbit%`,
`%azure%`, `%crm%`, `%extern%` returned **no match** (**VERIFIED**). The only third-party
identifiers present belong to the Wasi CRM (`wasi` 1481 users, `wasi_company` 766, `wasi_token`
766). They are **not** a Marketplace id, and `wasi_token` is a **credential**.

→ `marketplace_id` is **always `null`**, with a documented reason and an `issues` code. Nothing is
inferred from `wasi`.

### 8.3 User meta that must never be exposed

Beyond the blanket rule, these keys exist on this site and are named here so the allow-list is
unambiguous: `wasi_token`, `session_tokens`, `wp_capabilities`, `wp_user_level`,
`default_password_nag`, `password_rst_attempts`, `um_account_secure_fields`, `cedula_corredor`
(national ID, 1454 users), `fecha-de-nacimiento`, `sexo`, `tel-celular`, `tel-oficina`,
`celular-internacional`, `eael_phone_number`, `phone`, `correo-electronico`,
`correo-electronico_secundario`, `nombreasociado`, `apellidosasociado`, `full_name`, `nombre`,
`apellidos`, `estatus_de_pagos`, `fecha_de_desafiliacion`, `razon_de_dasafiliacion`,
`_um_last_login`, `wfls-last-login`, `wpseo_pronouns` and every other key not explicitly listed in
§8. The response builds people objects from an explicit allow-list, never by iterating user meta.

---

## 9. Origin / audit

| Fact | Value |
|---|---|
| `_wpuf_form_id` | 2515 properties; **2512** carry form id **9536** = `wpuf_forms` post "PublicarPropiedades"; 3 empty |
| Properties **without** `_wpuf_form_id` | **540** — 1 created in 2025, **539 in 2026** |
| JetFormBuilder marker on the post | **none exists.** A key-name search for `%jet_fb%`, `%jfb%` and `%form%` on the CPT returns only `_wpuf_form_id`, and `wp_jet_fb_records` is **empty** (0 rows) (**VERIFIED**) |
| Creation years | 2024: 831, 2025: 724, 2026: 1500 |

→ `origin.source` is `wp_user_frontend` when `_wpuf_form_id` is present, otherwise **`unknown`**
plus an `issues` code. The JetFormBuilder origin mentioned in `LOOP_PROMPT.md` §4 is plausible for
the 539 properties created in 2026 but is **not recorded anywhere in the data**, so it is never
claimed as a fact in the response.

---

## 10. Differences from `LOOP_PROMPT.md` §4

None of these changes a decision in §2.3, so per §1.3 they are recorded here and the measured value
is used.

| § 4 says | Measured | Impact |
|---|---|---|
| meta box has **28** fields | **29** | documentation only |
| "glossary 12 / 15 / 11" (read as counts) | those are glossary **ids**; entry counts are 15 / 3 / 14 | documentation only |
| checkbox values **are** serialized arrays | six shapes incl. pipe-joined strings and literal `false` | **drives the tolerant normalizer** (§2.2) |
| only `terreno`, `construccion`, `ano-de-construccion`, `precio-final-de-venta` may be non-numeric | **all ten** numeric-ish fields contain non-numeric values | **drives `{raw, value}` for all ten** (§2.4) |
| `imagenes` is a serialized array of id strings | serialized array **and** comma-separated list **and** single value **and** `NULL` | **drives the gallery parser** (§6) |
| `_thumbnail_id` on **2993 of 3049** | 2998 rows / 2994 usable ids, of 3055 posts | counts corrected |
| `_jet_pep_*` on **533** | **541** | counts corrected |
| coordinates on **496**, origin unverified | **498**; origin **verified** as `md5('mapa')` JetEngine geocode cache; 107 are error markers, 7 stale | counts corrected, hazards added |
| `_wpuf_form_id` on **2517** | **2515** | counts corrected |
| trash meta on **~378** | **383** = exactly the trashed set | counts corrected |
| taxonomy used by **1983 of 3049** | **1992 of 3055** | counts corrected |
| relation 131 children: **12** | **14 rows**, 12 of which still exist as users | **drives the missing-user guard** (§7) |
| relation 171 storage "not verified" | dedicated table **`wp_jet_rel_171`** | verified |

---

## 11. Still to verify in later tasks

- Gallery id → attachment integrity and broken references (Task 8).
- Media file existence (impossible locally — Q3).
- Per-page query count and timing (Task 10).
- Full-walk count/ID reconciliation against these totals (Task 11): expected **3055** unique ids,
  2612 `publish` / 383 `trash` / 60 `draft`, 250 distinct authors.
