# CCCBR Orbitas Propiedades

WordPress plugin for CCCBR (Cámara Costarricense de Corredores de Bienes Raíces) that exposes one
**public REST endpoint** so the Orbitas team can pull every CCCBR property — complete
property data, images, relations and the data of the user who created each property — and import
them into their Marketplace.

- **Author:** ElPuas Digital Crafts
- **Version:** 0.5.0
- **Requires:** WordPress 6.9+, PHP 8.1+ (production runs PHP 8.1.13)
- **License:** GPL-2.0-or-later

## Endpoints

```
GET /wp-json/cccbr-orbitas/v1/propiedades          # the collection, keyset-paginated
GET /wp-json/cccbr-orbitas/v1/propiedades/{id}     # one property, for refreshing a single record
```

Both routes answer `GET` (and `HEAD`). No writable method is registered.

- **Access: public.** No authentication and no capability — anyone with the URL can read the data
  (decision of 14 Oct 2026). The endpoint is meant to be removed once the migration is done.
  Responses carry `Cache-Control: no-store`, so no intermediary retains them.
- **Pagination:** keyset cursor on ascending post ID (`after_id`); `per_page` default 50, maximum 100.
- **Scope:** every `cccbr-propiedades` property that is **not in the trash** — 2672 today — each item carrying its own status. Trashed properties are excluded by CCCBR's decision: `?status=trash` is a `400`, and asking for a trashed id gives the same `404` as an unknown one.
- **Both routes return the identical property object**, verified on a sample by `tools/reconcile.php`.

**Building the import?** Start with [`docs/consumer-guide.md`](docs/consumer-guide.md) — how to walk
all 2672 properties without omissions or duplicates, how to retry, and a measured list of what the
source data does not contain. The exhaustive contract is
[`docs/contract.md`](docs/contract.md), and [`docs/REPORT.md`](docs/REPORT.md) summarises what was
built and how it was verified.

## Installation

```bash
composer install --no-dev   # production
composer install            # development (adds PHPCS, PHPStan, PHPUnit)
```

Then activate the plugin. Without `vendor/autoload.php` the plugin shows an admin notice and does
nothing — it never fatals.

## Development

```bash
composer lint   # php -l on every PHP file
composer cs     # WPCS 3 + PHPCompatibilityWP (testVersion 8.1-)
composer cbf    # auto-fix coding standards
composer stan   # PHPStan level 8
composer test   # PHPUnit unit suite (no WordPress bootstrap)
```

## Building for production

```bash
composer run build          # -> dist/cccbr-orbitas-propiedades.zip
composer run build:verify   # list the archive and assert it is shippable
```

The zip has a single top-level folder, `cccbr-orbitas-propiedades/`, so WordPress installs it under
that slug. It contains the plugin, `uninstall.php`, `src/`, `README.md`, `CHANGELOG.md` and
`vendor/` — **37 files, ~72 KB**.

`vendor/` ships because the plugin needs it at runtime: the main file includes
`vendor/autoload.php` and shows an admin notice instead of doing anything when it is missing. After
`composer install --no-dev` it holds nothing but Composer's own autoloader, because the plugin has
no production dependencies.

Everything development-only is listed in [`.distignore`](.distignore) — tests, tools, docs, the
Composer manifests and every tool config. The build works on a **temporary
copy** and runs `composer install --no-dev --optimize-autoloader` there, so the repository's own
`vendor/` (PHPCS, PHPStan, PHPUnit) is never touched and `composer cs` keeps working straight after
a build.

Working conventions, the local replica setup and the hard limits are documented in
[`CLAUDE.md`](CLAUDE.md). Progress and verified facts are logged in
[`docs/progress.md`](docs/progress.md).

## Documentation

| File | Contents |
|---|---|
| [`docs/plan.md`](docs/plan.md) | Task checklist and acceptance criteria |
| [`docs/progress.md`](docs/progress.md) | Append-only work log with verified facts |
| `docs/schema.md` | Real source schema: meta keys, counts, included/excluded + reason |
| `docs/contract.md` | Response contract |
| `docs/reconciliation.md` | Counts and IDs reconciled against the database |
| `docs/consumer-guide.md` | How Orbitas consumes the endpoint |
| `docs/security-review.md` | Executed security and privacy checks, with their results |
| [`docs/OPEN-QUESTIONS.md`](docs/OPEN-QUESTIONS.md) | Decisions needed from CCCBR or Orbitas |
| [`docs/REPORT.md`](docs/REPORT.md) | Final report: what was built, the criteria with evidence, limitations |

## Privacy

Only user ID, display name and email are exposed for people. Passwords, hashes, tokens, keys,
logins, sessions, phone numbers and national IDs are never exposed. Private property documents
(`foto-del-contrato`, `documentos-anexos`) are reduced to a boolean, and lead data to a count.
Trashed properties are not exported at all. Every example in this repository is **synthetic**.
