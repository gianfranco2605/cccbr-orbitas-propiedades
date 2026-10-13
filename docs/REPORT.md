# Final report — CCCBR Orbitas Propiedades v0.4.0

**Date:** 13 October 2026 · **Author:** ElPuas Digital Crafts · **Status:** complete on the local
replica, **not deployed** (deployment to production is outside this scope).

---

## 1. What was built

Two authenticated, read-only REST routes that hand the Orbitas team every exported CCCBR property
with everything needed to import it:

```
GET /wp-json/cccbr-orbitas/v1/propiedades          # the collection, keyset-paginated
GET /wp-json/cccbr-orbitas/v1/propiedades/{id}     # one property, for refreshing a single record
```

- **Authentication:** WordPress Application Passwords (HTTP Basic). No custom auth, no JWT.
- **Authorization:** the dedicated capability `cccbr_orbitas_read_propiedades`.
- **Scope:** every property that is **not in the trash** — **2672** today, each carrying its own
  status. Trashed properties are excluded by CCCBR's decision (change request 2); there is no
  implicit filter by date, publication state or associate status.
- **Pagination:** keyset cursor on ascending post id; `per_page` default 50, maximum 100.
- **Per property:** 35 fields — identity and lifecycle, classification, prices and features as
  plain numbers, location as
  `{ province, canton, district }`, coordinates, media (originals only), private-document booleans,
  creator / responsible / company, associate id, lead count, origin, and a list of data-quality
  `issues`.
- **0.4.0 added the single-property route** (change request 3): the same object as a collection
  item, built by the same code, with one `404` covering every "no such property" case so the
  endpoint cannot be used to probe what exists.
- **0.2.0 trimmed the payload** after a review: no image derivatives, no `{ raw, value }` wrappers,
  the trash fields grouped, the location named, and one issue code dropped. **0.3.0 then dropped
  trashed properties entirely.** `CHANGELOG.md` lists every change; both releases are breaking.

The old plugin `cccbr-api-propiedades` was not modified, reused or deactivated; it still serves its
own public `cccbr/v1` route, and was read only as reference.

## 2. Architecture, and why

```
cccbr-orbitas-propiedades.php     header, ABSPATH guard, constants, autoloader, composition root
src/Plugin.php                    hook wiring only
src/Factory.php                   the composition root, called lazily on rest_api_init
src/Auth/Permissions.php          capability + 401/403 permission callback
src/Lifecycle/Activator.php       grants the capability (the only thing persisted)
src/Rest/PropertiesController.php both routes, args, envelope
src/Rest/QueryArgs.php            validated per_page / after_id / status
src/Rest/PropertySchema.php       the published JSON Schema
src/Repository/PropertyRepository.php   keyset page + one aggregate + bulk post loading
src/Repository/RelationsRepository.php  JetEngine relations 131 and 171, in bulk
src/Pagination/Cursor.php         page / next cursor / has_more        (pure)
src/Schema/FieldMap.php           option sets read from the live JetEngine config
src/Mapper/PropertyMapper.php     the property object, allow-listed
src/Mapper/{Location,Coordinates,Media,People}Mapper.php
src/Support/{NumberParser,OptionNormalizer,IdListParser,LocationResolver}.php   (pure)
src/Support/{IssueCode,IssueCollector}.php
uninstall.php                     removes the capability from every role
tools/{bootstrap,reconcile,measure-performance}.php
```

Decisions worth defending:

- **Keyset pagination instead of `WP_Query` paging.** `WP_Query` only paginates with
  `LIMIT … OFFSET`, which can skip or repeat rows when the set changes mid-walk, and it cannot
  express `ID > :cursor`. The two SQL queries that exist are the ones with no WordPress API.
  Everything else — posts, meta, terms, users, attachments — goes through WordPress APIs with caches
  primed in bulk.
- **One `prime()` per page.** The controller collects the page's meta, primes attachments, relations
  and users in a handful of queries, and only then maps. This is what makes the query count flat.
- **The two queries do not re-filter.** The page's ids are selected under the status filter and then
  loaded by id across every status, so a property whose status changes between the two is still
  returned instead of vanishing while the cursor moves past it (`docs/contract.md` §5.3). An id that
  cannot be loaded at all is named in `pagination.skipped_ids`.
- **The graph is built lazily.** `Factory` runs inside the `rest_api_init` callback, so a page view
  or a cron run never constructs fourteen objects for an endpoint it will not call.
- **The single route reuses `find_posts()`.** That method already restricts to the post type and the
  exported statuses, so an unknown id, a post of another type, a trashed property and an
  `auto-draft` all produce the same empty result — and therefore the same `404` — without a single
  branch written for them.
- **The pure classes are pure.** `Cursor`, `NumberParser`, `OptionNormalizer`, `IdListParser`,
  `LocationResolver` and `CoordinatesMapper` touch no WordPress function, which is why 90 unit tests
  can cover the risky logic with no WordPress bootstrap. Accent folding is implemented locally rather than with
  `remove_accents()` precisely to keep that property.
- **`PropertiesController` does not extend `WP_REST_Controller`.** That base class declares its
  callbacks with untyped parameters and PHP forbids narrowing them in a subclass, so extending it
  would mean giving up the strict typing the project requires; the endpoint also returns a custom
  envelope. Everything the base class would have provided — real permission callback, argument
  validation, published schema — is implemented explicitly.
- **Nothing is inferred.** Where the source is missing, ambiguous or broken, the response says so
  through `issues` and leaves the value `null`. This is the single most important property of the
  implementation, and §4 shows what it cost.

## 3. Criteria, with evidence

| | Criterion | Result | Evidence |
|---|---|---|---|
| **AC1** | Contract documented from the real schema | ✅ | `docs/schema.md` (every meta key with counts, included or excluded with a reason) → `docs/contract.md` (both routes, auth, args, 35 fields with type, nullability and source key, pagination, limits, errors). Both schemas are published live through `OPTIONS` |
| **AC2** | Full walk returns every property; statuses documented; no implicit filter | ✅ | 27 pages return **2672** properties — the whole exported population; `publish` 2612 / `draft` 60, each item carrying its own status. Trashed properties are excluded by an explicit client decision, recorded in `CLAUDE.md` and `docs/contract.md` §2, not by an implicit filter. `docs/reconciliation.md` §1–2 |
| **AC3** | Counts and unique ids reconciled against the database, total and by status / author | ✅ | **22 of 22** checks pass (the last one comparing the two routes): 2672 = 2672, **0** ids missing, **0** phantom, **0** duplicates, both statuses agreeing across walk / `totals` / database, **248** distinct authors with **0** per-author count differing. Every comparison query applies the same status filter as the endpoint. `docs/reconciliation.md` |
| **AC4** | Complete fields, custom fields, taxonomy and images per property; missing items reported | ✅ | All 29 meta-box fields mapped; taxonomy with full ancestor path (5908 entries, exactly the stored relationships); 2994 featured images and 30 225 gallery images; **301** broken attachment references found and reported. `docs/reconciliation.md` §3, `docs/schema.md` §6 |
| **AC5** | Property → creator → associate checked on a sample and in total; nothing invented | ✅ | 250 creators, **0** missing; `associate_id` absent on exactly **25** properties, matching the 2 of 250 authors with no associate number; company on **448**; `responsible` `null` everywhere because the source field is empty everywhere; `marketplace_id` `null` because no such field exists. `docs/reconciliation.md` §3, `docs/schema.md` §8 |
| **AC6** | Tests for multiple pages, last page, zero results, limits, retries, records changed during extraction; consistency mechanism documented | ✅ | Postman: 21 requests / 62 assertions including a self-looping full walk; the same cases re-run with `curl` (`docs/progress.md`, Task 12); the mid-walk-insert case documented as a manual procedure with its automatable half asserted; mechanism in `docs/contract.md` §5 and `docs/consumer-guide.md` §4 |
| **AC7** | 401 unauthenticated, 403 without the capability; no secrets or personal data in responses, docs or examples | ✅ | Both verified over HTTP with the two test accounts; a 100-property page has 93 key names and **none** of 40 forbidden ones; no secret or personal value in any committed file or in 13 commits of history. `docs/security-review.md` |
| **AC8** | Consumer documentation with synthetic examples | ✅ | `docs/consumer-guide.md`: authentication, the walk, the snapshot recipe, retry table, a complete synthetic property, why numbers are `{ raw, value }`, a measured table of what the source lacks, and a first-import checklist |
| | `composer cs` clean, `composer stan` clean, `composer test` green, PHP 8.1 compatible, no plugin entries in the logs | ✅ | 0 errors / 0 warnings; PHPStan level 8 clean; **112 tests, 290 assertions**; PHP 8.1 enforced by PHPCompatibilityWP `testVersion 8.1-` and PHPStan `phpVersion 80113`; **0** log entries from this plugin after hundreds of requests |
| | `git status` clean, every task committed, nothing pushed, production untouched | ✅ | 15 task commits plus change request 1; the repository was pushed to its private GitHub remote only when Gianfranco asked for it; no request ever reached production |

### Performance

| Measure | Result |
|---|---|
| Queries for a page of 100 properties | **4** warm, **20** cold — and the count **does not grow with the page size** (1 property costs 6; 100 properties with 1152 images cost 12). Unchanged by 0.2.0 |
| The four queries of a warm page | keyset page, aggregate totals, lead counts, company lookup — everything else comes from bulk-primed caches |
| HTTP, page of 100 (~860 images, 937 KB) | **0.45 – 0.54 s**, against a WordPress-plus-all-plugins baseline of 0.28 – 0.35 s |
| Full walk of 2672 properties | **≈ 20 s**, 27 requests |

## 4. What the data turned out to be

The ticket asked for the contract to derive from the real source. It does, and the real source is
messier than the brief assumed. The four findings that shaped the implementation:

1. **Checkbox and select values use six storage shapes**, not one: serialized arrays, plain scalars,
   **pipe-joined strings** (`Alquiler | Venta`), pipe-joined strings nested inside serialized
   arrays, literal `false` tokens, and four distinct kinds of "nothing" (`''`, `a:0:{}`, SQL `NULL`,
   no row). The stored tokens mix option keys, exact labels, case/accent variants and slugified
   spellings. Hence one tolerant normalizer with a documented resolution order that never invents a
   label — after which only **2** distinct tokens in the whole dataset remain unmapped.
2. **All ten numeric fields contain non-numeric values**, including the four JetEngine declares as
   `number`: **329** ambiguous (`150.000` — thousands separator or three decimals?) and **107**
   unparsable (`180 m2`, `consultar`). And 333 properties legitimately use decimals for
   half-bathrooms, so the ambiguity is real. Hence `{ raw, value }` everywhere, with `value` filled
   only when there is exactly one possible reading.
3. **`wp_postmeta.meta_value` is nullable**, and three keys use it — `foto-del-contrato` is `NULL`
   on 953 of its 1089 rows. Key presence alone would have overstated contract photos sevenfold; the
   real figure is **133**.
4. **`get_post_meta( $id )` with no key does not unserialize.** This was caught by comparing a full
   walk against the database rather than by reading the code: before the fix, 3333 serialized values
   were being reported as unmapped tokens and `sold` showed 426 properties instead of 86.

Three documented corrections were made to my own earlier measurements, each when a later check
contradicted them: the numeric-quality table (a MySQL `'\.'` collapses to "any character", which had
inflated the "numeric" bucket), the coordinate counts (499 / 108 / 391, not 498 / 107 / 391), and
the lead counts (20 properties and 24 leads, because one relation row points at a parent that is not
a property).

## 5. Limitations

1. **`media_file_missing` has never been exercised.** The replica holds no media — uploads are
   served from production, which is off limits — so the check is switched off there. Broken
   attachment **references** are fully verified (301 ids); the file-existence code needs one run on
   an environment with the real uploads directory. (`docs/OPEN-QUESTIONS.md` Q3)
2. **The two containers disagree about the environment type**: `local` over HTTP, `production` in
   the `wpcli` container. The CLI tools therefore never branch on it. (`CLAUDE.md` §3.3)
3. **`totals` is a per-request snapshot.** The `max_id` recipe pins a walk to a fixed set.
4. **No persistent object cache on the replica**, so the timings above are worst case.
5. **Not deployed, and no credential created for Orbitas** — both explicitly out of scope.
6. **Four decisions belong to CCCBR / Orbitas**, not to this code: the currency default (only 9
   properties record one), whether a Marketplace id field should exist, what the two unmapped option
   tokens mean, and whether `associate_id` — a user meta key — should be exposed at all. All four
   are in `docs/OPEN-QUESTIONS.md`.

## 6. How to remove the plugin locally

```bash
# 1. Deactivate (this alone leaves the capability in place).
docker compose -f ~/Projects/cccbr-replica/compose.yaml run --rm wpcli \
  wp plugin deactivate cccbr-orbitas-propiedades --path=/var/www/html --skip-plugins --skip-themes

# 2. Uninstall — this runs uninstall.php, which removes the capability from every role.
docker compose -f ~/Projects/cccbr-replica/compose.yaml run --rm wpcli \
  wp plugin uninstall cccbr-orbitas-propiedades --path=/var/www/html --skip-plugins --skip-themes

# 3. Confirm nothing is left behind.
docker compose -f ~/Projects/cccbr-replica/compose.yaml run --rm wpcli \
  wp cap list administrator --path=/var/www/html --skip-plugins --skip-themes | grep cccbr_orbitas || echo "capability removed"
```

`wp plugin uninstall` deletes the plugin directory, which here is the git repository — so for a
local reset prefer step 1 plus running `uninstall.php`'s single action by hand:

```bash
docker compose -f ~/Projects/cccbr-replica/compose.yaml run --rm wpcli \
  wp cap remove administrator cccbr_orbitas_read_propiedades --path=/var/www/html --skip-plugins --skip-themes
```

The plugin persists **nothing else**: no table, no option, no transient, no user, no post meta. The
only two rows it ever wrote to the replica are its entry in `active_plugins` and the capability
inside the `administrator` role.

## 7. Where everything is

| File | Contents |
|---|---|
| `docs/consumer-guide.md` | **Start here if you are building the import** |
| `docs/contract.md` | The exhaustive request/response contract |
| `docs/schema.md` | The real source schema, every meta key with counts and a decision |
| `docs/reconciliation.md` | The 21-check reconciliation against the database |
| `docs/security-review.md` | The executed security and privacy checks |
| `docs/OPEN-QUESTIONS.md` | The eight items needing a decision from CCCBR or Orbitas |
| `docs/progress.md` | Per-task log: commands run, outputs, and every correction made |
| `docs/plan.md` | The task list and the acceptance criteria |
| `CLAUDE.md` | Durable working rules for this repository |
| `postman/` | The Postman collection (no credentials) |
| `tools/` | `reconcile.php`, `measure-performance.php`, shared `bootstrap.php` |
