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
- `src/.gitkeep` removed (the directory now holds real files).

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
