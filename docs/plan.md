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
- [ ] **CR1.2 — Numbers: plain values.** Replace every `{ raw, value }` pair (`price.sale`,
  `price.rent`, `price.final_sale`, `features.*`) with the number or `null`. An ambiguous or
  unparsable non-empty source value yields `null` plus the existing issue
  (`number_ambiguous` / `number_unparsable`) carrying the original raw string in `detail` — the only
  place a raw value now appears. `docs/OPEN-QUESTIONS.md` Q5 → ANSWERED.
- [ ] **CR1.3 — Trash: one field.** Replace `historical_slug`, `status_before_trash` and
  `trashed_at_gmt` with `trash`: `null` unless the property is in the trash, otherwise
  `{ historical_slug, status_before_trash, trashed_at_gmt }`.
- [ ] **CR1.4 — Location: one object.** Replace the `location` array with
  `{ province, canton, district }`, each `{ id, name, slug }` or `null`. Issues: `location_missing`,
  `location_incomplete`, `location_ambiguous`. Conflicting terms are never resolved by guessing: the
  affected level becomes `null` and the conflict is reported.
- [ ] **CR1.5 — Drop `option_value_normalized`.** It reports a successful mapping, not a data
  problem. `option_value_unmapped` stays.

**Fixes from the code review**

- [ ] **CR1.6 — Dates.** `created_at_gmt` / `modified_at_gmt` are `null` for a zero date, not `""`.
- [ ] **CR1.7 — Race between the two queries.** `find_posts()` must not re-filter by status, so a
  property whose status changes between the two queries is still returned with its current status
  instead of silently vanishing while the cursor moves past it. An ID that cannot be loaded at all
  (deleted in between) is reported in `pagination.skipped_ids`, covered by a test and documented.
- [ ] **CR1.8 — Composition root.** Build the object graph lazily inside the `rest_api_init`
  callback rather than on every WordPress request. The main plugin file keeps only the header, the
  `ABSPATH` guard, constants, the autoloader, the activation hook and one bootstrap call.

**Accompanying work**

- [ ] Version 0.2.0 (header, constant, `CHANGELOG.md` "Changed" entry marking the breaking changes).
- [ ] `docs/contract.md`, `Rest\PropertySchema`, PHPUnit tests, the Postman collection,
  `docs/consumer-guide.md` (synthetic examples only), `README.md` and `docs/REPORT.md`.
- [ ] Consumer guide: note that the featured image **can also appear inside the gallery** — that is
  how the property was saved in the source — so consumers should de-duplicate by media `id`.
- [ ] Re-run `tools/reconcile.php`, refresh `docs/reconciliation.md`, and add the number of
  properties per location issue code (aggregates only).
