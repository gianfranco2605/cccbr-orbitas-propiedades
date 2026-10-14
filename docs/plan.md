# Plan — CCCBR Orbitas Propiedades

Worked through in order. A task is done only when every applicable check in `LOOP_PROMPT.md` §7
passes, the box here is checked, `docs/progress.md` has an entry and the work is committed.

- [x] **Task 0 — Bootstrap.** Check `php -v`, `composer -V`, `git config user.name`. Create `CLAUDE.md`
  (durable rules: sections 2.3, 3, 5 and the hard limits), `docs/plan.md`, `docs/progress.md`,
  `docs/OPEN-QUESTIONS.md`, `README.md`, `CHANGELOG.md`, `.gitignore`, `composer.json`,
  `phpcs.xml.dist`, `phpstan.neon.dist`, the minimal main plugin file. `composer install`.
  Commit everything including `LOOP_PROMPT.md` and `.claude/settings.json`.
- [x] **Task 1 — Schema discovery** → `docs/schema.md`. Re-verify section 4. List **every** meta key on
  `cccbr-propiedades` with counts and classify it (included / excluded + reason). Resolve glossary
  storage, coordinate origin (JetEngine source), relation 171 storage, statuses present.
  Search user meta key names (counts only) for associate number and Marketplace / Orbitas IDs.
- [x] **Task 2 — Contract** → `docs/contract.md`: route, method, auth, permissions, args, every field
  (type, nullability, source key), pagination, limits, errors, consistency mechanism.
- [x] **Task 3 — Skeleton and lifecycle**: autoload, `Plugin`, activator, `uninstall.php`. Activate
  locally (§3.2), confirm the capability and no errors in the logs.
- [x] **Task 4 — Route, permissions, argument validation**: empty, correctly shaped response. Verify 401, 403, 400, 200.
- [x] **Task 5 — Repository and keyset pagination** with all statuses, `status` filter and totals.
  Walk all IDs: count equals the DB count, no duplicates.
- [x] **Task 6 — Core fields and meta mapping** (typed values, labels, empty vs missing, price, currency, features, trash fields).
- [x] **Task 7 — Location, coordinates, expiration, origin.**
- [x] **Task 8 — Media** (featured, ordered gallery, derivatives, issues).
- [x] **Task 9 — People and relations**: creator, responsible, company, associate / Marketplace IDs, lead count, missing authors.
- [x] **Task 10 — Performance**: cache priming, queries per page, timing for `per_page=100`. Record the numbers.
- [x] **Task 11 — Reconciliation tool** `tools/reconcile.php`, run with `wp eval-file … --skip-plugins --skip-themes`
  (it loads this plugin's autoloader itself and calls the controller through `rest_do_request()`
  as an administrator, so no HTTP credentials are needed). Compare totals and unique IDs with the DB,
  by status and by author; check creator → associate on a sample and on the whole set.
  Output → `docs/reconciliation.md` (aggregates only).
- [x] **Task 12 — Postman collection** `postman/cccbr-orbitas-propiedades.postman_collection.json`
  (variables for base URL and credentials, no secrets) with tests: first page, multiple pages,
  last page, zero results, `per_page` limit, invalid args, same cursor twice = same page, record added
  during the walk (manual step documented), 401, 403, no secret-like keys in the response.
  Run the same cases with `curl` and record the results.
- [x] **Task 13 — Security review**: scan code, docs, tests and sample responses for secrets and personal data; confirm the allow-list. Record evidence.
- [x] **Task 14 — Consumer guide** `docs/consumer-guide.md`: authentication, walking all pages, retries,
  field reference, synthetic examples, known limitations (local media checks, missing currency,
  properties without location). Final pass on `README.md`, `CHANGELOG.md`, `CLAUDE.md`.

---

## Acceptance criteria (all must be ✅, with evidence, against the running local site)

- [x] AC1 Contract documented from the real schema (route, method, auth, permissions, fields, types, pagination, limits, errors).
- [x] AC2 Full walk returns every property in the source; statuses documented; no implicit filter by date, publication or associate status.
- [x] AC3 Counts and unique IDs reconciled against the DB, total and by status / author; differences justified.
- [x] AC4 Complete fields, custom fields, taxonomy and images per property; missing items reported.
- [x] AC5 Property → creator → associate checked on a sample and in total; missing authors and ambiguous relations reported, nothing invented.
- [x] AC6 Tests for multiple pages, last page, zero results, limits, retries, records changed during extraction; consistency mechanism documented.
- [x] AC7 Unauthenticated → 401, no capability → 403; no secrets or personal data in responses, docs or examples.
- [x] AC8 Consumer documentation with synthetic examples.
- [x] Code: `composer cs` clean, `composer stan` clean, `composer test` green, PHP 8.1 compatible, no plugin entries in the logs.
- [x] `git status` clean, every task committed, nothing pushed, production untouched.

---

## Change request 1 — 2026-10-13

Gianfranco reviewed a real response and the code. Orbitas has not validated the contract yet, so the
payload starts lean and grows only on request. Changes 1–5 are **breaking** for consumers of 0.1.0;
6–8 are fixes. One commit per numbered change.

**Contract changes**

- [x] **CR1.1 — Media: drop `sizes`.** `media.featured` and every `media.gallery` item keep `id`,
  `url` (the original), `mime_type`, `width`, `height`, `alt`. Remove the code that only served sizes.
- [x] **CR1.2 — Numbers: plain values.** Replace every `{ raw, value }` pair (`price.sale`,
  `price.rent`, `price.final_sale`, `features.*`) with the number or `null`. An ambiguous or
  unparsable non-empty source value yields `null` plus the existing issue
  (`number_ambiguous` / `number_unparsable`) carrying the original raw string in `detail` — the only
  place a raw value now appears. `docs/OPEN-QUESTIONS.md` Q5 → ANSWERED.
- [x] **CR1.3 — Trash: one field.** Replace `historical_slug`, `status_before_trash` and
  `trashed_at_gmt` with `trash`: `null` unless the property is in the trash, otherwise
  `{ historical_slug, status_before_trash, trashed_at_gmt }`.
- [x] **CR1.4 — Location: one object.** Replace the `location` array with
  `{ province, canton, district }`, each `{ id, name, slug }` or `null`. Issues: `location_missing`,
  `location_incomplete`, `location_ambiguous`. Conflicting terms are never resolved by guessing: the
  affected level becomes `null` and the conflict is reported.
- [x] **CR1.5 — Drop `option_value_normalized`.** It reports a successful mapping, not a data
  problem. `option_value_unmapped` stays.

**Fixes from the code review**

- [x] **CR1.6 — Dates.** `created_at_gmt` / `modified_at_gmt` are `null` for a zero date, not `""`.
- [x] **CR1.7 — Race between the two queries.** `find_posts()` must not re-filter by status, so a
  property whose status changes between the two queries is still returned with its current status
  instead of silently vanishing while the cursor moves past it. An ID that cannot be loaded at all
  (deleted in between) is reported in `pagination.skipped_ids`, covered by a test and documented.
- [x] **CR1.8 — Composition root.** Build the object graph lazily inside the `rest_api_init`
  callback rather than on every WordPress request. The main plugin file keeps only the header, the
  `ABSPATH` guard, constants, the autoloader, the activation hook and one bootstrap call.

**Accompanying work**

- [x] Version 0.2.0 (header, constant, `CHANGELOG.md` "Changed" entry marking the breaking changes).
- [x] `docs/contract.md`, `Rest\PropertySchema`, PHPUnit tests, the Postman collection,
  `docs/consumer-guide.md` (synthetic examples only), `README.md` and `docs/REPORT.md`.
- [x] Consumer guide: note that the featured image **can also appear inside the gallery** — that is
  how the property was saved in the source — so consumers should de-duplicate by media `id`.
- [x] Re-run `tools/reconcile.php`, refresh `docs/reconciliation.md`, and add the number of
  properties per location issue code (aggregates only).

---

## Change request 2 — 2026-10-13 — exclude trashed properties

CCCBR decided that trashed properties must not be sent to Orbitas. This **overrides the "Statuses"
and "Trashed properties" rows** of the decisions table, both now marked superseded in `CLAUDE.md`.
One commit.

- [x] **CR2.1** — `trash` removed from the default statuses: the endpoint returns `publish`,
  `draft`, `pending`, `private` and `future` only.
- [x] **CR2.2** — the `status` filter rejects `trash` with a 400, like any other invalid value.
- [x] **CR2.3** — every trash-only field removed from the contract and the code: the `trash` object,
  historical slug, status before trash, trash date, and the readers of `_wp_desired_post_slug`,
  `_wp_trash_meta_status` and `_wp_trash_meta_time`.
- [x] **CR2.4** — `totals` no longer counts trashed properties.
- [x] **CR2.5** — `docs/contract.md`, `docs/schema.md`, `docs/consumer-guide.md`, `README.md`,
  `CHANGELOG.md`, `CLAUDE.md`, the unit tests and the Postman collection updated.
- [x] **CR2.6** — full checks re-run (lint, cs, stan, test) and `tools/reconcile.php` re-run with
  the new counts reported.
- [x] **CR2.7** — committed on `main`, not pushed.

---

## Change request 3 — 2026-10-13 — single property by ID

Orbitas must be able to refresh one property without reading the whole collection. This **extends
the "Route" row** of the decisions table, now updated in `CLAUDE.md`. One commit.

- [x] **CR3.1** — `GET /wp-json/cccbr-orbitas/v1/propiedades/(?P<id>\d+)` added to the existing
  controller, with the same authentication and the same `cccbr_orbitas_read_propiedades` capability.
- [x] **CR3.2** — the body is the property object, built by the same mappers as a collection item.
- [x] **CR3.3** — one `404` (`cccbr_orbitas_property_not_found`), same code and message, for an
  unknown id, another post type, a trashed property, and `auto-draft` / `inherit`.
- [x] **CR3.4** — `id` validated as a positive integer in the route args.
- [x] **CR3.5** — `docs/contract.md`, `docs/consumer-guide.md`, `README.md`, `CHANGELOG.md`,
  `CLAUDE.md`, `docs/REPORT.md`, the unit tests and the Postman collection updated.
- [x] **CR3.6** — `tools/reconcile.php` compares the single-property response with the collection
  item for a sample of ids.
- [x] **CR3.7** — lint, cs, stan, test and the reconciliation re-run and reported.
- [x] **CR3.8** — committed on `main`, not pushed.

---

## Change request 4 — 2026-10-14 — production build zip

- [x] **CR4.1** — `.distignore` listing every development-only file and folder, with a header
  recording what the zip *does* contain and why.
- [x] **CR4.2** — `composer run build` produces `dist/cccbr-orbitas-propiedades.zip` with the
  top-level folder `cccbr-orbitas-propiedades/`, built from a temporary copy in which
  `composer install --no-dev --optimize-autoloader` runs, so the repository's `vendor/` is untouched.
- [x] **CR4.3** — `dist/` added to `.gitignore`.
- [x] **CR4.4** — reported: the plugin **does** need `vendor/` at runtime; every file in the zip is
  listed; `composer run build:verify` asserts no dev dependency ships.
- [x] **CR4.5** — the zip was installed in the replica, the endpoint answered 200, and the
  repository copy was restored.
- [x] **CR4.6** — committed on `main`, not pushed.
