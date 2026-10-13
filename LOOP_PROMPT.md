# Prompt loop — CCCBR Orbitas Propiedades (REST export plugin)

Execute this end to end. Do not stop for confirmation between tasks.
Work through `docs/plan.md` in order, commit after each task, and continue.
Stop only when every acceptance criterion in section 9 is genuinely ✅ (then deliver the report),
or when you hit one of the blockers listed in section 1.3.

If this session ends before you finish, the next session receives this same prompt: read
`docs/plan.md` and `docs/progress.md` and continue from the first unchecked task.

---

## 1. How to run

### 1.1 Start of every session
1. Read `CLAUDE.md`, `docs/plan.md`, `docs/progress.md`. If they do not exist, start with Task 0.
2. `git status`. On the very first session the untracked files `LOOP_PROMPT.md` and
   `.claude/settings.json` are expected: they go into the Task 0 commit. In later sessions,
   if there are uncommitted changes you did not make in this session, list them in
   `docs/progress.md` and stop (blocker). Never discard anything.
3. Check the four test variables exist without printing them, e.g. `test -n "$CCCBR_API_USER"`
   (repeat for `CCCBR_API_APP_PASSWORD`, `CCCBR_API_NOACCESS_USER`, `CCCBR_API_NOACCESS_APP_PASSWORD`).
   If any is missing, do Tasks 0–3 and stop before Task 4 (blocker).

### 1.2 For each task
1. Re-read the parts of `docs/schema.md` and `docs/contract.md` the task depends on (once they exist).
2. Implement following sections 4–6.
3. Verify (section 7). A task is done only when all applicable checks pass.
4. Check the box in `docs/plan.md` and append to `docs/progress.md`: date, task, files changed,
   commands run with a short summary of their output (no personal data), facts marked
   **VERIFIED** (with evidence) or **TO VERIFY**, open points.
5. Commit with a Conventional Commits message (`feat:`, `fix:`, `docs:`, `test:`, `chore:`,
   `refactor:`). **Do not push.** Then continue with the next task.

### 1.3 Blockers (the only reasons to stop early)
- Test credentials missing when a task needs them.
- A permission prompt is denied.
- A fact in this prompt is contradicted by the real code or data **and** the contradiction
  changes a decision in section 2.3. Smaller discrepancies: record them in
  `docs/OPEN-QUESTIONS.md`, choose the safest option (exclude, `null`, report) and continue.
- Anything that would require touching production, other plugins, themes, core or the replica configuration.

When blocked: write the reason in `docs/progress.md`, commit what is done, and stop with a short report.

Never invent fields, keys, values, relations or results. Never claim a check passed without running it.

---

## 2. Project context

### 2.1 What we are building
WordPress plugin **CCCBR Orbitas Propiedades**, slug `cccbr-orbitas-propiedades`, author
**ElPuas Digital Crafts**, for CCCBR (Cámara Costarricense de Corredores de Bienes Raíces, site camara.cr).

It exposes an **authenticated, read-only REST endpoint** so the Orbitas team can pull **all**
CCCBR properties with complete property data, images, relations and the data of the user who
created each property, to import them into their Marketplace (Azure).

- Repository root = plugin folder = this working directory.
- GitHub: `https://github.com/gianfranco2605/cccbr-orbitas-propiedades` (private). Owner: Gianfranco Navas.

### 2.2 The ticket (source of truth for scope)

**Scope**
- Confirm the real CPT, schema, metadata, taxonomies, images and relations in the source environment.
- Expose complete property data: stable WordPress ID, title, description, status, creation /
  update / expiration dates, price, currency, operation type, property type, location,
  coordinates when they exist, slug / historical URL, origin for audit.
- Include the relevant custom fields and taxonomies, preserving types, values and relations.
  Document missing fields or fields without an equivalent. The final contract must derive from the real source.
- Include main image, gallery and order, media IDs and URLs, available originals / derivatives.
  Report broken references and missing files.
- Relate each property to its creator: original author ID, identification and the allowed
  profile / contact data needed for mapping. Distinguish the creator from the responsible
  associate when different, and keep the link to the associate ID and the Marketplace ID when it exists.
- Authenticated and authorized read access for Orbitas, stable pagination, maximum page size,
  load controls. Walking the whole set must give no omissions and no duplicates.
- Exclude passwords, password hashes, tokens, keys and secrets. Define the allowed user fields
  explicitly; never dump user tables or private metadata.
- Scope is **the totality available in the source**, with no silent restriction to published,
  featured or a date cut. Document the statuses present and any unrecoverable record.
  Do not restore deleted properties.

**Out of scope:** deletions, import or full migration, DNS changes, **deployment to production**,
**creating credentials for Orbitas**, delivering data or access to third parties.

### 2.3 Decisions already taken (do not change them)

| Topic | Decision |
|---|---|
| Route | `GET /wp-json/cccbr-orbitas/v1/propiedades` (read-only, collection only) |
| Authentication | WordPress **Application Passwords** (HTTP Basic). No custom auth, no JWT. |
| Authorization | Dedicated capability `cccbr_orbitas_read_propiedades`, granted to `administrator` on activation, removed on uninstall. The plugin never creates users or passwords. |
| Statuses | **All** statuses of the CPT by default (`publish`, `draft`, `pending`, `private`, `future`, `trash`), each item carrying its status. Optional explicit `status` filter. `auto-draft` and `inherit` excluded and documented. |
| Pagination | Keyset cursor on post ID ascending (`after_id`). `per_page` default 50, **maximum 100**; values above the maximum → 400. No offset pagination, no `-1`. |
| User data | Only: user ID, display name, email. Never login, password, hash, activation key, sessions, tokens, phone, national ID (cédula) or any other user meta. |
| Associate / Marketplace IDs | Only if a real source field is found (Task 1). Otherwise `null` and documented. |
| Private documents | `foto-del-contrato` and `documentos-anexos` are **not** exposed: only a boolean saying whether each exists. |
| Leads | Lead data **not** exposed: only the number of leads per property. |
| Old plugin | `cccbr-api-propiedades` (public route `cccbr/v1/propiedades`) is **not modified, not reused, not deactivated**. Read it only as reference. |
| Trashed properties | Historical slug from `_wp_desired_post_slug`, previous status from `_wp_trash_meta_status`, trash date from `_wp_trash_meta_time`, mapped to contract fields (not exposed as raw meta). |
| Technical meta | Not exposed: `_oembed_*`, `_elementor_*`, `_edit_*`, `_eael_*`, `_yoast_*`, `ast-*`, `astra*`, `site-*`, `header-*`, `stick-*`, `theme-transparent-*`, `adv-header-*`, `_wp_page_template`, `jet_engine_store_count_*`, internal WPUF workflow meta. Every excluded key appears in `docs/schema.md` with its reason. |
| Rule for every field | If Orbitas needs it to import a property, include it. If it is internal plumbing of a plugin or theme, exclude it and document why. |

---

## 3. Environment

### 3.1 Local replica
- Docker project at `~/Projects/cccbr-replica` (outside iCloud), compose file `~/Projects/cccbr-replica/compose.yaml`.
- Services: `db` (MariaDB 11.4), `wordpress` (WordPress 6.9.10, PHP 8.2, Apache), `wpcli` (profile `tools`).
- Site: `http://localhost:8080`. Table prefix `wp_`. Environment type `local` (Application Passwords work over HTTP).
- `./wp-content` is bind-mounted: **this repository is live in the local site**, no copy or restart needed.
- Database: production dump of **13 Oct 2026** (SHA-256 verified).
- **Production runs PHP 8.1.13. All code must run on PHP 8.1.** No PHP 8.2+ features
  (readonly classes, DNF types, `true`/`null`/`false` standalone types, constants in traits, etc.).

### 3.2 Exact command forms (they match the permissions in `.claude/settings.json`)
- WP-CLI: `docker compose -f ~/Projects/cccbr-replica/compose.yaml run --rm wpcli wp <command> --path=/var/www/html`
  - Add `--skip-plugins --skip-themes` to every DB query, option read and capability check.
  - **Known issue:** loading all plugins in WP-CLI exhausts the 128 MB memory limit
    (verified 13 Oct). Activate this plugin with `--skip-plugins --skip-themes` and then verify
    the capability with `wp cap list administrator --skip-plugins --skip-themes`.
    Never run WP-CLI with all plugins loaded.
  - Do **not** call plain `wp …` on the Mac: it does not reach the replica.
- PHP in the web container: `docker compose -f ~/Projects/cccbr-replica/compose.yaml exec wordpress php <args>`
- Logs: `docker compose -f ~/Projects/cccbr-replica/compose.yaml logs wordpress --tail 50`
- HTTP: start every command with one of `curl -s http://localhost:8080`, `curl -sS http://localhost:8080`,
  `curl -s -i http://localhost:8080`, `curl -s -o /dev/null -w`,
  `curl -s -u "$CCCBR_API_USER:$CCCBR_API_APP_PASSWORD" http://localhost:8080` or
  `curl -s -u "$CCCBR_API_NOACCESS_USER:$CCCBR_API_NOACCESS_APP_PASSWORD" http://localhost:8080`.
- Plugin path inside the containers: `/var/www/html/wp-content/plugins/cccbr-orbitas-propiedades`.
- Use the `~/Projects/…` form literally (do not expand it to `/Users/…`), or the permission rules will not match.
- **Never `cd`.** Run every command from the repository root; the `-f ~/Projects/cccbr-replica/compose.yaml`
  flag already points Docker to the replica. Prefer the Read / Glob / Grep tools over shell commands to inspect files.
- `.claude/settings.local.json` is personal: never commit it, never edit it; add it to `.gitignore`.

### 3.3 Local-only state (do not "fix" it)
- Deactivated **only locally**: `really-simple-ssl`, `wp-optimize`, `google-site-kit`. Local `advanced-cache.php` removed.
- Not copied (inactive in production): `fancy-elementor-gallery-box`, `jet-form-builder-save-progress`,
  `wp-rest-api-authentication`, `proteger-uploads`, theme `hello-elementor`.
- Mail blocked by `mu-plugins/000-local-safety.php`; cron disabled; external HTTP blocked; updates and file mods disabled.
- `wp-content/uploads` is **not** local: every request is redirected (302) to production.
  **Media file-existence checks cannot be validated locally**: implement them, and state this
  limitation in `docs/progress.md`, `docs/reconciliation.md` and the consumer guide.

### 3.4 Test credentials (local only, already created)
Two local users exist, each with an Application Password created by Gianfranco:
- `orbitas-dev` (ID 1883, `administrator`): receives the capability on activation → expected 200.
- `orbitas-noaccess` (ID 1884, `subscriber`): never has the capability → expected 403.

Gianfranco exports them before starting the session:
`CCCBR_API_USER`, `CCCBR_API_APP_PASSWORD`, `CCCBR_API_NOACCESS_USER`, `CCCBR_API_NOACCESS_APP_PASSWORD`.
Use them only through these variables (e.g. `curl -s -u "$CCCBR_API_NOACCESS_USER:$CCCBR_API_NOACCESS_APP_PASSWORD" http://localhost:8080…`).
**Never print, log, write to a file or commit their values.** Do not create, delete or change users or Application Passwords.

### 3.5 Hard limits
- **Never** read `~/Projects/cccbr-replica/database.sql.gz` or any `.sql` file.
- **Never** contact production: no `ssh`, `scp`, `rsync`, no request to `camara.cr`, `www.camara.cr`, `tienda.camara.cr`.
- **Never** run `docker compose down -v`, `docker volume rm`, `docker system prune`, `git push`, `git push --force`, `git reset --hard`.
- **Never** modify other plugins, themes, mu-plugins, WordPress core or the replica configuration. Only this repository.
- **Personal data:** the local database contains real people. Use aggregate queries (counts, key
  names, IDs). Never print names, emails, phone numbers, national IDs or addresses in chat, logs,
  docs, tests, fixtures, examples or commit messages. All examples are **synthetic**.

### 3.6 Skills (installed globally in `~/.claude/skills`)
- Start with `wordpress-router` and `wp-project-triage`.
- `wp-plugin-development` — plugin structure, lifecycle (activation / uninstall), capabilities, security.
- `wp-rest-api` — route registration, permission callbacks, argument and response schema, errors.
- `wp-wpcli-and-ops` — every WP-CLI operation, **always with the command forms of 3.2** (never plain `wp`).
- `wp-phpstan` — static analysis, configured for PHP 8.1.
- `wp-performance` — Task 10.
- Do not use skills for block themes, blocks, Interactivity API, Abilities API, Playground or design: they are out of scope.

---

## 4. Verified source facts (production, 11–13 Oct 2026)

Re-verify each fact on the local replica in Task 1 and record the result in `docs/schema.md`.

**CPT**
- `cccbr-propiedades`. Local counts on 13 Oct: `publish` 2612, `draft` 60, `trash` 383 (3055).
- JetEngine CPT definition: row id 10 in `wp_jet_post_types`; its `meta_fields` column is empty.

**Custom fields:** JetEngine meta box **"Campos propiedades"** in option `jet_engine_meta_boxes`, 28 fields:

| Field | Type | Notes |
|---|---|---|
| `nombre-de-propiedad` | text | only 5 properties; the real name is `post_title` |
| `tipo-de-propiedad` | select | glossary 12; stored as a label (e.g. `Apartamento`) |
| `estatus-propiedad` | checkbox | glossary 15; serialized array (e.g. `Venta`); = operation type |
| `opciondecompracondiciones` | wysiwyg | |
| `numero-de-finca` | text | property registry number |
| `uso-de-suelo` | checkbox | `usoresidencial=Residencial`, `usocomercial=Comercial`, `usoindustrial=Industrial`, `usoagricola=Agrícola` |
| `exclusividad` | checkbox | `completa=Completa`, `solopublicacion=Sólo publicación` |
| `foto-del-contrato` | media | private, not exposed |
| `moneda` | select | `Colones`, `Dólares`; only 11 properties → report missing elsewhere, never infer |
| `precio-de-venta`, `precio-de-alquiler`, `habitaciones`, `banos`, `garage`, `plantas` | number | stored as strings, may be empty |
| `terreno`, `construccion`, `ano-de-construccion`, `precio-final-de-venta` | **text** | may hold non-numeric values: never cast blindly |
| `mapa` | text | |
| `imagen-principal` | media | value format `both`; only 5 properties |
| `imagenes` | gallery | value format `id`; serialized array of ID **strings** |
| `amenidades` | checkbox | source `manual` but glossary id 11 set: verify which is used |
| `video_propiedad` | text | |
| `propiedad_vendida` | checkbox | `vendida=Propiedad vendida`; may be an empty array |
| `descripcion-en-ingles` | wysiwyg | |
| `documentos-anexos` | media | value format `both`; private, not exposed |
| `corredor_asignado` | select | options from a JetEngine query; only 5 properties; = responsible associate |
| `compartir_propiedad` | checkbox | `compartir_propiedad=Compartir Propiedad` |

- Checkbox values are serialized PHP arrays. Distinguish **empty** (key exists, empty value) from **missing** (key absent).
- Glossaries: rows with `status = 'glossary'` in `wp_jet_post_types`. Verify their storage format before resolving labels.

**Other meta**
- `_thumbnail_id` on 2993 of 3049. The most recent property sampled had **no** featured image, only a gallery.
- Expiration: `_jet_pep_period` (Unix timestamp) + `_jet_pep_action` (e.g. `draft`) on 533 properties (JetEngine post expiration module).
- Coordinates: `4437ad177c1679b1090c78835bb6c221_lat` / `_lng` / `_hash` on 496 properties.
  **Origin not verified** (probably JetEngine geocoding cache for `mapa`): confirm in the JetEngine source before using.
- Origin: `_wpuf_form_id` on 2517 properties (WP User Frontend). Recent properties come from the
  JetFormBuilder form "Publicar Propiedad CCCBR" (page `/mi-cuenta/publicar-propiedad/`;
  attachments under `uploads/jet-form-builder/<hash>/YYYY/MM/`).
- Trash meta: `_wp_trash_meta_status`, `_wp_trash_meta_time`, `_wp_desired_post_slug` on ~378 properties.

**Taxonomy:** only `ubicacion-propiedad`, hierarchical, 3 levels (7 / 82 / 488 terms; probably
province / canton / district, not verified by name). Used by 1983 of 3049 properties; the rest have
no location term: report it.

**JetEngine relations**
- 171: `posts::cccbr-propiedades` → `posts::leads-cccbr`, one_to_many. Storage table not verified. Used only for the lead count.
- 131: `mix::users` → `mix::users`, one_to_many, own table `wp_jet_rel_131` (`parent_object_id`, `child_object_id`).
  Parents: 2 users with role `empresa_activa`. Children: 12 users (11 `um_asociado-activo-cccbr`,
  1 `um_asociado-inactivo-cccbr`). Meaning: company → associate.
- 175: leads → lead comments. Not used.

**To find in Task 1:** the user meta key holding the associate / broker number (if any) and any key
holding a Marketplace / Orbitas ID (if any). Search **key names and counts only**, never values.

---

## 5. Engineering standards

### 5.1 Architecture and separation of concerns
- Main plugin file: header, `ABSPATH` guard, constants, autoloader include, one bootstrap call. No logic.
- Namespace `ElPuas\CCCBR\OrbitasPropiedades`, PSR-4 autoload via Composer from `src/`.
  If `vendor/autoload.php` is missing, show an admin notice and do nothing. Never fatal.
- One responsibility per class. Suggested split: `Plugin` (wiring), `Rest\PropertiesController`,
  `Auth\Permissions`, `Lifecycle\Activator`, `Schema\FieldMap` (definitions read from the meta box),
  `Repository\PropertyRepository`, `Repository\RelationsRepository`, `Pagination\Cursor`,
  `Mapper\PropertyMapper`, `Mapper\MediaMapper`, `Mapper\LocationMapper`, `Mapper\PeopleMapper`,
  `Support\IssueCollector`. Adjust if Task 1 shows a better split; record why.
- Constructor dependency injection. No singletons, no global state, no `global`, no static mutable state.
- Hook callbacks only delegate to services.
- `declare(strict_types=1);`, typed properties, parameter and return types, `final` by default, small methods.

### 5.2 WordPress
- WPCS 3 via PHPCS (`phpcs.xml.dist`), plus **PHPCompatibilityWP with `testVersion` 8.1-**.
  Prefix everything (`cccbr_orbitas_`, `CCCBR_ORBITAS_`).
- `register_rest_route` on `rest_api_init`, real `permission_callback` (never `__return_true`),
  args with `type`, `sanitize_callback`, `validate_callback`, response schema via `get_item_schema`,
  `WP_REST_Response` / `WP_Error` with correct status codes.
- WordPress APIs first. `$wpdb` only where no API exists (keyset `ID > after_id` selection,
  JetEngine relation tables, aggregate counts), always `$wpdb->prepare()` and `$wpdb->prefix`.
- No `apply_filters( 'the_content' )` on API output: raw stored content.
- Capability added on activation, removed in `uninstall.php` (guarded by `WP_UNINSTALL_PLUGIN`). Nothing else persisted.
- Header: name, description, version `0.1.0` (SemVer), `Requires at least: 6.9`, `Requires PHP: 8.1`,
  `Author: ElPuas Digital Crafts`, text domain `cccbr-orbitas-propiedades`, license.
- i18n for every human-readable string, error messages included.

### 5.3 Security
- Read-only; no side effects on GET.
- Unauthenticated → 401, authenticated without capability → 403, invalid args → 400.
- Explicit allow-list field map. Never return raw meta arrays or raw user objects.
- No secrets in code, config, docs, tests or fixtures.

### 5.4 Performance and load control
- `per_page` hard cap 100.
- Select the page IDs, then prime caches in bulk (`update_meta_cache`, `update_object_term_cache`,
  `cache_users`, attachment post and meta caches). No N+1 queries.
- Status totals with one aggregate query per request.

### 5.5 JavaScript (only if ever needed)
Separate enqueued files, ES modules, `const` / `let`, no `var`, no globals, no inline scripts, no data printed as JS globals.

### 5.6 Tooling
- `composer.json` with dev dependencies: `wp-coding-standards/wpcs`, `phpcompatibility/phpcompatibility-wp`,
  `dealerdirect/phpcodesniffer-composer-installer`, `phpstan/phpstan`, `szepeviktor/phpstan-wordpress`,
  `phpunit/phpunit`. Write them in `composer.json` and run `composer install` (do not use `composer require`).
  Scripts: `lint`, `cs`, `cbf`, `stan`, `test`.
- If PHP or Composer are missing on the Mac, record it and run `php -l` in the `wordpress` container;
  report what could not run (blocker only if `composer install` is impossible).
- PHPUnit for pure units without WordPress (cursor, mappers with injected data, type coercion).
- `.gitignore`: `vendor/`, `node_modules/`, `*.sql`, `*.sql.gz`, `.env*`, `.claude/settings.local.json`,
  OS / editor files, Postman environment files.
- `README.md`, `CHANGELOG.md` (Keep a Changelog).

---

## 6. Response contract (finalized in `docs/contract.md` after Task 1)

Collection:
- `data`: array of properties
- `pagination`: `per_page`, `after_id`, `next_after_id`, `has_more`
- `totals`: total and count per status at request time
- `generated_at` (UTC, ISO 8601)

Each property (English `snake_case`; every field mapped to its source key in the contract):
- `id`, `title`, `slug`, `historical_slug` (trash), `url`, `status`, `status_before_trash`, `trashed_at_gmt`,
  `created_at_gmt`, `modified_at_gmt`, `content` (raw), `description_en`
- `expiration`: `{ at_gmt, action }` or `null`
- `operation`, `property_type`, `land_use`, `exclusivity`, `sold`, `share`, `amenities`: arrays of `{ key, label }` for select / checkbox sources
- `price`: `{ sale, rent, final_sale, currency }` (`currency` `null` when `moneda` is missing; never inferred)
- `features`: `bedrooms`, `bathrooms`, `garage`, `floors` (number or `null`); `land_area`, `built_area`, `year_built` (raw text + parsed number only when unambiguous)
- `registry_number`, `purchase_option_terms`, `video_url`, `map_raw`
- `location`: assigned terms with full ancestor path `[{ id, name, slug, level }]`, or `[]`
- `coordinates`: `{ lat, lng, source }` or `null`
- `media`: `featured` and ordered `gallery`; each `{ id, url, mime_type, width, height, alt, sizes }`
- `private_documents`: `{ has_contract_photo, has_attachments }`
- `creator`: `{ id, display_name, email }` or `null` if the author does not exist
- `responsible`: user from `corredor_asignado` when different from the creator, same shape, else `null`
- `company`: parent of the creator through relation 131, `{ id, display_name }`, else `null`
- `associate_id`, `marketplace_id`: from a real source field, else `null`
- `leads_count`: integer
- `origin`: `{ source, wpuf_form_id }`
- `issues`: machine-readable codes for anything missing, ambiguous or broken on this property

Errors: `400`, `401`, `403`, each with a stable `code` and a translated `message`.

If Task 1 shows a field above has no source, or a source field is missing above, update the contract and record why.

---

## 7. Verification

Verify against the **running site**, not only the source files.
- `php -l` on every changed PHP file (Mac or `wordpress` container).
- `composer cs`, `composer stan`, `composer test` once tooling exists.
- `logs wordpress --tail 50` after requests: no notices, warnings or errors from this plugin.
- HTTP checks with `curl` against `http://localhost:8080`, reporting only status codes, field names, counts and IDs.

---

## 8. Plan (`docs/plan.md` — create it in Task 0 with these tasks)

- [ ] **Task 0 — Bootstrap.** Check `php -v`, `composer -V`, `git config user.name`. Create `CLAUDE.md`
  (durable rules: sections 2.3, 3, 5 and the hard limits), `docs/plan.md`, `docs/progress.md`,
  `docs/OPEN-QUESTIONS.md`, `README.md`, `CHANGELOG.md`, `.gitignore`, `composer.json`,
  `phpcs.xml.dist`, `phpstan.neon.dist`, the minimal main plugin file. `composer install`.
  Commit everything including `LOOP_PROMPT.md` and `.claude/settings.json`.
- [ ] **Task 1 — Schema discovery** → `docs/schema.md`. Re-verify section 4. List **every** meta key on
  `cccbr-propiedades` with counts and classify it (included / excluded + reason). Resolve glossary
  storage, coordinate origin (JetEngine source), relation 171 storage, statuses present.
  Search user meta key names (counts only) for associate number and Marketplace / Orbitas IDs.
- [ ] **Task 2 — Contract** → `docs/contract.md`: route, method, auth, permissions, args, every field
  (type, nullability, source key), pagination, limits, errors, consistency mechanism.
- [ ] **Task 3 — Skeleton and lifecycle**: autoload, `Plugin`, activator, `uninstall.php`. Activate
  locally (3.2), confirm the capability and no errors in the logs.
- [ ] **Task 4 — Route, permissions, argument validation**: empty, correctly shaped response. Verify 401, 403, 400, 200.
- [ ] **Task 5 — Repository and keyset pagination** with all statuses, `status` filter and totals.
  Walk all IDs: count equals the DB count, no duplicates.
- [ ] **Task 6 — Core fields and meta mapping** (typed values, labels, empty vs missing, price, currency, features, trash fields).
- [ ] **Task 7 — Location, coordinates, expiration, origin.**
- [ ] **Task 8 — Media** (featured, ordered gallery, derivatives, issues).
- [ ] **Task 9 — People and relations**: creator, responsible, company, associate / Marketplace IDs, lead count, missing authors.
- [ ] **Task 10 — Performance**: cache priming, queries per page, timing for `per_page=100`. Record the numbers.
- [ ] **Task 11 — Reconciliation tool** `tools/reconcile.php`, run with `wp eval-file … --skip-plugins --skip-themes`
  (it loads this plugin's autoloader itself and calls the controller through `rest_do_request()`
  as an administrator, so no HTTP credentials are needed). Compare totals and unique IDs with the DB,
  by status and by author; check creator → associate on a sample and on the whole set.
  Output → `docs/reconciliation.md` (aggregates only).
- [ ] **Task 12 — Postman collection** `postman/cccbr-orbitas-propiedades.postman_collection.json`
  (variables for base URL and credentials, no secrets) with tests: first page, multiple pages,
  last page, zero results, `per_page` limit, invalid args, same cursor twice = same page, record added
  during the walk (manual step documented), 401, 403, no secret-like keys in the response.
  Run the same cases with `curl` and record the results.
- [ ] **Task 13 — Security review**: scan code, docs, tests and sample responses for secrets and personal data; confirm the allow-list. Record evidence.
- [ ] **Task 14 — Consumer guide** `docs/consumer-guide.md`: authentication, walking all pages, retries,
  field reference, synthetic examples, known limitations (local media checks, missing currency,
  properties without location). Final pass on `README.md`, `CHANGELOG.md`, `CLAUDE.md`.

---

## 9. Acceptance criteria (all must be ✅, with evidence, against the running local site)

- [ ] AC1 Contract documented from the real schema (route, method, auth, permissions, fields, types, pagination, limits, errors).
- [ ] AC2 Full walk returns every property in the source; statuses documented; no implicit filter by date, publication or associate status.
- [ ] AC3 Counts and unique IDs reconciled against the DB, total and by status / author; differences justified.
- [ ] AC4 Complete fields, custom fields, taxonomy and images per property; missing items reported.
- [ ] AC5 Property → creator → associate checked on a sample and in total; missing authors and ambiguous relations reported, nothing invented.
- [ ] AC6 Tests for multiple pages, last page, zero results, limits, retries, records changed during extraction; consistency mechanism documented.
- [ ] AC7 Unauthenticated → 401, no capability → 403; no secrets or personal data in responses, docs or examples.
- [ ] AC8 Consumer documentation with synthetic examples.
- [ ] Code: `composer cs` clean, `composer stan` clean, `composer test` green, PHP 8.1 compatible, no plugin entries in the logs.
- [ ] `git status` clean, every task committed, nothing pushed, production untouched.

## 10. Deliverables and final report

- `docs/REPORT.md`: what was built, architecture and decisions with reasons, criteria results with evidence,
  limitations, how to remove the plugin locally.
- `docs/OPEN-QUESTIONS.md`: everything that needs Gianfranco's or Orbitas' decision.
- `CLAUDE.md` updated with the durable project rules.

Then STOP and show: the criteria checklist with evidence, the commit list, and the open questions.
