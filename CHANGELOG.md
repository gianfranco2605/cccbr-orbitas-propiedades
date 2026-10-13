# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.0] — 2026-10-13

Change request 3. Orbitas must be able to refresh one property without reading the whole collection.
**Purely additive** — nothing about the collection route or the property object changed.

### Added

- **`GET /wp-json/cccbr-orbitas/v1/propiedades/{id}`.** Same authentication (Application Passwords)
  and same capability (`cccbr_orbitas_read_propiedades`) as the collection. The body is the
  **property object itself**, with no envelope.
- The response is built by the **same repository and the same mappers** as a collection item, so a
  property is identical in both routes — down to field order. `tools/reconcile.php` now verifies
  that with `===` on a sample of ids drawn from the walk (**27 of 27 identical**), which makes it a
  checked fact rather than an assumption.
- `OPTIONS …/propiedades/{id}` publishes the item schema (`cccbr_orbitas_propiedad`).
- `Rest\QueryArgs::is_positive_integer()`, a pure rule covered by **17 new unit tests**, and
  `validate_id()` which wraps it for the route.

### Behaviour

- **One `404` for every "no such property" case**, with the same code
  (`cccbr_orbitas_property_not_found`) and the same message whether the id is unknown, belongs to
  another post type, is a **trashed** property, or is an `auto-draft` / `inherit` row. Telling them
  apart would let an authorized caller probe the site's contents through the differences, and an
  importer has nothing to do differently in any of those cases.
- `id` is validated as a positive integer: `/propiedades/0` is a `400` with
  `data.details.id.code = cccbr_orbitas_invalid_id`, because the route pattern `(?P<id>\d+)` matches
  `0` on its own. `/propiedades/abc` does not match the pattern at all and is WordPress's
  `rest_no_route`.
- Only a readable method is registered, so `POST` to the single route is refused like on the
  collection.

### Verified

lint, cs and stan clean; **112 tests / 290 assertions**; reconciliation **22 of 22 checks pass**
including the new route-identity check; `200` for a real property, `404` for an unknown id, a
non-property post, the WPUF form and a trashed property — all four with the identical body; `400`
for `id=0`; `401` without credentials and `403` without the capability; no plugin entries in the log.

## [0.3.0] — 2026-10-13

Change request 2. **CCCBR decided that trashed properties must not be sent to Orbitas.** This
overrides the "Statuses" and "Trashed properties" rows of the project's decisions table, both of
which are now marked as superseded in `CLAUDE.md`.

### Removed — **breaking for anyone integrated against 0.2.0**

- **Trashed properties are no longer exported.** `trash` is gone from the default status set, so a
  plain request returns **2672** properties instead of 3055.
- **`?status=trash` is rejected** with `400` / `cccbr_orbitas_invalid_status`, like any other value
  outside the allowed set — and so is any list containing it, such as `?status=publish,trash`.
- **`totals` no longer counts trashed properties.** `totals.total` is 2672 and `by_status` holds
  only `publish` 2612 and `draft` 60.
- **The `trash` object is removed from the item**, with it the historical slug, the status before
  trashing and the trash date. A property object now has **35 fields** (was 36).
- The code that read `_wp_desired_post_slug`, `_wp_trash_meta_status` and `_wp_trash_meta_time` is
  deleted, along with `PropertyMapper::map_trash()` and the `nullable_string()` helper that existed
  only for those keys. The three keys are listed as **excluded** in `docs/schema.md` §3.2.

All four behaviours come from one constant, `Rest\QueryArgs::ALLOWED_STATUSES`, which is now guarded
by `tests/unit/QueryArgsTest.php`.

### Changed

- `tools/reconcile.php` applies the exported status filter to **every** database comparison query,
  through a new `cccbr_orbitas_prepare_with_statuses()` helper. Without it the database side would
  report 3055 against the endpoint's 2672 and look like a defect.
- The reconciliation report states the population it measured.

### Verified

Reconciliation re-run: **21 of 21 checks pass** over 27 pages — 2672 properties, no omissions, no
duplicates, no phantom ids, `skipped_ids` empty, and every field-level count matching the database
for the exported population: 248 distinct authors, 1653 with a location, 2623 with a featured image,
476 with an expiration, 2194 from WP User Frontend, 129 with a contract photo, 16 with leads
(20 leads), 405 with a company and 8 without an associate number.

## [0.2.0] — 2026-10-13

Change request 1, after Gianfranco reviewed a real response and the code. Orbitas has not validated
the contract yet, so the payload was trimmed to what they are known to need; it can grow again on
request.

### Changed — **breaking for anyone integrated against 0.1.0**

- **Media: image derivatives removed.** `media.featured` and every `media.gallery` item now carry
  only `id`, `url` (the original), `mime_type`, `width`, `height` and `alt`. The generated thumbnail
  sizes added tens of thousands of URLs per page for data the importer would most likely discard.
- **Numbers: plain values.** `price.sale`, `price.rent`, `price.final_sale` and all seven
  `features.*` are a number or `null` instead of `{ raw, value }`. When a non-empty source value
  cannot be parsed, the field is `null` and the **original string travels in the issue's `detail`**
  (`number_ambiguous` / `number_unparsable`) — the only place a raw source value now appears.
- **Trash: one field.** `historical_slug`, `status_before_trash` and `trashed_at_gmt` are replaced
  by `trash`: `null` unless the property is in the trash, otherwise an object with those three
  members. They were `null` on 2672 of 3055 properties as top-level fields.
- **Location: one named object.** `location` is `{ province, canton, district }`, each
  `{ id, name, slug }` or `null`, instead of an array of terms carrying a `level` number. The
  property form labels its three selects "Provincia / Cantón / Distrito", so the taxonomy depth is
  the administrative level. New issue codes `location_incomplete` and `location_ambiguous`; a
  conflict clears the affected level and is reported rather than resolved by guessing.
- **`option_value_normalized` removed.** It reported a *successful* mapping, fired on 367
  properties, and drowned the codes that need action. `option_value_unmapped` stays.
- A property object now has **36 fields** (was 38); the issue vocabulary is **22 codes** (was 21).

### Added

- `pagination.skipped_ids`: ids a page selected but could not load because the property was deleted
  between the endpoint's two queries. Normally empty.
- `docs/contract.md` §5.3 and §5.4 documenting that mechanism.

### Fixed

- **A property whose status changed mid-request was silently lost.** `find_posts()` re-applied the
  status filter that had already selected the ids, so a property published, drafted or trashed
  between the two queries matched the first and not the second — and the cursor had already moved
  past its id. It now loads by id across every allowed status, so a selected property is always
  returned with its current status.
- `created_at_gmt` and `modified_at_gmt` return `null` for a zero date instead of `""`, consistent
  with the rest of the contract.
- The object graph is built lazily inside the `rest_api_init` callback instead of on every WordPress
  request. The new `Factory` is the single composition root, shared with the CLI tools.

### Internal

- New pure, unit-tested `Support\LocationResolver` (13 tests) and `Pagination\Cursor::skipped_ids()`
  (5 tests). `OptionNormalizer` lost its `exact` flag, `PropertyMapper` lost `strip_state()`, and
  `MediaMapper` lost `sizes()`. The suite is at **90 tests / 255 assertions**.
- The plugin-header test no longer pins the version number, only its SemVer format and its agreement
  with `CCCBR_ORBITAS_VERSION`.

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

[0.4.0]: https://github.com/gianfranco2605/cccbr-orbitas-propiedades/releases/tag/v0.4.0
[0.3.0]: https://github.com/gianfranco2605/cccbr-orbitas-propiedades/releases/tag/v0.3.0
[0.2.0]: https://github.com/gianfranco2605/cccbr-orbitas-propiedades/releases/tag/v0.2.0
[0.1.0]: https://github.com/gianfranco2605/cccbr-orbitas-propiedades/releases/tag/v0.1.0
