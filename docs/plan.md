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

## Acceptance criteria (all must be ✅, with evidence, against the running local site)

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
