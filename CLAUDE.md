# CLAUDE.md — CCCBR Orbitas Propiedades

Durable rules for this repository. The repository root **is** the plugin folder and it is live
inside the local WordPress replica (bind mount), so every edit is immediately active on
`http://localhost:8080`.

The session driver is `LOOP_PROMPT.md` (how to run, task list, acceptance criteria).
This file holds the rules that stay true between sessions.

---

## 1. What this plugin is

WordPress plugin **CCCBR Orbitas Propiedades** (`cccbr-orbitas-propiedades`), author
**ElPuas Digital Crafts**, for CCCBR (Cámara Costarricense de Corredores de Bienes Raíces).

It exposes one **authenticated, read-only REST endpoint** so the Orbitas team can pull all CCCBR
properties — complete property data, images, relations and the data of the user who created each
property — to import them into their Marketplace (Azure).

Out of scope: deletions, import/migration, DNS, deployment to production, creating credentials for
Orbitas, delivering data or access to third parties.

---

## 2. Decisions already taken (do not change)

| Topic | Decision |
|---|---|
| Route | `GET /wp-json/cccbr-orbitas/v1/propiedades` (read-only collection) and, **extended by change request 3 (13 Oct 2026)**, `GET /wp-json/cccbr-orbitas/v1/propiedades/{id}` for one property — same auth, same capability, same mappers, so both return the identical object. |
| Authentication | WordPress **Application Passwords** (HTTP Basic). No custom auth, no JWT. |
| Authorization | Capability `cccbr_orbitas_read_propiedades`, granted to `administrator` on activation, removed on uninstall. The plugin never creates users or passwords. |
| Statuses | **Superseded by change request 2 (13 Oct 2026):** `publish`, `draft`, `pending`, `private`, `future` — **`trash` is excluded**. Each item carries its status; the optional `status` filter accepts only those five and returns 400 for anything else, `trash` included. `auto-draft` and `inherit` remain excluded and documented. |
| Pagination | Keyset cursor on post ID ascending (`after_id`). `per_page` default 50, **max 100**; above max → 400. No offset pagination, no `-1`. |
| User data | Only user ID, display name, email. Never login, password, hash, activation key, sessions, tokens, phone, national ID (cédula) or any other user meta. |
| Associate / Marketplace IDs | Only if a real source field exists. Otherwise `null` + documented. |
| Private documents | `foto-del-contrato` and `documentos-anexos` are **not** exposed — only a boolean saying whether each exists. |
| Leads | Lead data **not** exposed — only the number of leads per property. |
| Old plugin | `cccbr-api-propiedades` (public route `cccbr/v1/propiedades`) is **not** modified, reused or deactivated. Reference only. |
| Trashed properties | **Superseded by change request 2 (13 Oct 2026): not exported at all.** CCCBR decided a deleted property must not reach Orbitas, so `_wp_desired_post_slug`, `_wp_trash_meta_status` and `_wp_trash_meta_time` are no longer read anywhere and the `trash` contract field is gone. The measurement stays in `docs/schema.md` §1.2 in case the decision is ever reversed. |
| Technical meta | Not exposed: `_oembed_*`, `_elementor_*`, `_edit_*`, `_eael_*`, `_yoast_*`, `ast-*`, `astra*`, `site-*`, `header-*`, `stick-*`, `theme-transparent-*`, `adv-header-*`, `_wp_page_template`, `jet_engine_store_count_*`, internal WPUF workflow meta. Every excluded key is listed in `docs/schema.md` with its reason. |
| Rule for every field | If Orbitas needs it to import a property, include it. If it is internal plumbing of a plugin or theme, exclude it and document why. |
| PHPStan level | **8** (greenfield, strictly typed; type coercion of source fields is the core risk of this plugin). |

---

## 3. Environment

### 3.1 Local replica
- Docker project at `~/Projects/cccbr-replica`, compose file `~/Projects/cccbr-replica/compose.yaml`.
- Services: `db` (MariaDB 11.4), `wordpress` (WordPress 6.9.10, PHP 8.2, Apache), `wpcli` (profile `tools`).
- Site `http://localhost:8080`, table prefix `wp_`, environment type `local` (Application Passwords work over HTTP).
- `./wp-content` is bind-mounted: this repository is live in the local site. No copy or restart needed.
- Database: production dump of **13 Oct 2026** (SHA-256 verified).
- **Production runs PHP 8.1.13 — all code must run on PHP 8.1.** No PHP 8.2+ features (readonly
  classes, DNF types, standalone `true`/`null`/`false` types, constants in traits, …).

### 3.2 Exact command forms (they match `.claude/settings.json`)
- WP-CLI: `docker compose -f ~/Projects/cccbr-replica/compose.yaml run --rm wpcli wp <command> --path=/var/www/html`
  - Add `--skip-plugins --skip-themes` to **every** DB query, option read and capability check.
  - **Known issue (verified 13 Oct 2026):** loading all plugins in WP-CLI exhausts the 128 MB memory
    limit. Activate this plugin with `--skip-plugins --skip-themes`, then verify with
    `wp cap list administrator --skip-plugins --skip-themes`. Never run WP-CLI with all plugins loaded.
  - Do **not** call plain `wp …` on the Mac: it does not reach the replica.
- PHP in the web container: `docker compose -f ~/Projects/cccbr-replica/compose.yaml exec wordpress php <args>`
- Logs: `docker compose -f ~/Projects/cccbr-replica/compose.yaml logs wordpress --tail 50`
- HTTP: start every command with one of `curl -s http://localhost:8080`, `curl -sS http://localhost:8080`,
  `curl -s -i http://localhost:8080`, `curl -s -o /dev/null -w`,
  `curl -s -u "$CCCBR_API_USER:$CCCBR_API_APP_PASSWORD" http://localhost:8080` or
  `curl -s -u "$CCCBR_API_NOACCESS_USER:$CCCBR_API_NOACCESS_APP_PASSWORD" http://localhost:8080`.
- Plugin path inside the containers: `/var/www/html/wp-content/plugins/cccbr-orbitas-propiedades`.
- Use the `~/Projects/…` form literally (never expanded to `/Users/…`) or the permission rules do not match.
- **Never `cd`.** Run every command from the repository root. Prefer Read / Glob / Grep over shell
  commands to inspect files.
- `.claude/settings.local.json` is personal: never commit it, never edit it (it is git-ignored).

### 3.3 Local-only state (do not "fix" it)
- Deactivated **only locally**: `really-simple-ssl`, `wp-optimize`, `google-site-kit`; local `advanced-cache.php` removed.
- Not copied (inactive in production): `fancy-elementor-gallery-box`, `jet-form-builder-save-progress`,
  `wp-rest-api-authentication`, `proteger-uploads`, theme `hello-elementor`.
- Mail blocked by `mu-plugins/000-local-safety.php`; cron disabled; external HTTP blocked; updates and file mods disabled.
- `wp-content/uploads` is **not** local: the directory exists but holds only ~30
  plugin-generated files, and every media request is redirected (302) to production.
  **Media file-existence checks cannot be validated locally** — implemented, with the limitation
  stated in `docs/progress.md`, `docs/reconciliation.md` and the consumer guide.
- **The two containers disagree about the environment type.** An HTTP request reports
  `wp_get_environment_type() === 'local'` (so the plugin correctly skips the media file check),
  while the same call inside the `wpcli` container reports `'production'` — `WORDPRESS_CONFIG_EXTRA`
  never reached `wp-config.php`, and the containers do not share that setting. Anything run through
  `wp eval-file` must therefore **not** branch on the environment type: `tools/bootstrap.php`
  disables the file check unconditionally and says why. Verified 13 Oct 2026 with a temporary
  response header.

### 3.4 Test credentials (local only, already created — never created or changed by us)
- `orbitas-dev` (ID 1883, `administrator`): gets the capability on activation → expected 200.
- `orbitas-noaccess` (ID 1884, `subscriber`): never has the capability → expected 403.

Exported by Gianfranco as `CCCBR_API_USER`, `CCCBR_API_APP_PASSWORD`, `CCCBR_API_NOACCESS_USER`,
`CCCBR_API_NOACCESS_APP_PASSWORD`. Use them **only** through these variables.
**Never print, log, write to a file or commit their values.**

### 3.5 Hard limits
- **Never** read `~/Projects/cccbr-replica/database.sql.gz` or any `.sql` file.
- **Never** contact production: no `ssh`, `scp`, `rsync`, no request to `camara.cr`, `www.camara.cr`, `tienda.camara.cr`.
- **Never** run `docker compose down -v`, `docker volume rm`, `docker system prune`, `git push`,
  `git push --force`, `git reset --hard`.
- **Never** modify other plugins, themes, mu-plugins, WordPress core or the replica configuration.
- **Personal data:** the local database contains real people. Use aggregate queries (counts, key
  names, IDs). Never print names, emails, phone numbers, national IDs or addresses in chat, logs,
  docs, tests, fixtures, examples or commit messages. **All examples are synthetic.**

---

## 4. Engineering standards

### 4.1 Architecture
- Main plugin file: header, `ABSPATH` guard, constants, autoloader include, one bootstrap call. No logic.
- Namespace `ElPuas\CCCBR\OrbitasPropiedades`, PSR-4 from `src/` via Composer.
  If `vendor/autoload.php` is missing: admin notice and do nothing. **Never fatal.**
- One responsibility per class: `Plugin` (wiring), `Rest\PropertiesController`, `Auth\Permissions`,
  `Lifecycle\Activator`, `Schema\FieldMap`, `Repository\PropertyRepository`,
  `Repository\RelationsRepository`, `Pagination\Cursor`, `Mapper\PropertyMapper`,
  `Mapper\MediaMapper`, `Mapper\LocationMapper`, `Mapper\PeopleMapper`, `Support\IssueCollector`.
- Constructor dependency injection. No singletons, no global state, no `global`, no static mutable state.
- Hook callbacks only delegate to services.
- `declare(strict_types=1);`, typed properties, parameter and return types, `final` by default, small methods.

### 4.2 WordPress
- WPCS 3 via PHPCS (`phpcs.xml.dist`) + **PHPCompatibilityWP with `testVersion` 8.1-**.
  Prefix everything (`cccbr_orbitas_`, `CCCBR_ORBITAS_`).
- `register_rest_route` on `rest_api_init`; real `permission_callback` (never `__return_true`);
  args with `type`, `sanitize_callback`, `validate_callback`; response schema via `get_item_schema`;
  `WP_REST_Response` / `WP_Error` with correct status codes.
- WordPress APIs first. `$wpdb` only where no API exists (keyset `ID > after_id` selection, JetEngine
  relation tables, aggregate counts), always with `$wpdb->prepare()` and `$wpdb->prefix`.
- **No `apply_filters( 'the_content' )`** on API output: raw stored content.
- Capability added on activation, removed in `uninstall.php` (guarded by `WP_UNINSTALL_PLUGIN`).
  Nothing else persisted.
- Header: name, description, version SemVer, `Requires at least: 6.9`, `Requires PHP: 8.1`,
  `Author: ElPuas Digital Crafts`, text domain `cccbr-orbitas-propiedades`, license.
- i18n for every human-readable string, error messages included.

### 4.3 Security
- Read-only; no side effects on GET.
- Unauthenticated → 401; authenticated without capability → 403; invalid args → 400.
- Explicit allow-list field map. Never return raw meta arrays or raw user objects.
- No secrets in code, config, docs, tests or fixtures.

### 4.4 Performance
- `per_page` hard cap 100.
- Select the page IDs, then prime caches in bulk (`update_meta_cache`, `update_object_term_cache`,
  `cache_users`, attachment post + meta caches). **No N+1 queries.**
- Status totals with one aggregate query per request.

### 4.5 Tooling
- Composer dev deps: `wp-coding-standards/wpcs`, `phpcompatibility/phpcompatibility-wp`,
  `dealerdirect/phpcodesniffer-composer-installer`, `phpstan/phpstan`,
  `szepeviktor/phpstan-wordpress`, `phpunit/phpunit`. Declare them in `composer.json` and run
  `composer install` (do not use `composer require`).
- Scripts: `composer lint`, `cs`, `cbf`, `stan`, `test`, and for releases
  `composer run build` / `composer run build:verify`.
- **The production zip must be built with `composer run build`**, never by zipping the working tree.
  It stages the files `.distignore` allows into a temporary directory, runs
  `composer install --no-dev --optimize-autoloader` **there**, drops the manifests, and zips the
  result — so the repository's dev `vendor/` is never disturbed. `vendor/` is shipped because the
  plugin requires `vendor/autoload.php` at runtime.
- PHPUnit covers pure units without WordPress (cursor, mappers with injected data, type coercion).
- `.gitignore`: `vendor/`, `node_modules/`, `*.sql`, `*.sql.gz`, `.env*`,
  `.claude/settings.local.json`, OS/editor files, Postman environment files.

### 4.6 Verification (against the running site, not only the source files)
- `php -l` on every changed PHP file.
- `composer cs`, `composer stan`, `composer test`.
- `logs wordpress --tail 50` after requests: no notices/warnings/errors from this plugin.
- `curl` checks against `http://localhost:8080`, reporting only status codes, field names, counts and IDs.

---

## 5. Documentation map

| File | Contents |
|---|---|
| `LOOP_PROMPT.md` | Session driver: how to run, task list, acceptance criteria |
| `docs/plan.md` | Task checklist (source of "what is next") |
| `docs/progress.md` | Append-only log: date, task, files, commands, VERIFIED / TO VERIFY facts |
| `docs/schema.md` | Real source schema: every meta key, counts, included/excluded + reason |
| `docs/contract.md` | Response contract (route, auth, args, fields, pagination, errors) |
| `docs/reconciliation.md` | Counts and IDs reconciled against the DB (aggregates only) |
| `docs/consumer-guide.md` | How Orbitas consumes the endpoint (synthetic examples) |
| `docs/security-review.md` | Executed security and privacy checks, with their results |
| `docs/OPEN-QUESTIONS.md` | Anything needing Gianfranco's or Orbitas' decision |
| `docs/REPORT.md` | Final report |

Never invent fields, keys, values, relations or results. Never claim a check passed without running it.
