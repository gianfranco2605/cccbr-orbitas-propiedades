# Reconciliation — `GET /wp-json/cccbr-orbitas/v1/propiedades`

Produced by `tools/reconcile.php` on **2026-10-13 13:20:41 UTC**, against the local replica.
The tool walks the whole collection through `rest_do_request()` as an administrator, then
reads the same facts straight from the database and compares them. It writes nothing, and
reports only counts, identifiers and booleans — never a personal value.

## 1. The walk

| Measure | Value |
|---|---|
| Pages walked (`per_page=100`) | 31 |
| Properties returned | 3055 |
| Distinct ids returned | 3055 |
| Duplicate ids | 0 |
| Ids in ascending order across the whole walk | yes |
| `pagination.max_id` reported | 184589 |
| Database queries for the whole walk | 381 |
| Time inside the endpoint | 4.00 s |

## 2. Totals and unique ids

| Check | Endpoint | Database | | Note |
|---|---|---|---|---|
| Properties in total | 3055 | 3055 | ✅ | match |
| Ids present in the database but missing from the walk | 0 | 0 | ✅ | no omissions |
| Ids returned that do not exist in the database | 0 | 0 | ✅ | no phantoms |
| Duplicate ids in the walk | 0 | 0 | ✅ | no duplicates |

### By status

| Status | Endpoint (walked) | Endpoint (`totals`) | Database | | 
|---|---|---|---|---|
| `draft` | 60 | 60 | 60 | ✅ |
| `publish` | 2612 | 2612 | 2612 | ✅ |
| `trash` | 383 | 383 | 383 | ✅ |

### By author

| Check | Endpoint | Database | | Note |
|---|---|---|---|---|
| Distinct authors | 250 | 250 | ✅ | match |
| Authors whose property count differs | 0 | 0 | ✅ | every author's count matches |

## 3. Field-level reconciliation

| Check | Endpoint | Database | | Note |
|---|---|---|---|---|
| Properties with a creator | 3055 | 3055 | ✅ | every property has an existing author |
| Properties without an `associate_id` | 25 | 25 | ✅ | match |
| Properties with a company | 448 | 448 | ✅ | match |
| Properties with a location | 1992 | 1992 | ✅ | match |
| Properties with coordinates | 391 | 391 | ✅ | match |
| Properties with a featured image | 2994 | 2994 | ✅ | match |
| Properties with an expiration | 541 | 541 | ✅ | match |
| Properties from WP User Frontend | 2512 | 2512 | ✅ | match |
| Properties with a contract photo | 133 | 133 | ✅ | match |
| Properties with at least one lead | 20 | 20 | ✅ | match |
| Leads counted in total | 24 | 24 | ✅ | match |
| Gallery images returned | 30225 | — | — | informational |
| Properties with a non-empty gallery | 2968 | — | — | informational |
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
| `associate_id_missing` | 25 |
| `coordinates_geocode_error` | 108 |
| `coordinates_stale` | 2 |
| `location_missing` | 1063 |
| `media_attachment_missing` | 334 |
| `media_featured_missing` | 61 |
| `media_gallery_empty` | 87 |
| `number_ambiguous` | 329 |
| `number_missing` | 70 |
| `number_unparsable` | 105 |
| `option_value_normalized` | 367 |
| `option_value_unmapped` | 60 |
| `origin_unknown` | 543 |
| `price_currency_missing` | 2840 |

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
