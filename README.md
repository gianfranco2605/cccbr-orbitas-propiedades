# CCCBR Orbitas Propiedades

WordPress plugin for CCCBR (Cámara Costarricense de Corredores de Bienes Raíces) that exposes one
**authenticated, read-only REST endpoint** so the Orbitas team can pull every CCCBR property —
complete property data, images, relations and the data of the user who created each property — and
import them into their Marketplace.

- **Author:** ElPuas Digital Crafts
- **Version:** 0.1.0
- **Requires:** WordPress 6.9+, PHP 8.1+ (production runs PHP 8.1.13)
- **License:** GPL-2.0-or-later

## Endpoint

```
GET /wp-json/cccbr-orbitas/v1/propiedades
```

- **Authentication:** WordPress Application Passwords (HTTP Basic).
- **Authorization:** capability `cccbr_orbitas_read_propiedades` (granted to `administrator` on
  activation, removed on uninstall). The plugin never creates users or passwords.
- **Pagination:** keyset cursor on ascending post ID (`after_id`); `per_page` default 50, maximum 100.
- **Scope:** all statuses of the `cccbr-propiedades` CPT, each item carrying its own status.

The full request/response contract lives in [`docs/contract.md`](docs/contract.md) and the
integration walkthrough in [`docs/consumer-guide.md`](docs/consumer-guide.md).

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
| [`docs/OPEN-QUESTIONS.md`](docs/OPEN-QUESTIONS.md) | Decisions needed from CCCBR or Orbitas |

## Privacy

Only user ID, display name and email are exposed for people. Passwords, hashes, tokens, keys,
logins, sessions, phone numbers and national IDs are never exposed. Private property documents
(`foto-del-contrato`, `documentos-anexos`) are reduced to a boolean, and lead data to a count.
Every example in this repository is **synthetic**.
