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
