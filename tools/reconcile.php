<?php
/**
 * Reconciles the endpoint's output against the database and prints a Markdown report.
 *
 * Run from the plugin directory and redirect the output:
 *
 *     docker compose -f ~/Projects/cccbr-replica/compose.yaml run --rm wpcli \
 *       wp eval-file wp-content/plugins/cccbr-orbitas-propiedades/tools/reconcile.php \
 *       --path=/var/www/html --skip-plugins --skip-themes > docs/reconciliation.md
 *
 * The tool writes nothing itself — it only reads, and prints to stdout — so it can never alter the
 * data it is checking. It walks the whole collection through `rest_do_request()` as an
 * administrator, so no HTTP credential is involved.
 *
 * Only aggregates, identifiers and booleans are printed. No name, email, phone number or other
 * personal value is ever read into the report.
 *
 * There is deliberately no `declare(strict_types=1)` here: `wp eval-file` strips the opening tag
 * and evaluates the rest, where a strict-types declaration is no longer the first statement and
 * PHP rejects it.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

namespace ElPuas\CCCBR\OrbitasPropiedades\Tools;

use ElPuas\CCCBR\OrbitasPropiedades\Mapper\LocationMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\PeopleMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Repository\PropertyRepository;
use ElPuas\CCCBR\OrbitasPropiedades\Rest\QueryArgs;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

require_once __DIR__ . '/bootstrap.php';

/**
 * Collects the whole collection by walking the endpoint.
 *
 * @return array<string, mixed>
 */
function cccbr_orbitas_walk(): array {
	$after     = 0;
	$pages     = 0;
	$ids       = array();
	$by_status = array();
	$by_author = array();
	$issues    = array();
	$seconds   = 0.0;
	$queries   = 0;
	$totals    = array();
	$max_id    = null;
	$skipped   = array();

	$with_creator     = 0;
	$with_associate   = 0;
	$with_company     = 0;
	$with_responsible = 0;
	$with_location    = 0;
	$with_coordinates = 0;
	$with_featured    = 0;
	$with_gallery     = 0;
	$gallery_images   = 0;
	$with_expiration  = 0;
	$origin_wpuf      = 0;
	$leads_properties = 0;
	$leads_total      = 0;
	$contract_photos  = 0;
	$location_issues  = array();
	$sample           = array();

	do {
		$result = cccbr_orbitas_tools_request(
			array(
				'per_page' => 100,
				'after_id' => $after,
			)
		);

		if ( 200 !== $result['status'] ) {
			return array( 'error' => 'HTTP ' . $result['status'] . ' at after_id ' . $after );
		}

		++$pages;
		$seconds += $result['seconds'];
		$queries += $result['queries'];

		$body       = $result['body'];
		$totals     = is_array( $body['totals'] ?? null ) ? $body['totals'] : $totals;
		$pagination = is_array( $body['pagination'] ?? null ) ? $body['pagination'] : array();
		$max_id     = $pagination['max_id'] ?? $max_id;

		foreach ( (array) ( $pagination['skipped_ids'] ?? array() ) as $skipped_id ) {
			$skipped[] = (int) $skipped_id;
		}

		foreach ( (array) ( $body['data'] ?? array() ) as $property ) {
			if ( ! is_array( $property ) ) {
				continue;
			}

			$ids[] = (int) $property['id'];

			// Keep one item per page, plus the first of all, as the sample the single-property
			// route is compared against.
			if ( 0 === count( $ids ) % 100 || 1 === count( $ids ) ) {
				$sample[ (int) $property['id'] ] = $property;
			}

			$status               = (string) $property['status'];
			$by_status[ $status ] = ( $by_status[ $status ] ?? 0 ) + 1;

			if ( is_array( $property['creator'] ) ) {
				++$with_creator;

				$author               = (int) $property['creator']['id'];
				$by_author[ $author ] = ( $by_author[ $author ] ?? 0 ) + 1;
			}

			if ( null !== $property['associate_id'] ) {
				++$with_associate;
			}

			if ( is_array( $property['company'] ) ) {
				++$with_company;
			}

			if ( is_array( $property['responsible'] ) ) {
				++$with_responsible;
			}

			/*
			 * `location` is always an object with the three members, so its presence proves
			 * nothing: a property "has a location" when at least one level resolved.
			 */
			foreach ( (array) $property['location'] as $level ) {
				if ( null !== $level ) {
					++$with_location;

					break;
				}
			}

			if ( null !== $property['coordinates'] ) {
				++$with_coordinates;
			}

			if ( null !== $property['expiration'] ) {
				++$with_expiration;
			}

			if ( 'wp_user_frontend' === $property['origin']['source'] ) {
				++$origin_wpuf;
			}

			if ( true === $property['private_documents']['has_contract_photo'] ) {
				++$contract_photos;
			}

			if ( null !== $property['media']['featured'] ) {
				++$with_featured;
			}

			$gallery = (array) $property['media']['gallery'];

			if ( array() !== $gallery ) {
				++$with_gallery;
			}

			$gallery_images += count( $gallery );

			$leads = (int) $property['leads_count'];

			if ( $leads > 0 ) {
				++$leads_properties;
				$leads_total += $leads;
			}

			$property_codes = array();

			foreach ( (array) $property['issues'] as $issue ) {
				if ( ! is_array( $issue ) ) {
					continue;
				}

				$code            = (string) $issue['code'];
				$issues[ $code ] = ( $issues[ $code ] ?? 0 ) + 1;

				$property_codes[ $code ] = true;
			}

			// Per-property counts for the location codes: one property can raise the same code
			// twice (two contested levels), and what matters is how many properties are affected.
			foreach ( array_keys( $property_codes ) as $code ) {
				if ( 0 === strpos( (string) $code, 'location' ) ) {
					$location_issues[ (string) $code ] = ( $location_issues[ (string) $code ] ?? 0 ) + 1;
				}
			}
		}

		$has_more = (bool) ( $pagination['has_more'] ?? false );
		$after    = (int) ( $pagination['next_after_id'] ?? 0 );
	} while ( $has_more && $pages < 200 );

	return array(
		'pages'            => $pages,
		'seconds'          => $seconds,
		'queries'          => $queries,
		'ids'              => $ids,
		'by_status'        => $by_status,
		'by_author'        => $by_author,
		'issues'           => $issues,
		'totals'           => $totals,
		'max_id'           => $max_id,
		'skipped'          => $skipped,
		'with_creator'     => $with_creator,
		'with_associate'   => $with_associate,
		'with_company'     => $with_company,
		'with_responsible' => $with_responsible,
		'with_location'    => $with_location,
		'with_coordinates' => $with_coordinates,
		'with_featured'    => $with_featured,
		'with_gallery'     => $with_gallery,
		'gallery_images'   => $gallery_images,
		'with_expiration'  => $with_expiration,
		'origin_wpuf'      => $origin_wpuf,
		'leads_properties' => $leads_properties,
		'leads_total'      => $leads_total,
		'contract_photos'  => $contract_photos,
		'location_issues'  => $location_issues,
		'sample'           => $sample,
	);
}

/**
 * Prepares a statement, expanding `{statuses}` into the set of exported statuses.
 *
 * Every comparison query has to apply the **same** status filter the endpoint does, or the
 * reconciliation would measure two different populations. Since CCCBR excluded `trash`, a query
 * that forgets the filter reports 3055 against the endpoint's 2672 and looks like a defect.
 *
 * The only runtime-built fragment is a run of `%s` derived from the **count** of statuses; every
 * value is bound by `prepare()`.
 *
 * @param string $template SQL containing the `{statuses}` marker.
 * @param array  $values   Values to bind, in placeholder order, before the statuses.
 * @return string|null
 *
 * @phpstan-param list<string> $values
 */
function cccbr_orbitas_prepare_with_statuses( string $template, array $values ): ?string {
	global $wpdb;

	$statuses     = QueryArgs::ALLOWED_STATUSES;
	$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );
	$sql          = str_replace( '{statuses}', $placeholders, $template );

	foreach ( $statuses as $status ) {
		$values[] = $status;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- See the docblock: the only runtime-built fragment is a run of %s derived from a count.
	$prepared = $wpdb->prepare( $sql, $values );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

	return is_string( $prepared ) ? $prepared : null;
}

/**
 * Reads the same facts straight from the database.
 *
 * @return array<string, mixed>
 */
function cccbr_orbitas_database_facts(): array {
	global $wpdb;

	$type = PropertyRepository::POST_TYPE;

	$by_status = array();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- A reconciliation tool must read the database directly and uncached; that is its entire purpose. Every statement below is prepared by cccbr_orbitas_prepare_with_statuses().
	$rows = $wpdb->get_results(
		(string) cccbr_orbitas_prepare_with_statuses(
			"SELECT p.post_status, COUNT(*) AS total FROM {$wpdb->posts} p
				WHERE p.post_type = %s AND p.post_status IN ( {statuses} )
				GROUP BY p.post_status",
			array( $type )
		),
		ARRAY_A
	);

	foreach ( (array) $rows as $row ) {
		$by_status[ (string) $row['post_status'] ] = (int) $row['total'];
	}

	$ids = $wpdb->get_col(
		(string) cccbr_orbitas_prepare_with_statuses(
			"SELECT p.ID FROM {$wpdb->posts} p
				WHERE p.post_type = %s AND p.post_status IN ( {statuses} )
				ORDER BY p.ID ASC",
			array( $type )
		)
	);

	$by_author = array();

	$rows = $wpdb->get_results(
		(string) cccbr_orbitas_prepare_with_statuses(
			"SELECT p.post_author, COUNT(*) AS total FROM {$wpdb->posts} p
				WHERE p.post_type = %s AND p.post_status IN ( {statuses} )
				GROUP BY p.post_author",
			array( $type )
		),
		ARRAY_A
	);

	foreach ( (array) $rows as $row ) {
		$by_author[ (int) $row['post_author'] ] = (int) $row['total'];
	}

	$single = array(
		'authors_without_associate'    => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(DISTINCT p.post_author) FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->usermeta} um ON um.user_id = p.post_author AND um.meta_key = %s
				WHERE p.post_type = %s AND ( um.umeta_id IS NULL OR TRIM(um.meta_value) = '' )
					AND p.post_status IN ( {statuses} )",
			array( PeopleMapper::ASSOCIATE_META_KEY, $type )
		),
		'properties_without_associate' => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->usermeta} um ON um.user_id = p.post_author AND um.meta_key = %s
				WHERE p.post_type = %s AND ( um.umeta_id IS NULL OR TRIM(um.meta_value) = '' )
					AND p.post_status IN ( {statuses} )",
			array( PeopleMapper::ASSOCIATE_META_KEY, $type )
		),
		'properties_with_location'     => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id
				WHERE tt.taxonomy = %s AND p.post_type = %s AND p.post_status IN ( {statuses} )",
			array( LocationMapper::TAXONOMY, $type )
		),
		'usable_thumbnails'            => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				INNER JOIN {$wpdb->posts} a ON a.ID = CAST(pm.meta_value AS UNSIGNED) AND a.post_type = 'attachment'
				WHERE p.post_type = %s AND pm.meta_key = '_thumbnail_id' AND pm.meta_value REGEXP '^[0-9]+$'
					AND p.post_status IN ( {statuses} )",
			array( $type )
		),
		'numeric_coordinates'          => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value NOT LIKE %s
					AND p.post_status IN ( {statuses} )",
			array( $type, md5( 'mapa' ) . '_lat', $wpdb->esc_like( 'je-coord-error' ) . '%' )
		),
		'error_coordinates'            => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value LIKE %s
					AND p.post_status IN ( {statuses} )",
			array( $type, md5( 'mapa' ) . '_lat', $wpdb->esc_like( 'je-coord-error' ) . '%' )
		),
		'with_expiration'              => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND pm.meta_key = '_jet_pep_period' AND TRIM(pm.meta_value) <> ''
					AND p.post_status IN ( {statuses} )",
			array( $type )
		),
		'origin_wpuf'                  => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND pm.meta_key = '_wpuf_form_id' AND pm.meta_value REGEXP '^[0-9]+$'
					AND p.post_status IN ( {statuses} )",
			array( $type )
		),
		'contract_photos'              => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE p.post_type = %s AND pm.meta_key = 'foto-del-contrato'
					AND pm.meta_value IS NOT NULL AND TRIM(pm.meta_value) <> ''
					AND p.post_status IN ( {statuses} )",
			array( $type )
		),
		'leads_properties'             => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(DISTINCT r.parent_object_id) FROM {$wpdb->prefix}jet_rel_171 r
				INNER JOIN {$wpdb->posts} p ON p.ID = r.parent_object_id AND p.post_type = %s
				WHERE r.rel_id = '171' AND p.post_status IN ( {statuses} )",
			array( $type )
		),
		'leads_total'                  => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(*) FROM {$wpdb->prefix}jet_rel_171 r
				INNER JOIN {$wpdb->posts} p ON p.ID = r.parent_object_id AND p.post_type = %s
				WHERE r.rel_id = '171' AND p.post_status IN ( {statuses} )",
			array( $type )
		),
		'properties_with_company'      => cccbr_orbitas_prepare_with_statuses(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->prefix}jet_rel_131 r ON r.child_object_id = p.post_author AND r.rel_id = '131'
				WHERE p.post_type = %s AND p.post_status IN ( {statuses} )",
			array( $type )
		),
	);

	$facts = array(
		'by_status' => $by_status,
		'ids'       => array_values( array_map( 'intval', (array) $ids ) ),
		'by_author' => $by_author,
	);

	foreach ( $single as $key => $sql ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Every statement in $single was built with $wpdb->prepare() above.
		$facts[ $key ] = is_string( $sql ) ? (int) $wpdb->get_var( $sql ) : 0;
	}
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return $facts;
}

/**
 * Prints one comparison row.
 *
 * @param string $label    What is being compared.
 * @param int    $api      Value the endpoint reported.
 * @param int    $database Value the database holds.
 * @param string $note     Optional explanation.
 * @return bool Whether the two agree.
 */
function cccbr_orbitas_row( string $label, int $api, int $database, string $note = '' ): bool {
	$ok = ( $api === $database );

	printf(
		"| %s | %d | %d | %s | %s |\n",
		$label,
		$api,
		$database,
		$ok ? '✅' : '❌',
		'' === $note ? ( $ok ? 'match' : 'DIFFERENT' ) : $note
	);

	return $ok;
}

/**
 * Runs the reconciliation and prints the report.
 *
 * @return void
 */
function cccbr_orbitas_reconcile(): void {
	cccbr_orbitas_tools_prepare();

	if ( 0 === cccbr_orbitas_tools_login_as_admin() ) {
		echo "No administrator user found: cannot run the reconciliation.\n";

		return;
	}

	$walk = cccbr_orbitas_walk();

	if ( isset( $walk['error'] ) ) {
		printf( "The walk failed: %s\n", (string) $walk['error'] );

		return;
	}

	$db = cccbr_orbitas_database_facts();

	$api_ids = (array) $walk['ids'];
	$db_ids  = (array) $db['ids'];

	$api_unique = array_values( array_unique( $api_ids ) );
	$sorted     = $api_unique;
	sort( $sorted, SORT_NUMERIC );

	$missing = array_values( array_diff( $db_ids, $api_unique ) );
	$extra   = array_values( array_diff( $api_unique, $db_ids ) );

	$checks = array();

	printf( "# Reconciliation — `GET /wp-json/cccbr-orbitas/v1/propiedades`\n\n" );
	printf(
		"Produced by `tools/reconcile.php` on **%s UTC**, against the local replica.\n",
		gmdate( 'Y-m-d H:i:s' )
	);
	echo "The tool walks the whole collection through `rest_do_request()` as an administrator, then\n";
	echo "reads the same facts straight from the database and compares them. It writes nothing, and\n";
	echo "reports only counts, identifiers and booleans — never a personal value.\n\n";
	printf(
		"**Population:** properties whose status is one of %s. **Trashed properties are excluded**\n",
		'`' . implode( '`, `', QueryArgs::ALLOWED_STATUSES ) . '`'
	);
	echo "from the export by CCCBR's decision, so every query below applies the same filter — a\n";
	echo "comparison against the unfiltered table would measure two different populations.\n\n";

	printf( "## 1. The walk\n\n" );
	printf( "| Measure | Value |\n|---|---|\n" );
	printf( "| Pages walked (`per_page=100`) | %d |\n", (int) $walk['pages'] );
	printf( "| Properties returned | %d |\n", count( $api_ids ) );
	printf( "| Distinct ids returned | %d |\n", count( $api_unique ) );
	printf( "| Duplicate ids | %d |\n", count( $api_ids ) - count( $api_unique ) );
	printf( "| Ids in ascending order across the whole walk | %s |\n", $api_ids === $sorted ? 'yes' : '**no**' );
	printf( "| `pagination.max_id` reported | %s |\n", null === $walk['max_id'] ? 'null' : (string) (int) $walk['max_id'] );
	printf( "| Ids selected but not loadable (`pagination.skipped_ids`) | %d |\n", count( (array) $walk['skipped'] ) );
	printf( "| Database queries for the whole walk | %d |\n", (int) $walk['queries'] );
	printf( "| Time inside the endpoint | %.2f s |\n\n", (float) $walk['seconds'] );

	printf( "## 2. Totals and unique ids\n\n" );
	printf( "| Check | Endpoint | Database | | Note |\n|---|---|---|---|---|\n" );

	$checks[] = cccbr_orbitas_row( 'Properties in total', count( $api_unique ), count( $db_ids ) );
	$checks[] = cccbr_orbitas_row( 'Ids present in the database but missing from the walk', count( $missing ), 0, count( $missing ) > 0 ? '**omissions**' : 'no omissions' );
	$checks[] = cccbr_orbitas_row( 'Ids returned that do not exist in the database', count( $extra ), 0, count( $extra ) > 0 ? '**phantoms**' : 'no phantoms' );
	$checks[] = cccbr_orbitas_row( 'Duplicate ids in the walk', count( $api_ids ) - count( $api_unique ), 0, 0 === count( $api_ids ) - count( $api_unique ) ? 'no duplicates' : '**duplicates**' );
	$checks[] = cccbr_orbitas_row( 'Ids reported in `pagination.skipped_ids`', count( (array) $walk['skipped'] ), 0, array() === $walk['skipped'] ? 'every selected id loaded' : '**properties were deleted mid-walk**' );

	/*
	 * `totals.by_status` is cast to an object by the controller so it serializes as `{}` rather
	 * than `[]` when empty. `rest_do_request()` hands back the raw PHP value, not JSON, so it
	 * arrives here as a stdClass and has to be cast back.
	 */
	$api_totals = (array) ( $walk['totals']['by_status'] ?? array() );

	echo "\n### By status\n\n";
	printf( "| Status | Endpoint (walked) | Endpoint (`totals`) | Database | | \n|---|---|---|---|---|\n" );

	$statuses = array_unique( array_merge( array_keys( (array) $walk['by_status'] ), array_keys( (array) $db['by_status'] ) ) );
	sort( $statuses );

	foreach ( $statuses as $status ) {
		$walked   = (int) ( $walk['by_status'][ $status ] ?? 0 );
		$reported = (int) ( $api_totals[ $status ] ?? 0 );
		$actual   = (int) ( $db['by_status'][ $status ] ?? 0 );
		$ok       = ( $walked === $actual && $reported === $actual );

		$checks[] = $ok;

		printf( "| `%s` | %d | %d | %d | %s |\n", $status, $walked, $reported, $actual, $ok ? '✅' : '❌' );
	}

	echo "\n### By author\n\n";

	$api_authors = (array) $walk['by_author'];
	$db_authors  = (array) $db['by_author'];
	$mismatched  = 0;

	foreach ( $db_authors as $author => $count ) {
		if ( (int) ( $api_authors[ $author ] ?? 0 ) !== (int) $count ) {
			++$mismatched;
		}
	}

	printf( "| Check | Endpoint | Database | | Note |\n|---|---|---|---|---|\n" );
	$checks[] = cccbr_orbitas_row( 'Distinct authors', count( $api_authors ), count( $db_authors ) );
	$checks[] = cccbr_orbitas_row( 'Authors whose property count differs', $mismatched, 0, 0 === $mismatched ? 'every author\'s count matches' : '**mismatch**' );

	echo "\n## 3. Field-level reconciliation\n\n";
	printf( "| Check | Endpoint | Database | | Note |\n|---|---|---|---|---|\n" );

	$checks[] = cccbr_orbitas_row( 'Properties with a creator', (int) $walk['with_creator'], count( $db_ids ), 'every property has an existing author' );
	$checks[] = cccbr_orbitas_row( 'Properties without an `associate_id`', count( $db_ids ) - (int) $walk['with_associate'], (int) $db['properties_without_associate'] );
	$checks[] = cccbr_orbitas_row( 'Properties with a company', (int) $walk['with_company'], (int) $db['properties_with_company'] );
	$checks[] = cccbr_orbitas_row( 'Properties with a location', (int) $walk['with_location'], (int) $db['properties_with_location'] );
	$checks[] = cccbr_orbitas_row( 'Properties with coordinates', (int) $walk['with_coordinates'], (int) $db['numeric_coordinates'] );
	$checks[] = cccbr_orbitas_row( 'Properties with a featured image', (int) $walk['with_featured'], (int) $db['usable_thumbnails'] );
	$checks[] = cccbr_orbitas_row( 'Properties with an expiration', (int) $walk['with_expiration'], (int) $db['with_expiration'] );
	$checks[] = cccbr_orbitas_row( 'Properties from WP User Frontend', (int) $walk['origin_wpuf'], (int) $db['origin_wpuf'] );
	$checks[] = cccbr_orbitas_row( 'Properties with a contract photo', (int) $walk['contract_photos'], (int) $db['contract_photos'] );
	$checks[] = cccbr_orbitas_row( 'Properties with at least one lead', (int) $walk['leads_properties'], (int) $db['leads_properties'] );
	$checks[] = cccbr_orbitas_row( 'Leads counted in total', (int) $walk['leads_total'], (int) $db['leads_total'] );

	printf( "| Gallery images returned | %d | — | — | informational |\n", (int) $walk['gallery_images'] );
	printf( "| Properties with a non-empty gallery | %d | — | — | informational |\n", (int) $walk['with_gallery'] );
	printf( "| Properties with a responsible associate | %d | — | — | the source field is empty on all five properties that carry it |\n", (int) $walk['with_responsible'] );

	echo "\n## 4. Pagination integrity\n\n";

	$first  = cccbr_orbitas_tools_request(
		array(
			'per_page' => 50,
			'after_id' => 100000,
		)
	);
	$second = cccbr_orbitas_tools_request(
		array(
			'per_page' => 50,
			'after_id' => 100000,
		)
	);

	$first_ids  = array_column( (array) ( $first['body']['data'] ?? array() ), 'id' );
	$second_ids = array_column( (array) ( $second['body']['data'] ?? array() ), 'id' );

	$past_end = cccbr_orbitas_tools_request(
		array(
			'per_page' => 100,
			'after_id' => 99999999,
		)
	);

	printf( "| Check | Result |\n|---|---|\n" );
	printf( "| The same cursor requested twice returns the same page | %s |\n", $first_ids === $second_ids ? '✅ identical' : '❌ different' );
	printf( "| Page past the end is empty | %s |\n", array() === (array) ( $past_end['body']['data'] ?? array() ) ? '✅ empty' : '❌ not empty' );
	printf( "| Page past the end reports `has_more` | %s |\n", false === ( $past_end['body']['pagination']['has_more'] ?? null ) ? '✅ false' : '❌ true' );

	/*
	 * `next_after_id` is legitimately null here, so `??` cannot be used to detect its absence —
	 * it would substitute the fallback for the very value being checked. The key's presence is
	 * tested separately from its value.
	 */
	$past_end_pagination = (array) ( $past_end['body']['pagination'] ?? array() );
	$cursor_is_null      = array_key_exists( 'next_after_id', $past_end_pagination )
		&& null === $past_end_pagination['next_after_id'];

	printf( "| Page past the end reports `next_after_id` | %s |\n", $cursor_is_null ? '✅ null' : '❌ not null' );

	$checks[] = ( $first_ids === $second_ids );

	echo "\n## 5. The single-property route\n\n";

	$sample     = (array) $walk['sample'];
	$same       = 0;
	$different  = array();
	$bad_status = array();

	foreach ( $sample as $id => $collection_item ) {
		$single = cccbr_orbitas_tools_request_item( (int) $id );

		if ( 200 !== $single['status'] ) {
			$bad_status[] = (int) $id . ' → HTTP ' . $single['status'];

			continue;
		}

		if ( $single['body'] === $collection_item ) {
			++$same;

			continue;
		}

		$single_keys = is_array( $single['body'] ) ? array_keys( $single['body'] ) : array();
		$differing   = array();

		foreach ( $collection_item as $field => $value ) {
			if ( ! array_key_exists( $field, (array) $single['body'] ) || $single['body'][ $field ] !== $value ) {
				$differing[] = (string) $field;
			}
		}

		$different[] = (int) $id . ' (' . ( array() === $differing ? 'key order or extra: ' . implode( ',', $single_keys ) : implode( ',', $differing ) ) . ')';
	}

	printf( "| Check | Result |\n|---|---|\n" );
	printf( "| Ids sampled from the walk | %d |\n", count( $sample ) );
	printf( "| Single-property responses that are **identical** to the collection item | %d |\n", $same );
	printf(
		"| Responses that differ | %s |\n",
		array() === $different ? '0' : '**' . count( $different ) . '**: ' . implode( '; ', $different )
	);
	printf(
		"| Sampled ids the single route did not return with 200 | %s |\n",
		array() === $bad_status ? '0' : '**' . implode( '; ', $bad_status ) . '**'
	);

	$checks[] = ( count( $sample ) === $same );

	echo "\nIdentity is compared with `===` on the whole decoded body, so a difference in a nested\n";
	echo "value, a type or the field order would all show up. Both routes go through the same\n";
	echo "repository and the same mappers; this check is what makes that a verified fact rather than\n";
	echo "an assumption.\n";

	echo "\n## 6. Data-quality issues reported by the endpoint\n\n";

	$issues = (array) $walk['issues'];
	ksort( $issues );

	printf( "| Code | Occurrences |\n|---|---|\n" );

	foreach ( $issues as $code => $count ) {
		printf( "| `%s` | %d |\n", (string) $code, (int) $count );
	}

	echo "\n### Location, by number of properties affected\n\n";

	$location_issues = (array) $walk['location_issues'];
	ksort( $location_issues );

	printf( "| Code | Properties |\n|---|---|\n" );

	if ( array() === $location_issues ) {
		printf( "| — | none |\n" );
	}

	foreach ( $location_issues as $code => $count ) {
		printf( "| `%s` | %d |\n", (string) $code, (int) $count );
	}

	printf(
		"| properties with all three levels resolved | %d |\n",
		count( $api_unique ) - array_sum( array( $location_issues['location_missing'] ?? 0, $location_issues['location_incomplete'] ?? 0 ) )
	);

	echo "\nThe counts above are **properties**, not occurrences: one property can raise\n";
	echo "`location_ambiguous` twice when two levels are contested.\n";

	echo "\nThese describe the **source data**, not endpoint failures: every property is still\n";
	echo "returned in full. See `docs/contract.md` §4.11 for what each code means.\n";

	echo "\n## 7. Limitations of this run\n\n";
	echo "- **Media file existence is not checked here.** `wp-content/uploads` on the replica holds no\n";
	echo "  media and every request redirects to production, so the check is switched off for these\n";
	echo "  tools — unconditionally, because the `wpcli` container reports a different\n";
	echo "  `wp_get_environment_type()` than an HTTP request does, and trusting it produced tens of\n";
	echo "  thousands of false `media_file_missing` issues. Broken attachment *references* are fully\n";
	echo "  checked and reported, since that needs only `wp_posts`.\n";
	echo "- Other plugins are not loaded (`--skip-plugins`), so the query counts are this plugin's own.\n";
	echo "  The post type and location taxonomy are registered minimally by `tools/bootstrap.php`.\n";
	echo "- `totals` is a per-request snapshot. On a quiet replica it is stable across the walk; on a\n";
	echo "  live site it can drift, which is why the checks above compare **unique ids**, not only counts.\n";

	$failed = count( array_filter( $checks, static fn( bool $ok ): bool => ! $ok ) );

	printf( "\n## 8. Verdict\n\n" );
	printf( '**%d of %d checks passed.**', count( $checks ) - $failed, count( $checks ) );

	if ( 0 === $failed ) {
		echo " The endpoint returns every property in the source exactly once, and every\n";
		echo "field-level count agrees with the database.\n";

		return;
	}

	printf( " **%d check(s) disagree and need investigation.**\n", $failed );
}

cccbr_orbitas_reconcile();
