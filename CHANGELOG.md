# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] — 2026-10-13

First complete version: the endpoint, its documentation and its verification. Not deployed —
deployment to production is outside this scope.

### Added

**The endpoint**
- `GET /wp-json/cccbr-orbitas/v1/propiedades`: authenticated, read-only, collection only.
- WordPress Application Passwords (HTTP Basic) for authentication; the dedicated capability
  `cccbr_orbitas_read_propiedades` for authorization, granted to `administrator` on activation and
  removed from every role on uninstall. The plugin never creates users or passwords.
- Keyset pagination on ascending post id (`after_id`), `per_page` default 50 and maximum 100 —
  above the maximum is a `400`, never a silent clamp. `has_more` is derived from one row fetched
  beyond the page, so it stays correct while the data changes.
- Every status of the `cccbr-propiedades` post type by default, each item carrying its own status,
  with an optional `status` filter. `auto-draft` and `inherit` are never returned and are refused as
  filter values.
- Envelope with `data`, `pagination` (including `max_id` for snapshot consistency), `totals`
  (overall and per status, from one aggregate query) and `generated_at`.
- 38 fields per property: identity and lifecycle including the trash fields, classification as
  `{ key, label }`, prices and features as `{ raw, value }`, location with its full
  province/cantón/distrito path, geocoded coordinates, featured image and ordered gallery with
  derivatives, private-document booleans, creator/responsible/company, associate id, lead count,
  origin, and a per-property `issues` list of 21 stable data-quality codes.
- The full JSON Schema published through `OPTIONS` on the route.

**Correctness under real data**
- A tolerant normalizer for select and checkbox values: the source stores them in six different
  shapes and mixes option keys, labels, folded labels, slugs and free text. Unresolvable tokens are
  returned verbatim with `label: null` and reported; no label is ever invented.
- A conservative number parser: a number is produced only when the stored text has exactly one
  possible reading. `150.000` and `150,000` are reported as ambiguous rather than guessed, and the
  raw string is always preserved.
- Explicit allow-lists for property meta and for user fields; raw meta arrays and user objects are
  never returned.

**Verification**
- `tools/reconcile.php` — walks the whole collection through `rest_do_request()` and compares it
  against the database: **21 of 21 checks pass**, with 3055 properties returned, no omissions, no
  duplicates and every field-level count matching.
- `tools/measure-performance.php` — a page of 100 properties costs 4 warm queries (20 cold) and the
  count does not grow with the page size.
- 71 PHPUnit unit tests over the cursor, the number parser, the option normalizer, the id parser and
  the coordinate mapper.
- A Postman collection with 21 requests and 62 assertions, containing no credentials.

**Documentation**
- `docs/schema.md`, `docs/contract.md`, `docs/consumer-guide.md`, `docs/reconciliation.md`,
  `docs/security-review.md`, `docs/OPEN-QUESTIONS.md`, `docs/REPORT.md`, plus `CLAUDE.md` and a
  per-task log in `docs/progress.md`.

### Tooling
- Composer with PSR-4 autoloading from `src/`, platform pinned to PHP 8.1.13.
- WPCS 3 via PHPCS with PHPCompatibilityWP at `testVersion 8.1-`; PHPStan level 8 with WordPress
  stubs; PHPUnit for WordPress-free unit tests. `composer lint`, `cs`, `cbf`, `stan`, `test`.

[0.1.0]: https://github.com/gianfranco2605/cccbr-orbitas-propiedades/releases/tag/v0.1.0
