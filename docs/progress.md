# Progress log

Append-only. One entry per task: date, task, files changed, commands run with a short summary of
their output (never personal data), facts marked **VERIFIED** (with evidence) or **TO VERIFY**,
and open points.

---

## 2026-10-13 — Task 0 — Bootstrap

### Session preconditions
- `git status`: only the expected untracked `LOOP_PROMPT.md` and `.claude/` on a branch `main`
  with **no commits yet** → first session, nothing pre-existing to preserve. **VERIFIED**
- The four test variables `CCCBR_API_USER`, `CCCBR_API_APP_PASSWORD`, `CCCBR_API_NOACCESS_USER`,
  `CCCBR_API_NOACCESS_APP_PASSWORD` all exist (checked with `-n` tests only, values never read
  or printed). **VERIFIED** → no blocker before Task 4.
- Replica running: `docker compose … ps` shows `cccbr-replica-db-1` (mariadb:11.4, healthy) and
  `cccbr-replica-wordpress-1` (wordpress:php8.2-apache) with `0.0.0.0:8080->80/tcp`. **VERIFIED**

### Toolchain
- `php -v` → **PHP 8.3.11** (CLI on the Mac, Herd). **VERIFIED**
  - Note: the Mac CLI is 8.3 while production is 8.1.13. Compatibility is therefore enforced by
    PHPCompatibilityWP (`testVersion 8.1-`) and PHPStan (`phpVersion: 80113`), not by the local
    interpreter. `php -l` on the Mac cannot catch 8.2+ syntax used by mistake — `composer cs` can.
- `composer -V` → **Composer 2.7.7**. **VERIFIED**
- `git config user.name` → set (Gianfranco Navas Fernandini). **VERIFIED**

### Files created
- `CLAUDE.md` — durable rules (ticket scope, decisions table, environment, command forms,
  hard limits, engineering standards, documentation map).
- `docs/plan.md` — Tasks 0–14 + acceptance criteria checklist.
- `docs/progress.md` — this file.
- `docs/OPEN-QUESTIONS.md` — Q1 PHPStan level (answered), Q2 associate / Marketplace ID source
  fields (open, Task 1), Q3 media existence checks not verifiable locally (open).
- `README.md`, `CHANGELOG.md` (Keep a Changelog), `.gitignore`.
- `composer.json` — PSR-4 `ElPuas\CCCBR\OrbitasPropiedades\` → `src/`, dev deps per §5.6,
  `config.platform.php = 8.1.13`, scripts `lint` / `cs` / `cbf` / `stan` / `test`.
- `phpcs.xml.dist` — WPCS 3 + `PHPCompatibilityWP` with `testVersion 8.1-`, prefixes
  `cccbr_orbitas` / `CCCBR_ORBITAS`, text domain `cccbr-orbitas-propiedades`,
  `minimum_wp_version 6.9`; `WordPress.Files.FileName` excluded (PSR-4 file names).
- `phpstan.neon.dist` — level **8**, `phpVersion: 80113`, WordPress stubs via
  `szepeviktor/phpstan-wordpress`, `bootstrapFiles: tests/bootstrap.php`.
- `phpunit.xml.dist` + `tests/bootstrap.php` + `tests/unit/PluginHeaderTest.php` (3 tests guarding
  the plugin header fields, the production version targets and header/constant version parity).
- `cccbr-orbitas-propiedades.php` — header (v0.1.0, `Requires at least: 6.9`, `Requires PHP: 8.1`,
  author ElPuas Digital Crafts, GPL-2.0-or-later, text domain), `ABSPATH` guard, constants,
  autoloader include with an admin notice fallback (never fatals). **No bootstrap call yet** —
  Task 3 adds it together with `Plugin`, the activator and `uninstall.php`.
- `src/` created empty (PHPStan needs the path to exist; filled in Task 3).

### Commands run
| Command | Result |
|---|---|
| `composer install` | 38 packages installed; PHPCS `installed_paths` auto-configured by the dealerdirect plugin |
| `composer validate --no-check-publish` | `./composer.json is valid` |
| `composer lint` | `php -l` on every non-vendor PHP file: no syntax errors |
| `composer cs` | 3 files, **0 errors / 0 warnings** |
| `composer stan` | **No errors** (level 8) |
| `composer test` | **OK (3 tests, 8 assertions)** |

### Facts and decisions recorded this task
- **VERIFIED** PHPStan 1.12 at level 8 needs `--memory-limit=1G`: with the default 128 MB the child
  process crashes while loading the WordPress stubs. The limit is baked into the `stan` script.
- **VERIFIED** `phpstan/phpstan` is pinned to `^1.12` (1.12.34) and prints an "old version" notice.
  Upgrading to 2.x would also require `szepeviktor/phpstan-wordpress ^2.0` and a `composer update`,
  which §5.6 excludes ("write them in `composer.json` and run `composer install`"). Staying on 1.12
  is deliberate; the notice is cosmetic.
- **VERIFIED** `composer.json` carries no `version` field (Composer's own `validate` recommends
  omitting it), so every Composer command prints a harmless "could not detect the root package
  version" notice. The plugin version lives in the plugin header and `CCCBR_ORBITAS_VERSION`, kept
  in sync by `PluginHeaderTest`.
- **Not run:** `wp-project-triage`'s `detect_wp_project.mjs`. It needs `node`, which is outside the
  allow-list in `.claude/settings.json`, and running it would raise a permission prompt for no
  information gain: the repository kind (single WordPress plugin, Composer + PSR-4, PHPUnit,
  no Node build step) is fully determined by the files just written. Routing per `wordpress-router`
  is therefore `wp-plugin-development` + `wp-rest-api` + `wp-wpcli-and-ops` + `wp-phpstan`.

### Open points carried into Task 1
- Re-verify every fact of `LOOP_PROMPT.md` §4 against the replica and write `docs/schema.md`.
- Find (key names and counts only) a user meta key for the associate / broker number and any
  Marketplace / Orbitas ID → Q2 in `docs/OPEN-QUESTIONS.md`.
- Resolve glossary storage format, coordinate meta origin, relation 171 storage table, and the
  exact set of statuses present on `cccbr-propiedades`.

---

## 2026-10-13 — Task 1 — Schema discovery

Full result: **`docs/schema.md`** (sections 1–11). This entry records what was run and the facts
that change the implementation.

### Files changed
- `docs/schema.md` (new) — the complete source schema: post type, statuses, all 29 meta-box fields,
  **every** meta key on the CPT with counts and an include/exclude decision, glossaries, taxonomy,
  coordinates, media, relations, people, origin, the §4 differences table, and what is still to verify.
- `docs/OPEN-QUESTIONS.md` — Q2 answered; Q4 (associate id is user meta), Q5 (`{raw, value}`
  numbers), Q6 (unknown origin for 540 properties), Q7 (107 geocode error markers) added.
- `docs/plan.md`, `docs/progress.md` — Task 1 checked and logged.

### Commands run
All read-only aggregates through
`docker compose -f ~/Projects/cccbr-replica/compose.yaml run --rm wpcli wp db query "…" --path=/var/www/html --skip-plugins --skip-themes`,
plus `wp option get jet_engine_meta_boxes --format=json` (written to the session scratchpad, not the
repository), `grep`/`sed` over the read-only JetEngine and JetFormBuilder sources, and one local
`php -r 'md5(...)'`. 24 queries in total: status counts; all meta keys with counts; distinct values
of the 8 enumerated fields; numeric quality and shape classification of the 10 numeric fields; value
shapes of media/expiration/origin keys; `NULL`-value audit; thumbnail→attachment integrity;
taxonomy usage, depth and root names; `information_schema` JetEngine tables; relation definitions,
`rel_id` consistency, cardinality, role and existence checks; author integrity; user meta key names
with counts; `%market%`/`%orbit%`/`%azure%`/`%crm%` key searches; `jet_tax__` redundancy check;
WPUF form id distribution by year; CPT definition row; id/date ranges; expiration range.
**No personal value was read or printed** — only key names, counts, value *shapes* and
non-personal option/glossary labels.

### Facts that change the implementation (all **VERIFIED**, evidence in `docs/schema.md`)
1. **Checkbox/select storage is not uniform.** Six shapes coexist: serialized array, plain scalar,
   **pipe-joined string** (`Alquiler | Venta`), pipe-joined *inside* a serialized array, literal
   `false` tokens, and four kinds of "nothing" (`''`, `a:0:{}`, `NULL`, no row). Tokens mix option
   keys, exact labels, case-folded labels, slugified labels and free text (`lote_residencial_/_comercial`,
   `-1`, `uso mixto`). → one tolerant normalizer with a documented resolution order, raw token always
   preserved, never a guessed label (`docs/schema.md` §2.2).
2. **All ten numeric-ish fields contain non-numeric values**, not just the four text ones
   (`precio-de-venta` 68, `terreno` 63, `construccion` 21, … `banos` 2). `150.000` vs `150,000` is
   genuinely ambiguous. → `{ raw, value }` for all ten, numbers only for `^-?\d+$` and
   `^-?\d+\.\d{1,2}$` (§2.4, Q5).
3. **`wp_postmeta.meta_value` is NULL on three keys**: `foto-del-contrato` 953/1089, `imagenes`
   13/3030, `_thumbnail_id` 4/2998. `has_contract_photo` is therefore true for **133** properties,
   not 1089, and `maybe_unserialize( null )` returns `null` → every mapper guards for it (§3.3).
4. **`imagenes` has three shapes**: serialized array 2092, **comma-separated id list 899**, single
   integer 26, other 13 (§6).
5. **Coordinate origin verified**: `4437ad177c1679b1090c78835bb6c221` = `md5('mapa')`; JetEngine
   Maps Listings writes `md5(field)_lat/_lng/_hash` (`map-field.php:242`, `sources/posts.php:28`,
   `lat-lng.php:447`). **107 of 498** hold the error marker `je-coord-error` (`lat-lng.php:12`) →
   `null` + issue; **7** are stale (`_hash ≠ md5(mapa)`) → value + issue (§5).
6. **Relation 171 is stored in its own table `wp_jet_rel_171`** (25 rows, 21 properties with leads);
   relation 131 in `wp_jet_rel_131` (14 rows, 2 companies, 14 children) with **2 children that no
   longer exist in `wp_users`**, and no child under two parents → unambiguous company lookup, but
   every user lookup must tolerate a missing user (§7).
7. **Only 1 of 250 property authors is a child in relation 131** → 448 properties get a `company`,
   2607 get `null`.
8. **`corredor_asignado` is empty on all 5 rows** and `amenidades` on all 5 → `responsible` and
   `amenities` are empty for every property today; query 132 confirms `corredor_asignado` is meant to
   hold a **user id** (§8).
9. **`moneda` exists on 11 properties and only 9 have a value** (`Colones` 7, `Dólares` 2) →
   `currency: null` for 3046 properties, never inferred (§2.3).
10. **`jet_tax__ubicacion-propiedad` is excluded safely**: it is a raw JetFormBuilder submission
    artifact and all **704** of its term ids are already assigned as real terms (§3.2).
11. **No origin marker for 540 properties** (no JetFormBuilder post meta; `wp_jet_fb_records` empty)
    → `origin.source = "unknown"`, never claimed as JetFormBuilder (§9, Q6).
12. **Associate number found**: user meta `numero-de-asociado` (1623 users). **No Marketplace /
    Orbitas / Azure key exists anywhere** → `marketplace_id` always `null` (§8.1–8.2, Q2/Q4).
13. **Taxonomy**: only `ubicacion-propiedad`; 1992 of 3055 properties have terms → **1063 with no
    location**; 7 roots are the Costa Rican provinces, so levels are province / cantón / distrito (§4).
14. **Statuses present are only `publish` 2612, `trash` 383, `draft` 60** (total 3055). The contract
    still documents the full list. `_wp_desired_post_slug` exists for all 383 trashed posts, while
    only 373 carry the `__trashed` suffix → always use the meta for `historical_slug` (§1.1–1.2).
15. Baselines for Task 11 reconciliation: **3055** posts, ids `80187`–`184589`, **250** authors.

### Discrepancies with `LOOP_PROMPT.md` §4
13 numeric/structural differences, tabulated in `docs/schema.md` §10. None of them changes a
decision in §2.3, so per §1.3 the measured values are used and recorded rather than stopping. The
material ones are items 1, 2, 4 and 6 above.

### TO VERIFY (carried forward)
- Gallery id → attachment integrity, broken references (Task 8).
- Media **file** existence: impossible on the replica (`uploads` 302-redirects to production) — Q3.
- Queries per page and timing (Task 10); full-walk reconciliation (Task 11).

---

## 2026-10-13 — Task 2 — Contract

### Files changed
- `docs/contract.md` (new) — route/auth/permissions, the three request args with their constraints,
  the full collection envelope, the property object field by field (type, nullability, source key),
  the 7-step option normalizer, the `{ raw, value }` number rule, the 22 `issues` codes, pagination
  and the consistency mechanism, the error table, guarantees/non-guarantees, a source→contract map,
  and a table of every change against the `LOOP_PROMPT.md` §6 sketch with its reason.
- `docs/plan.md`, `docs/progress.md` — Task 2 checked and logged.

### Decisions taken in the contract (all traceable to a Task 1 measurement)
1. **`{ raw, value }` for all ten numeric fields** (3 prices + 7 features). Numbers are produced
   only for `^-?\d+$` and `^-?\d+\.\d{1,2}$`; `150.000` and `150,000` are ambiguous →
   `value: null` + `number_ambiguous`, raw always preserved. → Q5.
2. **Classification fields are always arrays of `{ key, label }`**, `property_type` included, with a
   documented resolution order (exact key → exact label → folded label → slugified label) and
   `{ key: "<raw token>", label: null }` + `option_value_unmapped` when nothing matches. No label is
   ever guessed, no raw token dropped.
3. **`pagination.max_id` added** to the envelope — a cheap snapshot boundary for a long walk
   (same aggregate query as `totals`); purely additive.
4. **`status` accepts the six real statuses**, rejects `auto-draft` and `inherit` with 400, and
   defaults to all. Statuses present today: `publish` 2612, `trash` 383, `draft` 60.
5. **`per_page` above 100 is a 400, never a silent clamp**, so a consumer cannot believe it is
   walking in pages of 500.
6. **Error codes are stable and prefixed** (`cccbr_orbitas_invalid_per_page`,
   `…_invalid_after_id`, `…_invalid_status`, `…_not_authenticated`, `…_forbidden`,
   `…_unexpected`); consumers match on `code`, never on the translated `message`.
7. **`issues` is a fixed 22-code enumeration** of `{ code, field, detail }` — `detail` is an id or
   token, never a sentence and never personal data.
8. **Consistency mechanism documented**: keyset on the primary key cannot shift rows across page
   boundaries, `has_more` is computed by reading one row beyond the page (not from `totals`), the
   same cursor returns the same page, and the snapshot recipe is "record `max_id` + `generated_at`
   from the first page, ignore `id > max_id`".
9. **No contract field is ever omitted** — a missing value is `null` / `[]` / `false` /
   `{ raw: "", value: null }` — so Orbitas can rely on a fixed item shape.

### Verification
Documentation-only task: nothing to lint or run. `composer cs`, `stan`, `test` were last green at
Task 0 and no PHP changed. Every count quoted in the contract is copied from `docs/schema.md`,
which records the query that produced it.

### Open points carried into Task 3
- Implement the skeleton (`Plugin`, `Lifecycle\Activator`, `uninstall.php`), activate on the
  replica with `--skip-plugins --skip-themes`, confirm the capability on `administrator` and that
  the logs stay clean.

---

## 2026-10-13 — Task 3 — Skeleton and lifecycle

### Files changed
- `src/Auth/Permissions.php` (new) — `CAPABILITY = 'cccbr_orbitas_read_propiedades'` and the REST
  permission callback: `401 cccbr_orbitas_not_authenticated` when no user is authenticated,
  `403 cccbr_orbitas_forbidden` when the user lacks the capability, `true` otherwise.
- `src/Lifecycle/Activator.php` (new) — adds the capability to the `administrator` role, guarded
  against a missing role and idempotent. No table, option, user, password or rewrite flush.
- `src/Plugin.php` (new) — hook wiring only; currently loads the text domain on `init` (WordPress
  6.7+ warns if a text domain is loaded earlier). The REST controller is wired in Task 4.
- `uninstall.php` (new) — guarded by `WP_UNINSTALL_PLUGIN`; removes the capability from **every**
  role (a site owner may have granted it elsewhere). Uses the autoloaded constant when `vendor/` is
  present and a literal fallback when it is not, so uninstall works without Composer.
- `cccbr-orbitas-propiedades.php` — added `register_activation_hook()` (closure delegating to
  `Activator`) and the single bootstrap call `( new Plugin( __FILE__ ) )->register()`.
- `phpstan.neon.dist` — `uninstall.php` added to the analysed paths.

### Commands run and results
| Command | Result |
|---|---|
| `composer lint` | no syntax errors (7 PHP files) |
| `composer cs` | **7 files, 0 errors / 0 warnings** (one `Generic.Commenting.DocComment.MissingShort` fixed) |
| `composer stan` | **No errors** (level 8, 7 files) |
| `wp plugin status … --skip-plugins --skip-themes` | before: `Inactive`, version 0.1.0, author ElPuas Digital Crafts |
| `wp plugin activate cccbr-orbitas-propiedades … --skip-plugins --skip-themes` | `Success: Activated 1 of 1 plugins.` |
| `wp cap list administrator … --skip-plugins --skip-themes` | contains **`cccbr_orbitas_read_propiedades`** — **VERIFIED** |
| `wp cap list subscriber … --skip-plugins --skip-themes` | **0** matches for `cccbr_orbitas` — **VERIFIED**, so `orbitas-noaccess` cannot read |
| `curl -s -o /dev/null -w` on `/` and `/wp-json/` | **200** and **200** — activation broke nothing |
| `curl -s http://localhost:8080/wp-json/` | namespaces containing "cccbr": `cccbr/v1`, `cccbr` — i.e. **only the old plugin**. `cccbr-orbitas/v1` is absent, as expected before Task 4 |
| `logs wordpress` | **0** occurrences of `cccbr-orbitas-propiedades` in the entire log — no notice, warning or error from this plugin. **VERIFIED** |

### Facts
- **VERIFIED** The activation hook fires under `wp plugin activate --skip-plugins --skip-themes`:
  `activate_plugin()` includes the target plugin file itself, so the capability is granted even
  though every other plugin is skipped (which is what keeps WP-CLI inside its 128 MB limit).
- **VERIFIED** The old plugin `cccbr-api-propiedades` is untouched and still serves `cccbr/v1`.
- The only PHP warning in the container log comes from `astra-addon`
  (`class-astra-ext-header-sections-markup.php:1179`, undefined variable) and predates this work.
  Not ours, not in scope.
- Activation writes exactly two things to the replica: `cccbr-orbitas-propiedades` in
  `active_plugins`, and the capability in the `administrator` role — both reversed by deactivation
  plus uninstall.

### Open points carried into Task 4
- Register `cccbr-orbitas/v1/propiedades` with the permission callback and argument validation,
  then verify 401 / 403 / 400 / 200 with the two test credentials.

---

## 2026-10-13 — Task 4 — Route, permissions and argument validation

### Files changed
- `src/Rest/QueryArgs.php` (new) — immutable, validated view of `per_page`, `after_id` and
  `status`. The rules live here once and are used twice: as the route's argument
  `validate_callback`s and again in `from_request()` at the point of use, so the controller cannot
  receive an out-of-range value even if a filter stripped the route's `args`. `per_page` above 100
  is rejected, never clamped; `status` accepts a single value, a comma-separated list and a repeated
  parameter; `auto-draft` and `inherit` are rejected.
- `src/Rest/PropertySchema.php` (new) — the JSON Schema of the envelope and of one property
  (**38 fields**), with helpers for the repeated `{ raw, value }`, `{ key, label }`, attachment and
  person shapes. Served through the route's `schema` callback.
- `src/Rest/PropertiesController.php` (new) — registers `GET cccbr-orbitas/v1/propiedades` with a
  real `permission_callback`, the three validated args and the schema; returns the correctly shaped
  (still empty) envelope and sets `Cache-Control: no-store`.
- `src/Plugin.php` — wires the controller to `rest_api_init`.
- `cccbr-orbitas-propiedades.php` — composes `Permissions` + `PropertySchema` →
  `PropertiesController` → `Plugin` (constructor injection, no container, no singletons).
- `docs/contract.md` — §1 caching wording, **§6 rewritten** to the verified error envelope, §6.3
  added for schema discovery.

### Commands run and results
| Command | Result |
|---|---|
| `composer lint` | no syntax errors |
| `composer cs` | **0 errors / 0 warnings** (43 alignment and docblock-spacing issues auto-fixed with `composer cbf`) |
| `composer stan` | **No errors** (level 8, 10 files) |
| `GET /propiedades` with no credentials | **401**, `code=cccbr_orbitas_not_authenticated` — **VERIFIED** |
| `GET /propiedades` as `orbitas-noaccess` | **403**, `code=cccbr_orbitas_forbidden` — **VERIFIED** |
| `GET /propiedades` as `orbitas-dev` | **200**, envelope `data: []`, `pagination{per_page:50, after_id:0, next_after_id:null, has_more:false, max_id:null}`, `totals{total:0, by_status:{}}`, `generated_at` UTC — **VERIFIED** |
| `per_page=101`, `0`, `abc`, `50.5` | **400** each, `data.details.per_page.code = cccbr_orbitas_invalid_per_page` — **VERIFIED** |
| `after_id=-1`, `xyz` | **400** each, `data.details.after_id.code = cccbr_orbitas_invalid_after_id` — **VERIFIED** |
| `status=auto-draft`, `inherit`, `publish,bogus` | **400** each, `data.details.status.code = cccbr_orbitas_invalid_status` — **VERIFIED** |
| `per_page=100`, `per_page=1`, `after_id=0`, `status=publish`, `status=publish,draft`, `status[]=publish&status[]=trash`, all three combined | **200** each — **VERIFIED** |
| `OPTIONS /propiedades` | **200**; schema title `cccbr_orbitas_propiedades_collection`, envelope members `data,pagination,totals,generated_at`, **38** property fields, args `per_page,after_id,status` — **VERIFIED** |
| `logs wordpress --tail 200` | **0** entries from this plugin — **VERIFIED** |

### Facts discovered while verifying (and the contract corrections they forced)
- **VERIFIED** WordPress validates route arguments **before** the callback and always wraps the
  failure in its own envelope: the top-level `code` of any argument error is **`rest_invalid_param`**
  (HTTP 400), and the specific code lands in `data.details.<argument>.code`. The contract claimed
  our code would be at the top level; `docs/contract.md` §6 now documents the real shape, split into
  §6.1 (401/403, our code at the top level) and §6.2 (400, nested), with a real response example.
- **VERIFIED** validation runs before sanitization (`has_valid_params()` then `sanitize_params()`),
  so the `absint` sanitizers cannot mask an invalid value.
- **VERIFIED** `WP_REST_Response` headers are merged with WordPress's own REST directives: the
  response carries `no-cache, must-revalidate, max-age=0, no-store, private`. Contract wording fixed.
- **FIXED** `totals.by_status` serialized as `[]` because an empty PHP array is a JSON array. It is
  now cast to an object, so it is `{}` as the contract promises — verified in the live response.
- **Deviation recorded:** `PropertiesController` deliberately does **not** extend
  `WP_REST_Controller`. That base class declares its callbacks with untyped parameters and PHP
  forbids narrowing a parameter type in a subclass, so extending it would mean giving up the strict
  typing required by the project standards; the endpoint also returns a custom envelope rather than
  a plain item list. Everything the base class would have supplied — real permission callback,
  argument validation, published schema — is implemented explicitly. Reason is in the class docblock.

### Open points carried into Task 5
- Repository + keyset pagination over all statuses, the `status` filter, `totals` and `max_id` in
  one aggregate query, then a full walk proving 3055 unique ids with no duplicates.

---

## 2026-10-13 — Task 5 — Repository and keyset pagination

### Files changed
- `src/Pagination/Cursor.php` (new) — pure, WordPress-free: given a "page size plus one" result
  set it yields the page, `next_after_id` and `has_more`. `has_more` comes from the **presence of
  the extra row**, not from a count comparison, so it stays correct while the set changes.
- `src/Repository/PropertyRepository.php` (new) — two prepared `$wpdb` queries:
  `find_page_ids()` (`post_type = %s AND post_status IN (…) AND ID > %d ORDER BY ID ASC LIMIT
  per_page + 1`) and `aggregate()` (one `GROUP BY post_status` returning `COUNT(*)` and `MAX(ID)`,
  which answers `totals.total`, `totals.by_status` and `pagination.max_id` together).
  `prepare_with_statuses()` is the only place in the plugin that assembles SQL text; the variable
  part is a `%s` list built from a **count**, never from input, and every value including the
  statuses is bound by `prepare()`. The sniff and PHPStan exemptions live only there, each with its
  reason.
- `src/Rest/PropertiesController.php` — consumes the repository and cursor; `data` currently holds
  `{ id }` stubs (Task 6 replaces them with mapped objects).
- `cccbr-orbitas-propiedades.php` — the composition root reads the global `$wpdb` once and injects
  it, so no class of this plugin touches a global (comment in the file).
- `tests/unit/CursorTest.php` (new) — 7 tests: empty page, partial page, exactly `per_page` with no
  extra row, extra row dropped while setting `has_more`, `per_page = 1`, a non-positive page size
  clamped, and a simulated full walk asserting every id is visited exactly once in order.

### Why `$wpdb` and not `WP_Query`
`WP_Query` paginates with `LIMIT … OFFSET`, which can skip or repeat rows when the set changes
mid-walk, and it cannot express `ID > :cursor`. Keyset pagination therefore has no WordPress API,
which is exactly the exception `CLAUDE.md` §4.2 allows. Meta, terms, users and attachments will
still be read through WordPress APIs with primed caches (Tasks 6–9).

### Commands run and results
| Command | Result |
|---|---|
| `composer lint` | no syntax errors (13 files) |
| `composer cs` | **0 errors / 0 warnings** |
| `composer stan` | **No errors** (level 8, 13 files) |
| `composer test` | **OK (10 tests, 27 assertions)** |
| `GET ?per_page=3` | ids `80187, 80205, 80310`; `next_after_id=80310`; `has_more=true`; `max_id=184589`; `totals.total=3055`; `by_status={draft:60, publish:2612, trash:383}` — **matches the DB exactly** |
| **Full walk** `per_page=100` | **31 pages, 14 s**, 3055 ids collected, **3055 unique** — **VERIFIED** |
| Walk vs DB id set (`comm` on sorted sets) | `only in DB: 0`, `only in API: 0`, `in both: 3055` — **no omissions, no duplicates, no extras. VERIFIED** |
| Walk ordering | strictly ascending, `first=80187`, `last=184589` — **VERIFIED** |
| `status=publish` / `draft` / `trash` | totals 2612 / 60 / 383 with matching `by_status` and `max_id` 184589 / 180368 / 184171 — **VERIFIED** (2612 + 60 + 383 = 3055) |
| `status=publish,draft` / `draft,trash` | totals 2672 / 443 with both statuses in `by_status` — **VERIFIED** |
| `status=pending` (no matching rows) | **200**, `data: []`, `total: 0`, `by_status: {}` — an empty result is never a 404 — **VERIFIED** |
| Same cursor requested twice | byte-identical 50-id pages — the walk is idempotent and safe to retry — **VERIFIED** |
| `after_id=184589` (past the end) | `data: []`, `next_after_id: null`, `has_more: false`, `total: 3055` — **VERIFIED** |
| `after_id=184170&per_page=100` (last partial page) | 40 items, `next_after_id=184589`, `has_more=false` — **VERIFIED** |
| `logs wordpress --tail 400` after the walk | **0** entries from this plugin — **VERIFIED** |

### Facts
- **VERIFIED** `totals` and `max_id` do not depend on the cursor, so they are identical on every
  page of a walk. That is what makes the snapshot recipe in `docs/contract.md` §5.2 usable.
- **VERIFIED** The response is live, not cached: the two `$wpdb` reads are deliberately uncached
  (documented at each `phpcs:ignore`), because a cached count would make Task 11's reconciliation
  meaningless.
- **Note on inspection, not a defect:** decoding the JSON with `json_decode( …, true )` turns the
  empty `by_status` object into a PHP array, which then re-encodes as `[]`. The raw response is
  `"by_status":{}` — confirmed by reading the response body directly.

### Open points carried into Task 6
- Replace the `{ id }` stubs with the mapped property object: core fields, the tolerant option
  normalizer, the `{ raw, value }` numbers, currency, and the trash fields, with cache priming
  (`update_meta_cache`) so a page of 100 costs no N+1 queries.

---

## 2026-10-13 — Task 6 — Core fields and meta mapping

### Files changed
- `src/Support/IssueCode.php` (new) — the closed set of `issues` codes as constants.
- `src/Support/IssueCollector.php` (new) — per-property issue list, de-duplicating identical triples.
- `src/Support/NumberParser.php` (new, pure) — parses a number **only** for `^-?\d+$` and
  `^-?\d+\.\d{1,2}$`; everything else is `empty`, `ambiguous` or `unparsable`, raw always kept.
- `src/Support/OptionNormalizer.php` (new, pure) — the 7-step normalizer: unserialize/flatten,
  split on `|`, drop junk (`false`, `true`, `-`, `-1`), then resolve by exact key → exact label →
  folded label → slugified label, keeping unmapped tokens verbatim with `label: null`. Accent
  folding is implemented locally (no `remove_accents()`) so the class stays WordPress-free.
- `src/Schema/FieldMap.php` (new) — option sets read from the **live** configuration: the
  `jet_engine_meta_boxes` option plus the JetEngine glossary rows, memoized per request. Uses the
  `%i` identifier placeholder (WordPress 6.2+) so the glossary statement stays a literal string,
  and probes for the JetEngine table first so a deactivated JetEngine yields empty option sets
  instead of a database error inside the JSON.
- `src/Mapper/PropertyMapper.php` (new) — builds the contract object for identity, lifecycle,
  classification, price, features, text fields and `private_documents`. Allow-listed throughout: it
  asks for the keys it knows and never iterates the meta array.
- `src/Repository/PropertyRepository.php` — added `find_posts()`, which loads a page through
  `get_posts()` with the post, meta and term caches primed in bulk. `post_status` is the explicit
  list because `'any'` silently drops `trash`, and `suppress_filters` stays true so no other plugin
  can narrow an export the ticket requires to be complete.
- `src/Rest/PropertiesController.php`, `cccbr-orbitas-propiedades.php` — wiring.
- `tests/unit/NumberParserTest.php`, `tests/unit/OptionNormalizerTest.php` (new) — 39 new tests over
  the storage shapes measured in Task 1.
- `docs/schema.md` §2.4 **corrected** (see below), §10 updated; `docs/OPEN-QUESTIONS.md` Q5 figures
  updated; `docs/contract.md` §4.10/§4.11 updated (21 codes, no `marketplace_id` issue).

### Two defects found by verifying against the live data, not by reading the code

**1. `get_post_meta( $id )` does not unserialize.** With no `$meta_key`, `get_metadata_raw()`
returns the raw meta cache, unlike a single-key read. The first full walk therefore reported
`a:1:{i:0;s:5:"Venta";}` as an *unmapped token* — 3333 unmapped occurrences, `sold` on 426
properties instead of 86. Fixed in `PropertyMapper::first()` with `maybe_unserialize()`, with the
reason in the docblock so it cannot be "simplified" away later. After the fix every aggregate
matches the database exactly (table below).

**2. `docs/schema.md` §2.4 was under-counting.** The Task 1 query used the MySQL literal
`'^-?[0-9]+(\.[0-9]+)?$'`, where `\.` collapses to `.` — "any character" — so `150,000` and
`350 m2` were counted as numeric. Re-measured with `\\.`: **329 ambiguous** and **107 unparsable**
values across the ten fields, not 171 non-numeric. §2.4 now carries the corrected table, a note
about the escaping error, and the parser's own classification.

### Whole-dataset verification: API output vs database

Walked all 31 pages and aggregated the 3055 mapped objects (counts only, no personal values).

| Measure | Database (`docs/schema.md`) | API | |
|---|---|---|---|
| items returned | 3055 | **3055** | ✅ |
| `operation` token `Venta` | 2595 | **2595** | ✅ |
| `operation` token `Alquiler` | 508 | **508** | ✅ |
| `operation` token `opciondecompra` | 10 + 14 variant spellings | **24** | ✅ |
| properties with `sold` | 86 | **86** | ✅ |
| properties with `share` | 954 | **954** | ✅ |
| properties with `exclusivity` | 2517 − 1230 empty − 3 `a:0:{}` = 1284 | **1284** | ✅ |
| `currency` | `Colones` 7, `Dólares` 2 | **7 / 2** | ✅ |
| `has_contract_photo` | 133 | **133** | ✅ |
| `has_attachments` | 0 | **0** | ✅ |
| trashed properties with `historical_slug` | 383 | **383** | ✅ |
| non-empty feature values | 13392 | **13392** | ✅ |
| feature values parsed to a number | 13104 | **13106** | ✅ +2: the parser trims, so two space-padded integers parse that the SQL regex rejected |
| `number_ambiguous` occurrences | 329 | **329** | ✅ |
| `number_unparsable` occurrences | 107 | **105** | ✅ −2: the same two space-padded values |
| `land_use` tokens | keys/labels/folded variants of the four options | `usoresidencial` 2496, `usocomercial` 609, `usoagricola` 246, `usoindustrial` 51 | ✅ all four resolved |
| **unmapped tokens left in the whole dataset** | `lote_residencial_/_comercial` 59, `uso mixto` 1 | **exactly those two, 60 occurrences** | ✅ |
| `option_value_normalized` | — | 367 occurrences (folded spellings) | — |
| `price_currency_missing` | 3055 − 9 with a currency, of those that have an amount | 2840 | — |
| `title_name_mismatch` | — | **0** — the five `nombre-de-propiedad` values never contradict `post_title` | — |
| `property_type` empty | 1 (the `-1` placeholder, dropped as junk) | **1** | ✅ |
| `operation` empty | 6 empty + 1 `false \| false \| false` | **7** | ✅ |

The two genuinely unmapped tokens are reported, never guessed: `lote_residencial_/_comercial` has
no counterpart in glossary 12, and `uso mixto` none among the four `uso-de-suelo` options.

### Commands run
| Command | Result |
|---|---|
| `composer lint` | no syntax errors (21 files) |
| `composer cs` | **0 errors / 0 warnings** |
| `composer stan` | **No errors** (level 8, 21 files) |
| `composer test` | **OK (49 tests, 137 assertions)** |
| Full walk, aggregated twice (before and after the unserialize fix) | table above |
| `logs wordpress --tail 600` | **0** entries from this plugin after 62 page requests — **VERIFIED** |

### Decision recorded
`marketplace_id` no longer carries a per-property issue code. It is `null` on all 3055 records
because the field does not exist in the source, and an issue identical on every record is noise in
the consumer's log rather than information about a property. The absence is documented in
`docs/contract.md` §4.10 and §4.11 instead, and `IssueCode` is down to **21** codes.

### Still placeholders (by task boundary, visible in the response)
`expiration`, `location`, `coordinates` and `origin` (Task 7); `media` (Task 8); `creator`,
`responsible`, `company`, `associate_id`, `leads_count` (Task 9). Each is marked in
`PropertyMapper::map()` with the task that fills it.

---

## 2026-10-13 — Task 7 — Location, coordinates, expiration and origin

### Files changed
- `src/Mapper/CoordinatesMapper.php` (new, pure) — reads the JetEngine geocode cache
  (`md5('mapa')` + `_lat` / `_lng` / `_hash`), refuses to expose a geocoding error marker
  (`je-coord-error…`), validates the pair the way JetEngine does (numeric, |lat| ≤ 90,
  |lng| ≤ 180), and compares the stored hash with `md5( mapa )` to flag a stale cache. Reports
  `source: "jetengine_geocode:mapa"` so nobody mistakes geocoded coordinates for surveyed ones.
- `src/Mapper/LocationMapper.php` (new) — expands each assigned `ubicacion-propiedad` term to its
  full ancestor path with `level` 1 = provincia, 2 = cantón, 3 = distrito, de-duplicated and
  ordered by level then name. The whole taxonomy (577 terms) is read **once per request** into an
  index, so a page of 100 costs one term query rather than an ancestor walk per term; the walk is
  loop-guarded against a corrupt hierarchy.
- `src/Mapper/PropertyMapper.php` — `expiration`, `origin`, `location` and `coordinates` now real.
- `cccbr-orbitas-propiedades.php` — wiring.
- `tests/unit/CoordinatesMapperTest.php` (new) — 10 tests: the md5 prefix, fresh coordinates,
  absent cache, error markers (both fields and one field), out-of-range and non-numeric values,
  half a pair, a stale cache that still returns coordinates, a missing hash that must not claim
  staleness, and valid zero coordinates.
- `docs/schema.md` §5 — the 7 stale rows split by whether they also hold an error marker.

### Whole-dataset verification (31 pages, all 3055 properties)

| Measure | Database | API | |
|---|---|---|---|
| properties with a location | 1992 | **1992** | ✅ |
| properties with **no** location | 1063 | **1063** (`location_missing`) | ✅ |
| location terms emitted | 5908 term relationships exist | **5908** (L1 1992 + L2 1958 + L3 1958) | ✅ the ancestor expansion reproduces the stored relationships exactly |
| properties with the full province/cantón/distrito path | — | 1958 | — |
| properties with only a province | — | 34 | — |
| root terms | the 7 provinces | San José 572, Alajuela 556, Heredia 420, Guanacaste 172, Puntarenas 149, Cartago 73, Limón 50 | ✅ seven, and they sum to 1992 |
| properties with coordinates | 391 numeric of 498 cached | **391** | ✅ |
| `coordinates_geocode_error` | 107 error markers | **107** | ✅ |
| `coordinates_stale` | 7 stale hashes, **5 of which also hold an error marker** | **2** | ✅ verified by query: `stale_total 7 / stale_but_error_marker 5 / stale_with_usable_coords 2` |
| properties with an expiration | 541 | **541** | ✅ |
| `origin.source = wp_user_frontend` | 2512 rows with form 9536 | **2512** | ✅ |
| `origin.source = unknown` | 540 without the key + 3 with an empty value | **543** (`origin_unknown`) | ✅ |

### Facts
- **VERIFIED** The term relationships stored by the site already include the ancestors: expanding
  each assigned term to its full path produces exactly 5908 entries, the number of rows in
  `wp_term_relationships` for this CPT. So the expansion adds no invented data — it only guarantees
  the path is complete for the 34 properties that carry a single term.
- **VERIFIED** `wp_term_taxonomy.count` is lower than the API's per-province totals (e.g. San José
  507 vs 572) because the stored count only includes published posts, while the export covers every
  status. Not a discrepancy.
- A property without coordinates or without an expiration raises **no** issue: absence of an
  optional feature is not a data-quality problem. Only a *broken* value is reported.

### Commands run
| Command | Result |
|---|---|
| `composer lint` | no syntax errors (24 files) |
| `composer cs` | **0 errors / 0 warnings** |
| `composer stan` | **No errors** (level 8, 24 files) |
| `composer test` | **OK (59 tests, 162 assertions)** |
| Full walk + aggregation | table above |

---

## 2026-10-13 — Task 8 — Media

### Files changed
- `src/Support/IdListParser.php` (new, pure) — parses the three `imagenes` shapes (serialized
  array, comma-separated list, single id) and nested combinations, preserves gallery order,
  collapses duplicates, treats `0` as "nothing selected", and returns non-id tokens **separately**
  so they are reported rather than dropped. `first_id()` also reads JetEngine's `both` format
  (`{ id, url }`) used by `imagen-principal`.
- `src/Mapper/MediaMapper.php` (new) — `prime()` loads **every attachment of a whole page in one
  query** before any property is mapped; `map()` resolves `media.featured` (from `_thumbnail_id`,
  falling back to `imagen-principal`) and the ordered `media.gallery`, builds derivative URLs from
  the attachment metadata (no query per size), and reports broken references.
- `src/Mapper/PropertyMapper.php` — gained `prime()`, which the controller calls once per page;
  `media` is now real.
- `src/Rest/PropertiesController.php` — collects the page's meta, primes, then maps.
- `cccbr-orbitas-propiedades.php` — wiring, including the file-check flag.
- `tests/unit/IdListParserTest.php` (new) — 12 tests over every measured shape.
- `docs/schema.md` §3.1, §5, §6, §10, §11 — corrected and extended (below).

### Design decision: the file-existence check is environment-gated
`media_file_missing` is implemented, but it only runs where `wp_get_environment_type()` is not
`local`. On this replica `wp-content/uploads` exists yet holds only 28 plugin-generated files and
every media request 302-redirects to production, so running the check would report **every** file
as missing — a false claim. The plugin cannot distinguish "file deleted" from "library not present
in this environment", so where it cannot know, it says nothing. The flag is a constructor argument,
not an internal `wp_get_environment_type()` call, so both branches are unit-testable and the
decision is visible at the composition root. Recorded against Q3.

### Whole-dataset verification (31 pages, 3055 properties, 25 s)

| Measure | Database | API | |
|---|---|---|---|
| properties with a featured image | 2994 usable `_thumbnail_id` values | **2994** | ✅ |
| properties without one | 3055 − 2994 = 61 | **61** (`media_featured_missing`) | ✅ |
| properties with a non-empty gallery | — | 2968 | — |
| properties with an empty gallery | — | 87 (`media_gallery_empty`) | — |
| gallery images returned | — | **30225** | — |
| **gallery tokens that were not ids** | — | **0** | ✅ this is the empirical proof that the 899 comma-separated rows, the 2092 serialized arrays and the 26 single-id rows hold nothing but ids |
| **broken attachment references** | `SELECT COUNT(*) FROM wp_posts WHERE ID IN (<the 301 ids>)` → **0** | **301 distinct ids, 334 references, 118 properties** — all from `imagenes`, none from `_thumbnail_id` | ✅ genuinely broken, correctly reported and excluded from the gallery |
| attachment objects with no URL | — | **0** | — |
| attachments with no dimensions | — | **12, all `image/heic`** (WordPress produces no metadata for HEIC) | — |
| attachment objects with no derivatives | — | 16 | — |
| mime types returned | — | jpeg 30682, png 2305, webp 201, heic 23, avif 8 | — |
| `media_file_missing` | not verifiable here | **0** — check skipped in a `local` environment, by design | — |

Spot-checked property **184589** against the third-party export Gianfranco had run earlier: same
featured attachment (**184582**) and the same six gallery attachments in the same order, with ids,
mime type, dimensions and derivatives added. The old plugin's output was used only as an external
cross-check; nothing about it changed this contract.

### Correction to `docs/schema.md` §5 — coordinate counts were off by one
The authoritative query (`GROUP BY meta_key` over the three coordinate keys, one row per property,
no duplicates) returns **499** properties with coordinate meta, of which **108** hold a
`je-coord-error…` marker and **391** are numeric — not 498 / 107 as Task 1 recorded. The API's
geocode-error set was diffed id-for-id against the database and matches exactly (`only in API: 0`,
`only in DB: 0`), and 391 + 108 = 499. `docs/schema.md` §3.1, §5 and §10 now carry the corrected
figures, and the Task 7 entry above should be read with **108** rather than 107.

### Commands run
| Command | Result |
|---|---|
| `composer lint` | no syntax errors (26 files) |
| `composer cs` | **0 errors / 0 warnings** (one sniff conflict resolved by moving the environment comment out of the constructor call) |
| `composer stan` | **No errors** (level 8, 26 files) |
| `composer test` | **OK (71 tests, 195 assertions)** |
| Full walk `per_page=100` | 31 pages, **25 s** with media mapped (14 s without, Task 5) |
| Missing-id cross-check | `SELECT COUNT(*) FROM wp_posts WHERE ID IN (301 ids)` → **0** |

### Open points carried into Task 9
- People and relations: creator, responsible, company through relation 131, `associate_id` from
  `numero-de-asociado`, lead count from `wp_jet_rel_171`, and bulk user priming through the same
  `prime()` hook so the page still costs no N+1 queries.

---

## 2026-10-13 — Task 9 — People and relations

### Files changed
- `src/Repository/RelationsRepository.php` (new) — two **bulk** queries over the JetEngine relation
  tables: `company_parents()` on `wp_jet_rel_131` (associate → company) and `lead_counts()` on
  `wp_jet_rel_171` (property → number of leads). `rel_id` is matched explicitly even on a dedicated
  table, the table name is bound with `%i`, the id list is a run of `%d` built from a count, and
  each table's existence is probed once per request so a deactivated JetEngine degrades to "no
  company, zero leads" instead of leaking a database error into the JSON.
- `src/Mapper/PeopleMapper.php` (new) — `creator`, `responsible`, `company`, `associate_id`,
  `marketplace_id`, `leads_count`. The allow-list is the point of the class: a person is reduced to
  **id, display name, email** (company: id and display name only, no email), and the single user
  meta key it reads is `numero-de-asociado`. No user object reaches the serializer and user meta is
  never iterated, so a national id, phone, birth date, payment status or Wasi credential cannot
  leak even if added to the profile later.
- `src/Mapper/PropertyMapper.php` — `prime()` now takes the page's posts as well and delegates to
  both the media and people mappers; the six people fields are real.
- `src/Rest/PropertiesController.php`, `cccbr-orbitas-propiedades.php` — wiring; the `IdListParser`
  instance is shared between the media and people mappers.
- `docs/schema.md` §7, §8.1 — leads nuance and the associate-number figure, both verified below.
- `docs/OPEN-QUESTIONS.md` — Q8 added (observation about the old public endpoint); Q2, Q3 and Q7
  corrected to the verified figures and to the implemented file-check behaviour.

### Bulk loading
`PeopleMapper::prime()` runs **one** relation query for lead counts, **one** for company parents,
and then a single `cache_users()` for authors + responsible users + the companies those relations
revealed — the companies deliberately join the same call rather than triggering a lookup each.
`get_userdata()` and `get_user_meta()` during mapping therefore hit the primed cache.

### Whole-dataset verification (31 pages, 3055 properties, 22 s)

| Measure | Database | API | |
|---|---|---|---|
| distinct creators | 250 | **250** | ✅ |
| properties with no creator | 0 | **0** | ✅ |
| **creator object keys** | — | exactly `id, display_name, email` | ✅ allow-list holds across all 3055 |
| **company object keys** | — | exactly `id, display_name` (no email) | ✅ |
| properties with a company | 448 | **448** | ✅ |
| distinct companies | 1 of the 250 authors is a relation-131 child | **1** | ✅ |
| properties with a responsible | all 5 `corredor_asignado` values are empty | **0** | ✅ |
| properties with `associate_id` | 250 authors, **2 without** `numero-de-asociado`, owning **25** properties | **3030 with / 25 without** (`associate_id_missing` = 25) | ✅ exact |
| `marketplace_id` non-null | no such field exists | **0** | ✅ |
| properties with leads | table has 25 rows over 21 parents, but **1 parent is not a `cccbr-propiedades` post** → 20 properties / 24 leads | **20 properties, 24 leads** (16 with one, 4 with two) | ✅ exact |

### Facts
- **VERIFIED** One of the 25 rows in `wp_jet_rel_171` points at a parent that is not a property
  (`parent_not_a_property_row = 1`), which is why the export reports 20 properties and 24 leads
  rather than the 21/25 the raw table suggests. `docs/schema.md` §7 now says so.
- **VERIFIED** Exactly 2 of the 250 property authors have no `numero-de-asociado`, and they own the
  25 properties that report `associate_id_missing`.
- `responsible` is `null` for every property today and `company` for 2607 of them. Both are real
  absences in the source, not mapping failures, and neither is reported as an issue: the contract
  defines them as nullable.

### Commands run
| Command | Result |
|---|---|
| `composer lint` | no syntax errors (29 files) |
| `composer cs` | **0 errors / 0 warnings** |
| `composer stan` | **No errors** (level 8, 29 files) |
| `composer test` | **OK (71 tests, 195 assertions)** |
| Full walk `per_page=100` | 31 pages, **22 s**, every people figure reconciled above |
| Lead-relation cross-check | `rel_rows 25 / distinct_parents 21 / parent_not_a_property_row 1 / parents_that_are_properties 20` |
| Associate-number cross-check | `authors_total 250 / authors_without_associate_number 2 / properties_affected 25` |

### Open points carried into Task 10
- Count the queries per page and time `per_page=100`, now that every mapper is in place, and record
  the numbers.

---

## 2026-10-13 — Task 10 — Performance

### Files changed
- `tools/bootstrap.php` (new) — shared bootstrap for the CLI tools: loads the Composer autoloader,
  **minimally registers** the property post type and the location taxonomy (JetEngine normally does
  that, and `get_the_terms()` refuses an unregistered taxonomy), composes the controller exactly as
  the main plugin file does, hooks it to `rest_api_init`, signs in as an administrator, and runs a
  request through `rest_do_request()` while counting `$wpdb->num_queries` and wall-clock time.
  Task 11 reuses it.
- `tools/measure-performance.php` (new) — the measurement harness below. With
  `--exec="define('SAVEQUERIES', true);"` it also lists the individual queries of one page.
- `phpcs.xml.dist` — `WordPress.Security.EscapeOutput` excluded for `/tools/*`: those scripts write
  plain text to stdout, where HTML escaping would corrupt the output rather than protect anything.
  `phpstan.neon.dist` — `tools` added to the analysed paths.

Note: these entry scripts carry **no** `declare(strict_types=1)`, because `wp eval-file` strips the
opening tag and evaluates the body, where PHP rejects a strict-types declaration that is no longer
the first statement. Documented in the file header; every class they exercise declares it.

### Queries per page — the N+1 proof
`wp eval-file … --skip-plugins --skip-themes`, so the counts are **this plugin's own queries**,
without the rest of the site's plugins.

| Case | Status | Queries | Seconds | Items | Images returned |
|---|---|---|---|---|---|
| `per_page=100` **cold** (first request of the process) | 200 | **20** | 0.19 | 100 | 861 |
| `per_page=100` warm (same page again) | 200 | **4** | 0.05 | 100 | 861 |
| `per_page=1` | 200 | 6 | 0.004 | 1 | 11 |
| `per_page=10` | 200 | 6 | 0.008 | 10 | 97 |
| `per_page=50` | 200 | 6 | 0.027 | 50 | 439 |
| `per_page=100` (middle of the set) | 200 | **12** | 0.20 | 100 | **1152** |
| `per_page=100` (newest, partial page) | 200 | 13 | 0.05 | 50 | 414 |
| `per_page=100&status=publish` | 200 | **12** | 0.06 | 100 | 840 |
| `per_page=100` past the end | 200 | **2** | 0.002 | 0 | 0 |

**The query count does not grow with the page size**: 1 property costs 6 queries and 100 properties
with 1152 images cost 12. That is the no-N+1 guarantee, measured rather than asserted. Peak memory
for the whole eight-request run was 83 MB.

The four queries of a warm page, listed with `SAVEQUERIES`:

1. `SELECT ID FROM wp_posts WHERE post_type = … AND post_status IN (…) AND ID > … ORDER BY ID ASC LIMIT …` — the keyset page
2. `SELECT post_status, COUNT(*), MAX(ID) … GROUP BY post_status` — totals and `max_id` in one go
3. `SELECT parent_object_id, COUNT(*) FROM wp_jet_rel_171 WHERE rel_id = '171' AND parent_object_id IN (…)` — lead counts for the whole page
4. `SELECT child_object_id, parent_object_id FROM wp_jet_rel_131 WHERE rel_id = '131' AND child_object_id IN (…)` — companies for the whole page

Everything else — posts, post meta, terms, attachments, attachment meta, users, user meta — is
served from caches primed in bulk, which is why those four are all that remain. The extra queries
on a cold request are the bulk priming itself (posts, meta, terms, attachments + their meta, users
+ their meta), the JetEngine configuration reads (option, two glossaries) and three
`SHOW TABLES LIKE` probes, each memoized for the request.

### End-to-end HTTP timing (whole site loaded, three samples each)

| Request | Times | Payload |
|---|---|---|
| `/wp-json/` index (baseline: WordPress + every plugin, no property work) | 0.32 – 0.53 s | — |
| our route, past the end (auth + 2 queries, zero items) | 0.28 – 0.37 s | — |
| `per_page=1` | 0.62 – 2.11 s | 8.5 KB |
| `per_page=10` | 0.53 – 1.13 s | 81 KB |
| `per_page=50` | 0.49 – 0.60 s | 452 KB |
| `per_page=100` | **0.45 – 0.54 s** | **937 KB** |
| `per_page=100&after_id=120000` | 0.66 – 0.70 s | 1.45 MB |
| full walk, 31 pages of 100 | **22 – 25 s total** | ≈ 30 MB |

Reading these numbers: the WordPress bootstrap with all of this site's plugins already costs
**0.28 – 0.35 s**, and our empty page costs the same, so the plugin adds no measurable overhead
to a request that returns nothing. A full page of 100 properties with ~860 images costs
**about 0.15 – 0.25 s of actual work** on top of that baseline. A `per_page=1` request is not
faster than a `per_page=100` one, which says the cost is dominated by WordPress loading, not by
our mapping — so Orbitas should always walk with `per_page=100`: it is ~30× fewer requests for the
same work.

The replica has no persistent object cache (`wp-optimize` and the local `advanced-cache.php` are
deactivated per `CLAUDE.md` §3.3), so these are worst-case numbers; production with a persistent
object cache can only do better.

### Commands run
| Command | Result |
|---|---|
| `composer lint` | no syntax errors (31 files) |
| `composer cs` | **0 errors / 0 warnings** |
| `composer stan` | **No errors** (level 8, 31 files) |
| `composer test` | **OK (71 tests, 195 assertions)** |
| `wp eval-file tools/measure-performance.php … --skip-plugins --skip-themes` | the table above |
| same with `--exec="define('SAVEQUERIES', true);"` | the four-query breakdown above |
| `curl -w %{time_total}` over six request shapes, three samples each | the timing table above |

### Open points carried into Task 11
- Reconciliation tool reusing `tools/bootstrap.php`: walk the whole set through
  `rest_do_request()`, compare totals and unique ids with the database by status and by author,
  and check creator → associate on a sample and on the whole set. Output to
  `docs/reconciliation.md`, aggregates only.

---

## 2026-10-13 — Task 11 — Reconciliation tool

### Files changed
- `tools/reconcile.php` (new) — walks the whole collection through `rest_do_request()` as an
  administrator, then reads the same facts straight from the database and compares them. It
  **writes nothing**: the report goes to stdout, so the tool can never alter the data it is
  checking. Only counts, identifiers and booleans are printed.
- `tools/bootstrap.php` — the media file-existence check is now switched off for the CLI tools
  unconditionally (see the finding below).
- `docs/reconciliation.md` (new) — the generated report, produced with
  `… wp eval-file tools/reconcile.php … > docs/reconciliation.md`.
- `CLAUDE.md` §3.3 — records the container environment-type discrepancy.
- `phpcs.xml.dist` had nothing to change; the tool's own SQL now binds its `LIKE` wildcards.

### Result: **21 of 21 checks passed**

| Group | Checks |
|---|---|
| Totals and unique ids | 3055 returned = 3055 in the database; **0** ids missing from the walk, **0** returned that do not exist, **0** duplicates; ascending across the whole walk |
| By status | `publish` 2612, `draft` 60, `trash` 383 — walked, reported in `totals`, and in the database, all three agreeing |
| By author | 250 distinct authors both sides; **0** authors whose property count differs |
| Field level | creator 3055, missing `associate_id` 25, company 448, location 1992, coordinates 391, featured image 2994, expiration 541, WPUF origin 2512, contract photo 133, properties with leads 20, leads 24 — **every one matching the database** |
| Pagination | same cursor twice → identical page; past the end → empty page, `has_more: false`, `next_after_id: null` |

The walk itself: 31 pages, **381 queries** and **6.7 s** inside the endpoint for the whole 3055
properties (≈ 12 queries per page, consistent with Task 10).

### Finding: the two containers disagree about the environment type
The first run reported **33 219** `media_file_missing` issues. That was not a data problem and not
an endpoint bug — it was the tool trusting `wp_get_environment_type()`:

- over **HTTP** the value is `local`, so the plugin correctly skips the file check (verified with a
  temporary response header, since removed);
- inside the **`wpcli`** container the same call returns `production`, because
  `WORDPRESS_CONFIG_EXTRA` from `compose.yaml` never reached `wp-config.php` (confirmed: the file
  mentions neither `WP_ENVIRONMENT_TYPE` nor `DISABLE_WP_CRON`).

So the CLI tools must not branch on the environment type at all. `tools/bootstrap.php` now disables
the file check unconditionally, with the reason in the code, and the generated report states it.
`CLAUDE.md` §3.3 records the discrepancy so a later session does not rediscover it. Broken
attachment **references** are still fully checked — that needs only `wp_posts`.

### Two bugs in the tool, found by reading its own output
1. `totals.by_status` is cast to an object by the controller so it serializes as `{}`; over
   `rest_do_request()` it arrives as a `stdClass`, and an `is_array()` guard silently read zeros.
   The by-status column showed 0/0/0 against the database's real figures. Fixed with a cast.
2. The "past the end returns `next_after_id: null`" check used `?? 'x'` to detect absence, which
   substitutes the fallback for a value that is *legitimately* null — so a correct response was
   reported as a failure. Now the key's presence is tested separately from its value.

Both were in the checker, not in the endpoint; the endpoint's behaviour had already been verified
over HTTP in Task 5.

### Commands run
| Command | Result |
|---|---|
| `composer lint` / `cs` / `stan` / `test` | clean; **OK (71 tests, 195 assertions)** |
| `wp eval-file tools/reconcile.php … --skip-plugins --skip-themes` | the report above, **21/21** |
| `wp eval 'echo wp_get_environment_type()'` in `wpcli` | `production` |
| temporary `X-Cccbr-Debug-Env` response header over HTTP | `local` (header then removed) |
| `wp eval` grep of `wp-config.php` for the two constants | both absent |

### Open points carried into Task 12
- Postman collection with the test cases, plus the same cases run with `curl` and recorded.

---

## 2026-10-13 — Task 12 — Postman collection

### Files changed
- `postman/cccbr-orbitas-propiedades.postman_collection.json` (new) — Postman v2.1 collection:
  **21 requests, 62 assertions**, in six folders. It contains **no credentials**: HTTP Basic is
  configured at collection level from `{{username}}` / `{{app_password}}`, and the overview explains
  that those belong in a Postman *environment*, which `.gitignore` already excludes
  (`/postman/*.postman_environment.json`).
- `docs/plan.md`, `docs/progress.md`.

| Folder | Requests | What it proves |
|---|---|---|
| Shape | 3 | the envelope's four members, the five pagination members, `per_page` honoured, strictly ascending ids, `next_after_id` = last id of the page, `generated_at` as UTC ISO 8601, `by_status` summing to `total`, **all 38 property fields present on every item**, `{raw, value}` on all ten numeric fields, `{key, label}` on all seven classification fields, and the status filter narrowing both data and totals |
| Full walk | 3 | a request that **loops over itself** through the whole collection with the keyset cursor, asserting on every page that each id is above the cursor and that the walk's final count equals `totals.total`; plus the past-the-end page and a filter matching nothing |
| Consistency | 3 | the same cursor twice returns byte-identical ids, and the first page is unchanged after the walk |
| Limits and invalid arguments | 7 | `per_page` 101 / `abc` / `0`, `after_id=-1`, `status=auto-draft`, `status=publish,bogus` all 400 with the right nested code, and `per_page=100` accepted |
| Authentication and authorization | 3 | 401 without credentials, 403 with a valid Application Password but no capability, and a POST that the route refuses |
| Privacy | 2 | a recursive scan of a 100-property page for 40 forbidden key names, the people allow-list, `private_documents` as booleans, `leads_count` as a number — plus `OPTIONS` publishing the schema |

### The one case that cannot be automated
"A record added during the walk" needs a property to be created mid-walk, so the collection
documents it as a three-step manual procedure and automates the half that can be checked: after the
walk, re-reading the first page must return exactly the same ids, because a new property always
gets a **higher** id and therefore appears at the end of the walk instead of displacing a row.
`pagination.max_id` from the first page is the documented way to pin the snapshot.

### The same cases run with `curl` — all pass

| # | Case | Result |
|---|---|---|
| 01 | first page, `per_page=10` | 200; envelope ok; 10 items; ascending; **38 fields**; `next_after_id` = last id; `generated_at` ISO |
| 02 | `by_status` sums to `total` | 3055 = 3055 |
| 03 | `status=publish` | 200; only `publish` in data and in `by_status` |
| 04 | `status=publish,draft` | 200; `by_status` keys `draft,publish` |
| 05 | past the end | 200; `data: []`; `has_more: false`; `next_after_id: null`; `total` still 3055 |
| 06 | `status=pending` (no matches) | 200; `data: []`; `total: 0`; `by_status: {}`; `max_id: null` |
| 07 | `per_page=100` | 200; 100 items |
| 08 | `per_page=101` / `abc` / `0` | 400, `data.details.per_page.code = cccbr_orbitas_invalid_per_page` |
| 08 | `after_id=-1` | 400, `data.details.after_id.code = cccbr_orbitas_invalid_after_id` |
| 08 | `status=auto-draft`, `status=publish,bogus` | 400, `data.details.status.code = cccbr_orbitas_invalid_status` |
| 09 | no credentials | **401**, `cccbr_orbitas_not_authenticated`, no property data in the body |
| 10 | credentials without the capability | **403**, `cccbr_orbitas_forbidden`, no property data in the body |
| 11 | `POST` to the route | **404 `rest_no_route`** — only a readable method is registered |
| 12 | same cursor twice | identical id lists |
| 13 | `OPTIONS` | 200; schema `cccbr_orbitas_propiedades_collection`; args `per_page,after_id,status`; `per_page.maximum = 100` |
| 14 | privacy scan of a 100-property page | 93 distinct keys, **0** of the 40 forbidden names; people objects exactly `id/display_name/email` (company without email); `private_documents` booleans; `leads_count` an integer |

### Fixed while verifying
The collection's "every property carries the full field set" assertion listed **37** fields; the
real item has **38**. `location` was missing from the list. Found by running the same check with
`curl` rather than by assuming the assertion was right, and corrected in the collection.

### Open points carried into Task 13
- Security review: scan the code, docs, tests and sample responses for secrets and personal data,
  and confirm the allow-list from the code side as well as from the response side.

---

## 2026-10-13 — Task 13 — Security review

Full evidence: **`docs/security-review.md`** (new, registered in `CLAUDE.md` §5 and `README.md`).
Every item there is a command that was run, with its output — nothing inferred from reading code.

### What was checked and found

**Nothing secret is committed.** 51 tracked files, none from `vendor/`, no `.sql`/`.sql.gz`/`.env`.
A grep for credential-shaped assignments across code, config, tests and the Postman collection
returns nothing. The four `CCCBR_API_*` variables appear **only as names**, never with a value.
`.claude/settings.local.json` is on disk but untracked. 13 commits of history scanned for
credential-shaped literals: **0 matches** (the one commit subject matching `/key/` is "keyset").
No `var_dump`, `print_r`, `error_log` or `phpinfo` in shipped code.

**Nothing personal is committed.** No email addresses beyond the author domain and `example.org`
samples; no identity-number or phone-shaped patterns; no associate's name. The only person named is
Gianfranco, the repository owner, named by `LOOP_PROMPT.md` itself. The author name that appeared in
the third-party export inspected during Task 8 was **never written to any file** — verified by
grepping for it. Test fixtures use property vocabulary only.

**The allow-list holds, from both sides.** `Mapper\PeopleMapper` is the only file in `src/` that
touches a user, in four places: one `get_user_meta()` for `numero-de-asociado` (the only user meta
key named anywhere in the plugin), one `get_userdata()`, `display_name` and `user_email`. No
`$user->data`, no `get_object_vars()`, no loop over user meta — a field added to a profile tomorrow
cannot surface. From the response side, a 100-property page has **93 distinct key names** and
**none** of 40 forbidden ones; `creator`/`responsible` carry exactly `id`/`display_name`/`email`,
`company` has no email, `private_documents` is two booleans, `leads_count` an integer.

**The gate works.** 401 without credentials, 403 with a valid Application Password but no
capability; `__return_true` appears nowhere; error bodies carry only `code`, `message` and
`data.status`, and the 400 body does not echo the rejected value. The capability is present in
`wp_user_roles` **once** and held directly by **0** users (`wp_usermeta`).

**There is no write path.** A grep for every WordPress and `$wpdb` write function across `src/`,
the main file and `tools/` returns **nothing**. The only persistence in the plugin is
`add_cap()` on activation and `remove_cap()` in `uninstall.php`. `POST` to the route is
**404 `rest_no_route`**.

**Injection surface.** No superglobals anywhere; arguments are validated twice; the three places
that assemble SQL each bind every value with `prepare()` and build only a `%s`/`%d` run from a
count, with table names bound via `%i`; `apply_filters( 'the_content' )` appears nowhere, so no
shortcode or page-builder hook executes while the response is built; and the plugin makes no
outbound HTTP request of any kind.

### Residual risks recorded (not defects)
1. The payload **is** personal data — HTTPS and a dedicated least-privilege account for Orbitas are
   deployment decisions the plugin cannot enforce.
2. `administrator` receives the capability on activation, as decided in `CLAUDE.md` §2, so every
   existing administrator can read the endpoint. Narrowing it is a one-line change.
3. Media file existence stays unverified in this environment (Q3) — a data-quality gap, not a
   security one.
4. Creating the Orbitas credential is out of scope and was not done.

### Commands run
All listed with their results in `docs/security-review.md` §1–§6: `git ls-files`, five `grep`
sweeps over the tracked tree, `git log -p --all` scanned for credential-shaped literals, three
`curl` probes of the 401/403/400 bodies, the recursive 40-key privacy scan of a 100-property page,
and a WP-CLI query confirming the capability's scope in `wp_user_roles` and `wp_usermeta`.

---

## 2026-10-13 — Task 14 — Consumer guide and final pass

### Files changed
- `docs/consumer-guide.md` (new, 364 lines) — the deliverable for the Orbitas import team:
  authentication and credential hygiene, the request arguments, the walk with both pseudocode and a
  runnable Bash loop, the `max_id` snapshot recipe, how to do an incremental run with
  `modified_at_gmt`, a retry table saying which codes are retryable and which are not, a **complete
  synthetic property** with every field, an explanation of why numbers arrive as `{ raw, value }`
  (with the measured counts), a table of **what the source does not contain** with real figures, the
  issue-code frequencies from a full walk, the endpoint's own limitations, and a nine-step checklist
  for the first import.
- `CHANGELOG.md` — rewritten as the 0.1.0 entry.
- `docs/REPORT.md` (new) — the final report: what was built, architecture with reasons, the criteria
  table with evidence, performance, what the data turned out to be, limitations, and how to remove
  the plugin locally.
- `README.md` — points first at the consumer guide, and lists the report.
- `docs/plan.md` — Task 14 ticked and **all ten acceptance criteria** ticked.

### Verified while writing the guide
- The documented single-property re-fetch trick works: `?per_page=1&after_id=184588` returns exactly
  id 184589, and `?per_page=1&after_id=80186` returns exactly 80187. A claim in a guide is worth
  nothing unless it has been run.
- Every count quoted in the guide is copied from `docs/reconciliation.md` or `docs/schema.md`, both
  of which record the query that produced it.

### Final gates
| Check | Result |
|---|---|
| `composer lint` | no syntax errors |
| `composer cs` | **0 errors / 0 warnings** |
| `composer stan` | **No errors** (level 8) |
| `composer test` | **OK (71 tests, 195 assertions)** |
| `GET` with the authorized account | **200** |
| `GET` with no credentials | **401** |
| `GET` with the account lacking the capability | **403** |
| `logs wordpress` grepped for this plugin, whole log | **0** entries |

---

# Change request 1 — 2026-10-13

Gianfranco reviewed a real response and the code. Orbitas has not validated the contract yet, so the
payload starts lean and grows only on request. One commit per numbered change; every change verified
against the running site before its commit.

## CR1.1 — Media: drop `sizes`

### Files changed
- `src/Mapper/MediaMapper.php` — `sizes` removed from the attachment object and the `sizes()` method
  that built it deleted. `wp_get_attachment_metadata()` is still read, because `width` and `height`
  come from it.
- `src/Rest/PropertySchema.php` — `sizes` removed from the attachment shape.
- `docs/contract.md` §4.9 — example trimmed; two rows added: derivatives are not exposed and why,
  and the fact that the featured image may also appear in the gallery.
- `docs/consumer-guide.md` — synthetic example trimmed, the HEIC row no longer mentions `sizes`, and
  two bullets added to "Reading it": de-duplicate by media `id`, and only the original URL is returned.
- `postman/…collection.json` — new assertion that every attachment carries exactly
  `alt,height,id,mime_type,url,width` (63 assertions now). It was committed one commit late: the
  first edit targeted the file as text and its anchor did not match after an earlier reformat, so it
  silently did nothing while the rest of CR1.1 was committed. Re-done by editing the parsed JSON
  structure instead of the raw text, which cannot miss.

### Verification
| Check | Result |
|---|---|
| `composer cs` | 0 errors / 0 warnings |
| `composer stan` | No errors (level 8) |
| `composer test` | OK (71 tests, 195 assertions) |
| `GET ?per_page=3` | **200**; every attachment object (27 of them) has exactly `alt,height,id,mime_type,url,width` |
| Featured image also present in the gallery | **2 of 3** properties on the first page — the de-duplication note in the guide is warranted, not hypothetical |
| `logs wordpress --tail 100` | **0** entries from this plugin |

## CR1.2 — Numbers: plain values

### Files changed
- `src/Mapper/PropertyMapper.php` — `price.sale/rent/final_sale` and all seven `features.*` are now
  the parsed number or `null`. `strip_state()` is deleted; the parse result is still used internally
  for the `number_missing` and `price_currency_missing` checks, and `map_number()`'s docblock records
  that the issue's `detail` is now the only place a raw source value appears.
- `src/Rest/PropertySchema.php` — `number_field()` is a nullable number instead of an object.
- `docs/contract.md` §4 preamble, §4.3, §4.4, §4.5 (rewritten, with the before/after rationale) and
  the §9 comparison rows.
- `docs/OPEN-QUESTIONS.md` — **Q5 now ANSWERED** with the decision and the reasoning.
- `postman/…collection.json` — the `{ raw, value }` assertion is replaced by two: every numeric
  field is a number or `null`, and every `number_ambiguous`/`number_unparsable` issue carries a
  non-empty string in `detail`.
- `docs/consumer-guide.md` — the synthetic example, the "Reading it" bullet and §7 (now "Why a
  number can be `null`"), including how to match an issue to its field through `issues[].field`.

### Why
The wrapper placed an object on 10 fields of every one of the 3055 properties — 30 550 objects — to
carry information that only **436** values actually need. The raw string is not lost: it moved to the
issue that explains why the number is missing, which is where a consumer looks anyway.

### Verification
| Check | Result |
|---|---|
| `composer cs` | 0 errors / 0 warnings |
| `composer stan` | No errors (level 8) |
| `composer test` | OK (71 tests, 195 assertions) |
| `GET ?per_page=100`, type census over all 10 numeric fields | **0** values that are neither a number nor `null`; types are `integer`, `double` or `NULL` only |
| Raw strings preserved | **16** `number_ambiguous`/`number_unparsable` issues on that page, **all 16** carrying a non-empty string in `detail`, **0** without |
| `logs wordpress --tail 60` | **0** entries from this plugin |

## CR1.3 — Trash: one nullable object

### Files changed
- `src/Mapper/PropertyMapper.php` — `historical_slug`, `status_before_trash` and `trashed_at_gmt`
  are replaced by `trash`, built in a new `map_trash()` that returns `null` unless
  `post_status === 'trash'`.
- `src/Rest/PropertySchema.php` — the three fields become one nullable object with the three members.
- `docs/contract.md` §4.1 — one row instead of three, recording why and keeping the
  `_wp_desired_post_slug` note.
- `postman/…collection.json` — expected field list is now **36** entries, plus a new assertion that
  `trash` is `null` for anything whose status is not `trash` and carries exactly the three members
  when it is.
- `docs/consumer-guide.md` — synthetic example and a new "Reading it" bullet.

### Why
As three top-level fields they were `null` on **2672 of 3055** properties. Grouping them says what
the data actually means: these exist *because* the property is in the trash.

### Verification
| Check | Result |
|---|---|
| `composer cs` / `stan` / `test` | clean / No errors / OK (71 tests, 195 assertions) |
| `GET ?status=trash&per_page=50` | **200**; all 50 items have 36 fields and a `trash` object with exactly `historical_slug,status_before_trash,trashed_at_gmt` |
| `GET ?status=publish&per_page=50` | **200**; all 50 items have 36 fields and `trash: null` |
| `logs wordpress --tail 40` | **0** entries from this plugin |

## CR1.4 — Location: one named object

### Files changed
- `src/Support/LocationResolver.php` (**new, pure**) — decides which assigned term is the province,
  the cantón and the distrito. Groups the terms by taxonomy depth, clears any level holding more
  than one term, clears the **deeper** level when a term's parent contradicts the term assigned
  above it, completes a missing level from the **ancestry of a deeper assigned term** (a fact in the
  taxonomy, not a guess), and reports `location_missing` / `location_incomplete` /
  `location_ambiguous`. No WordPress, so it is unit-tested.
- `src/Mapper/LocationMapper.php` — reduced to the WordPress half: read the assigned terms and the
  taxonomy index, hand both to the resolver, forward its issues. `with_ancestors()` deleted.
- `src/Support/IssueCode.php` — `LOCATION_INCOMPLETE` and `LOCATION_AMBIGUOUS` added.
- `src/Rest/PropertySchema.php` — `location` is an object of three nullable `{ id, name, slug }`
  members; new `term()` helper.
- `cccbr-orbitas-propiedades.php`, `tools/bootstrap.php` — resolver injected.
- `tests/unit/LocationResolverTest.php` (new) — **13 tests**: the clean path, order independence, no
  terms, unknown terms, province only, a distrito completing its own ancestry, two terms on one
  level, a distrito belonging to another cantón, a cantón from another province, two provinces,
  a duplicate term, a term deeper than three levels, and that the three members are always present.
- `docs/contract.md` §4.7 (rewritten, with a conflict table) and the §4.11 issue rows.
- `postman/…collection.json` — two assertions: the member order and term shape, and that the number
  of resolved levels is consistent with the issues reported.
- `docs/consumer-guide.md` — example, two "Reading it" bullets, and a new gap row.

### Why the levels can be named
The taxonomy is exactly three levels deep, its seven root terms are the seven provinces of Costa
Rica, and the property form labels its three selects **"Provincia / Cantón / Distrito"**. So depth
*is* the administrative level, and an anonymous ladder with a `level` number was hiding that.

### The rule that matters
**A conflict is reported, never resolved.** The source does not guarantee one term per level: a
property can carry two cantones, or a distrito whose parent is not the cantón also assigned. In both
cases the affected level becomes `null` and the conflict is reported, because an invented location
is worse than an absent one.

### Verification
| Check | Result |
|---|---|
| `composer cs` | 0 errors / 0 warnings (3 alignment fixes via `cbf`) |
| `composer stan` | No errors (level 8) — the new test needed a narrowing helper rather than indexing a nullable |
| `composer test` | **OK (84 tests, 253 assertions)**, 13 of them new |
| `GET ?per_page=100` | **200**; `location` always has `province,canton,district` in that order, every non-null term has exactly `id,name,slug` |
| Resolution on that page | 75 properties with all three levels, 25 with the province only → **25** `location_incomplete`, no ambiguity |
| `logs wordpress --tail 40` | **0** entries from this plugin |

Whole-dataset counts per location issue code come with the reconciliation re-run at the end of this
change request.

## CR1.5 — Drop `option_value_normalized`

### Files changed
- `src/Support/IssueCode.php` — constant removed from the class and from `all()`.
- `src/Mapper/PropertyMapper.php` — the branch that raised it is gone; only an unresolvable token is
  still reported.
- `src/Support/OptionNormalizer.php` — the `exact` flag existed **only** to feed that issue, so it
  is removed from the entry shape, from `entry()` and from the three resolution branches. The
  docblock now records that how a token matched is deliberately not reported.
- `tests/unit/OptionNormalizerTest.php` — assertions updated; one test renamed from
  `test_unknown_operation_variant_stays_unmapped` to
  `test_unknown_operation_variant_resolves_through_folding`, because that is what it actually
  proves: `alquiler_con_opción_de_compra` folds to the same slug as the glossary label and resolves
  to `opciondecompra`. The old name was wrong before this change too.
- `docs/contract.md` — the 7-step normalizer is now 6 steps, with a note explaining the removal; the
  issue row deleted and the §9 count updated to **22** codes.
- `docs/consumer-guide.md` — the code removed from the frequency table and the paragraph about it
  replaced.

### Why
It reported a **successful** mapping. The consumer receives the canonical `key` and `label` either
way, so there was nothing to act on — and it fired on 367 properties, drowning the codes that do
need action.

### Verification
| Check | Result |
|---|---|
| `composer cs` / `stan` / `test` | clean / No errors / **OK (84 tests, 250 assertions)** |
| `GET ?per_page=100` | **200**; `option_value_normalized` **absent**; classification entries still exactly `key,label` |
| Codes still reported on that page | `location_incomplete` 25, `media_attachment_missing` 31, `price_currency_missing` 95, `number_ambiguous` 11, `number_unparsable` 5, `number_missing` 2, `option_value_unmapped` 3, `media_gallery_empty` 3 |
| `logs wordpress --tail 30` | **0** entries from this plugin |

## CR1.6 — Dates: `null` for a zero date

### Files changed
- `src/Mapper/PropertyMapper.php` — `mysql_to_iso()` returns `?string` and yields `null` instead of
  `""` for `0000-00-00 00:00:00`, with the reasoning in the docblock.
- `src/Rest/PropertySchema.php` — `created_at_gmt` and `modified_at_gmt` are nullable.
- `docs/contract.md` §4.1 — both rows updated.

### Why
Absence is `null` everywhere else in this contract. Returning `""` for a date made these two fields
the single exception a consumer would have to special-case, for a situation that does not even occur
in the data. Found in Gianfranco's code review, not by a test — no zero date exists in the source,
so nothing was failing.

### Verification
| Check | Result |
|---|---|
| `composer cs` / `stan` / `test` | clean / No errors / OK (84 tests, 250 assertions) |
| `GET ?per_page=100` | **200**; both date fields are `string` on all 100 properties, and **no** empty-string date appears — the real data has no zero dates, so the change is latent by design |

## CR1.7 — The race between the two queries

### The defect
Serving a page takes two queries: `find_page_ids()` selects ids under the request's status filter,
and `find_posts()` loaded them **with the same filter re-applied**. A property whose status changed
between the two matched the first and not the second, so it was dropped from the page — and because
the cursor had already advanced past its id, it disappeared from the walk **entirely and silently**.
A consumer walking `?status=publish` could miss a property for no reason other than someone
publishing or drafting it at that instant.

### Files changed
- `src/Repository/PropertyRepository.php` — `find_posts()` no longer takes `QueryArgs` and loads by
  id across `QueryArgs::ALLOWED_STATUSES`. The ids were already filtered by the keyset query, so a
  selected property is now always returned, carrying whatever status it has at that moment. The
  docblock explains all three `get_posts()` decisions (why not the filter, why not `'any'`, why
  `suppress_filters` stays on).
- `src/Pagination/Cursor.php` — new pure `skipped_ids( array $loaded )`: the page ids absent from
  the loaded posts, in page order.
- `src/Rest/PropertiesController.php` — collects the loaded ids and publishes
  `pagination.skipped_ids`.
- `src/Rest/PropertySchema.php` — the new member documented.
- `tests/unit/CursorTest.php` — **5 new tests**: nothing skipped, one id skipped, several skipped in
  page order, the sentinel row beyond the page never reported, and a whole page skipped.
- `tools/reconcile.php` — accumulates `skipped_ids` across the walk, prints the count, and **checks
  it is zero**.
- `docs/contract.md` — `pagination.skipped_ids` row, plus new §5.3 ("Why a status change mid-walk
  cannot lose a property") and §5.4 (`skipped_ids`), with later sections renumbered.
- `postman/…collection.json` — `skipped_ids` added to the expected pagination members, plus an
  assertion that it is an array (logging the ids if any appear).

### What a consumer now sees
A walk with `?status=publish` can occasionally return an item whose `status` is no longer `publish`.
That is the property's live state at the moment it was read, and it is strictly better than a gap.
A property *deleted* between the two queries still cannot be loaded — that id is now listed in
`skipped_ids` rather than vanishing.

### Verification
| Check | Result |
|---|---|
| `composer cs` / `stan` | clean / No errors (level 8) |
| `composer test` | **OK (89 tests, 255 assertions)**, 5 of them new |
| `GET ?per_page=5` | **200**; pagination members are now `after_id,has_more,max_id,next_after_id,per_page,skipped_ids`; `skipped_ids` is `[]` |
| `GET ?per_page=50&status=trash` | **200**; 50 items, all with status `trash`, `skipped_ids: []` — removing the second filter did not widen what a filtered page returns |
| `logs wordpress --tail 30` | **0** entries from this plugin |

The race cannot be reproduced on demand on a quiet replica, which is why it is covered by unit tests
on the pure helper and by a standing check in the reconciliation rather than by a one-off experiment.

## CR1.8 — Composition root built lazily

### Files changed
- `src/Factory.php` (**new**) — the composition root: the one place that knows how the services fit
  together, and the only one that reads the global `$wpdb`. It also decides the media file-check
  flag, so that decision is visible in one place rather than inline in the plugin file.
- `src/Plugin.php` — holds a `Factory` instead of a built controller, and gained
  `register_routes()`, the `rest_api_init` callback that builds the graph and registers the route.
- `cccbr-orbitas-propiedades.php` — now **only** header, `ABSPATH` guard, constants, autoloader with
  its admin-notice fallback, the activation hook and one bootstrap call. The `global $wpdb` read and
  the fourteen `new` expressions are gone from it.
- `tools/bootstrap.php` — calls the **same** `Factory`, so the CLI tools exercise exactly the graph
  an HTTP request uses and the wiring cannot drift into a second copy. It keeps one deliberate
  override: a `wp_get_environment_type` filter forcing `local`, which is how the media file check
  stays off in the `wpcli` container (CR1.7's note and `CLAUDE.md` §3.3 explain why).

### Why
The graph was built on **every** WordPress request — page views, admin screens, cron — to serve an
endpoint that only answers REST calls. Now it is built inside the `rest_api_init` callback, so
anything that never touches the REST API pays nothing for it.

### A real bug PHPStan caught during this change
The new filter was first registered as `add_filter( 'wp_get_environment_type',
'cccbr_orbitas_tools_force_local_environment' )`. The function lives in the
`…\Tools` namespace, so that string names a **global** function that does not exist — the filter
would silently never have fired, and the media check would have come back on in the CLI tools.
PHPStan reported it as "expects callable, 'cccbr_orbitas_tools…' given"; fixed with
`__NAMESPACE__ . '\\…'`.

### Verification
| Check | Result |
|---|---|
| `composer cs` | 0 errors / 0 warnings |
| `composer stan` | No errors (level 8) — after fixing the namespaced-callback bug above |
| `composer test` | OK (89 tests, 255 assertions) |
| `GET` authorized / no credentials / without the capability | **200 / 401 / 403** |
| `OPTIONS` | **200** — the schema is still published |
| `GET /` (home page, no REST involved) | **200** |
| `GET /wp-json/` | `cccbr-orbitas/v1` still registered |
| `wp eval-file tools/measure-performance.php` | same figures as before: **4** warm queries, **20** cold, flat as the page grows — and no `media_file_missing`, so the forced-`local` filter works |
| `logs wordpress --tail 40` | **0** entries from this plugin |
