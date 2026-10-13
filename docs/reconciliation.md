# Reconciliation — `GET /wp-json/cccbr-orbitas/v1/propiedades`

Produced by `tools/reconcile.php` on **2026-10-13 18:40:13 UTC**, against the local replica.
The tool walks the whole collection through `rest_do_request()` as an administrator, then
reads the same facts straight from the database and compares them. It writes nothing, and
reports only counts, identifiers and booleans — never a personal value.

**Population:** properties whose status is one of `publish`, `draft`, `pending`, `private`, `future`. **Trashed properties are excluded**
from the export by CCCBR's decision, so every query below applies the same filter — a
comparison against the unfiltered table would measure two different populations.

## 1. The walk

| Measure | Value |
|---|---|
| Pages walked (`per_page=100`) | 27 |
| Properties returned | 2672 |
| Distinct ids returned | 2672 |
| Duplicate ids | 0 |
| Ids in ascending order across the whole walk | yes |
| `pagination.max_id` reported | 184589 |
| Ids selected but not loadable (`pagination.skipped_ids`) | 0 |
| Database queries for the whole walk | 332 |
| Time inside the endpoint | 5.87 s |

## 2. Totals and unique ids

| Check | Endpoint | Database | | Note |
|---|---|---|---|---|
| Properties in total | 2672 | 2672 | ✅ | match |
| Ids present in the database but missing from the walk | 0 | 0 | ✅ | no omissions |
| Ids returned that do not exist in the database | 0 | 0 | ✅ | no phantoms |
| Duplicate ids in the walk | 0 | 0 | ✅ | no duplicates |
| Ids reported in `pagination.skipped_ids` | 0 | 0 | ✅ | every selected id loaded |

### By status

| Status | Endpoint (walked) | Endpoint (`totals`) | Database | | 
|---|---|---|---|---|
| `draft` | 60 | 60 | 60 | ✅ |
| `publish` | 2612 | 2612 | 2612 | ✅ |

### By author

| Check | Endpoint | Database | | Note |
|---|---|---|---|---|
| Distinct authors | 248 | 248 | ✅ | match |
| Authors whose property count differs | 0 | 0 | ✅ | every author's count matches |

## 3. Field-level reconciliation

| Check | Endpoint | Database | | Note |
|---|---|---|---|---|
| Properties with a creator | 2672 | 2672 | ✅ | every property has an existing author |
| Properties without an `associate_id` | 8 | 8 | ✅ | match |
| Properties with a company | 405 | 405 | ✅ | match |
| Properties with a location | 1653 | 1653 | ✅ | match |
| Properties with coordinates | 389 | 389 | ✅ | match |
| Properties with a featured image | 2623 | 2623 | ✅ | match |
| Properties with an expiration | 476 | 476 | ✅ | match |
| Properties from WP User Frontend | 2194 | 2194 | ✅ | match |
| Properties with a contract photo | 129 | 129 | ✅ | match |
| Properties with at least one lead | 16 | 16 | ✅ | match |
| Leads counted in total | 20 | 20 | ✅ | match |
| Gallery images returned | 27589 | — | — | informational |
| Properties with a non-empty gallery | 2597 | — | — | informational |
| Properties with a responsible associate | 0 | — | — | the source field is empty on all five properties that carry it |

## 4. Pagination integrity

| Check | Result |
|---|---|
| The same cursor requested twice returns the same page | ✅ identical |
| Page past the end is empty | ✅ empty |
| Page past the end reports `has_more` | ✅ false |
| Page past the end reports `next_after_id` | ✅ null |

## 5. Data-quality issues reported by the endpoint

| Code | Occurrences |
|---|---|
| `associate_id_missing` | 8 |
| `coordinates_geocode_error` | 105 |
| `coordinates_stale` | 2 |
| `location_incomplete` | 27 |
| `location_missing` | 1019 |
| `media_attachment_missing` | 326 |
| `media_featured_missing` | 49 |
| `media_gallery_empty` | 75 |
| `number_ambiguous` | 309 |
| `number_missing` | 56 |
| `number_unparsable` | 78 |
| `option_value_unmapped` | 53 |
| `origin_unknown` | 478 |
| `price_currency_missing` | 2503 |

### Location, by number of properties affected

| Code | Properties |
|---|---|
| `location_incomplete` | 27 |
| `location_missing` | 1019 |
| properties with all three levels resolved | 1626 |

The counts above are **properties**, not occurrences: one property can raise
`location_ambiguous` twice when two levels are contested.

These describe the **source data**, not endpoint failures: every property is still
returned in full. See `docs/contract.md` §4.11 for what each code means.

## 6. Limitations of this run

- **Media file existence is not checked here.** `wp-content/uploads` on the replica holds no
  media and every request redirects to production, so the check is switched off for these
  tools — unconditionally, because the `wpcli` container reports a different
  `wp_get_environment_type()` than an HTTP request does, and trusting it produced tens of
  thousands of false `media_file_missing` issues. Broken attachment *references* are fully
  checked and reported, since that needs only `wp_posts`.
- Other plugins are not loaded (`--skip-plugins`), so the query counts are this plugin's own.
  The post type and location taxonomy are registered minimally by `tools/bootstrap.php`.
- `totals` is a per-request snapshot. On a quiet replica it is stable across the walk; on a
  live site it can drift, which is why the checks above compare **unique ids**, not only counts.

## 7. Verdict

**21 of 21 checks passed.** The endpoint returns every property in the source exactly once, and every
field-level count agrees with the database.
