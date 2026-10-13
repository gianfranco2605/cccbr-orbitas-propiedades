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
