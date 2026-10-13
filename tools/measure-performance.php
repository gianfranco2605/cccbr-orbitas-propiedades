<?php
/**
 * Measures the cost of one page of the properties endpoint.
 *
 * Run from the plugin directory:
 *
 *     docker compose -f ~/Projects/cccbr-replica/compose.yaml run --rm wpcli \
 *       wp eval-file wp-content/plugins/cccbr-orbitas-propiedades/tools/measure-performance.php \
 *       --path=/var/www/html --skip-plugins --skip-themes
 *
 * Reports, for several page sizes and cursor positions: the number of database queries this
 * plugin issues, the wall-clock time, the peak memory, and how many properties came back. The
 * query count is what matters: it must stay flat as the page size grows, which is the proof that
 * there is no N+1.
 *
 * There is deliberately no `declare(strict_types=1)` here: `wp eval-file` strips the opening tag
 * and evaluates the rest, where a strict-types declaration is no longer the first statement and
 * PHP rejects it. The classes this script exercises all declare it; only this entry point cannot.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

namespace ElPuas\CCCBR\OrbitasPropiedades\Tools;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

require_once __DIR__ . '/bootstrap.php';

cccbr_orbitas_tools_prepare();

$cccbr_orbitas_admin = cccbr_orbitas_tools_login_as_admin();

if ( 0 === $cccbr_orbitas_admin ) {
	echo "No administrator user found: cannot measure an authorized request.\n";

	return;
}

echo "Measuring this plugin's cost per page.\n";
echo "Other plugins are not loaded, so these are the queries this code issues itself.\n\n";

$cccbr_orbitas_cases = array(
	array(
		'label'  => 'per_page=100 COLD (1st req)',
		'params' => array(
			'per_page' => 100,
			'after_id' => 0,
		),
	),
	array(
		'label'  => 'per_page=100 warm (same page)',
		'params' => array(
			'per_page' => 100,
			'after_id' => 0,
		),
	),
	array(
		'label'  => 'per_page=1   (first page)',
		'params' => array(
			'per_page' => 1,
			'after_id' => 0,
		),
	),
	array(
		'label'  => 'per_page=10  (first page)',
		'params' => array(
			'per_page' => 10,
			'after_id' => 0,
		),
	),
	array(
		'label'  => 'per_page=50  (first page)',
		'params' => array(
			'per_page' => 50,
			'after_id' => 0,
		),
	),
	array(
		'label'  => 'per_page=100 (middle)',
		'params' => array(
			'per_page' => 100,
			'after_id' => 120000,
		),
	),
	array(
		'label'  => 'per_page=100 (newest)',
		'params' => array(
			'per_page' => 100,
			'after_id' => 184000,
		),
	),
	array(
		'label'  => 'per_page=100 (publish only)',
		'params' => array(
			'per_page' => 100,
			'after_id' => 0,
			'status'   => 'publish',
		),
	),
	array(
		'label'  => 'per_page=100 (past the end)',
		'params' => array(
			'per_page' => 100,
			'after_id' => 999999,
		),
	),
);

printf( "%-30s %7s %8s %9s %8s %8s\n", 'case', 'status', 'queries', 'seconds', 'items', 'images' );
printf( "%'-30s %'-7s %'-8s %'-9s %'-8s %'-8s\n", '', '', '', '', '', '' );

foreach ( $cccbr_orbitas_cases as $cccbr_orbitas_case ) {
	$cccbr_orbitas_result = cccbr_orbitas_tools_request( $cccbr_orbitas_case['params'] );

	$cccbr_orbitas_items  = isset( $cccbr_orbitas_result['body']['data'] ) && is_array( $cccbr_orbitas_result['body']['data'] )
		? $cccbr_orbitas_result['body']['data']
		: array();
	$cccbr_orbitas_images = 0;

	foreach ( $cccbr_orbitas_items as $cccbr_orbitas_item ) {
		if ( ! is_array( $cccbr_orbitas_item ) || ! isset( $cccbr_orbitas_item['media'] ) || ! is_array( $cccbr_orbitas_item['media'] ) ) {
			continue;
		}

		$cccbr_orbitas_gallery = is_array( $cccbr_orbitas_item['media']['gallery'] ?? null )
			? $cccbr_orbitas_item['media']['gallery']
			: array();

		$cccbr_orbitas_images += count( $cccbr_orbitas_gallery );

		if ( null !== ( $cccbr_orbitas_item['media']['featured'] ?? null ) ) {
			++$cccbr_orbitas_images;
		}
	}

	printf(
		"%-30s %7d %8d %9.3f %8d %8d\n",
		$cccbr_orbitas_case['label'],
		$cccbr_orbitas_result['status'],
		$cccbr_orbitas_result['queries'],
		$cccbr_orbitas_result['seconds'],
		count( $cccbr_orbitas_items ),
		$cccbr_orbitas_images
	);
}

printf(
	"\npeak memory: %.1f MB (PHP limit %s)\n",
	memory_get_peak_usage( true ) / 1048576,
	(string) ini_get( 'memory_limit' )
);

/*
 * With SAVEQUERIES on, show what the queries of one page actually are. Run the command with
 * `--exec="define('SAVEQUERIES', true);"` to get this breakdown.
 */
if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
	echo "\n(run with --exec=\"define('SAVEQUERIES', true);\" to list the individual queries)\n";

	return;
}

global $wpdb;

$cccbr_orbitas_before = is_array( $wpdb->queries ) ? count( $wpdb->queries ) : 0;

cccbr_orbitas_tools_request(
	array(
		'per_page' => 100,
		'after_id' => 0,
	)
);

$cccbr_orbitas_log = is_array( $wpdb->queries ) ? array_slice( $wpdb->queries, $cccbr_orbitas_before ) : array();

echo "\nqueries of one warm per_page=100 page:\n";

foreach ( $cccbr_orbitas_log as $cccbr_orbitas_index => $cccbr_orbitas_query ) {
	$cccbr_orbitas_sql = is_array( $cccbr_orbitas_query ) ? (string) ( $cccbr_orbitas_query[0] ?? '' ) : '';
	$cccbr_orbitas_sql = (string) preg_replace( '/\s+/', ' ', $cccbr_orbitas_sql );

	printf( "  %2d. %s\n", $cccbr_orbitas_index + 1, substr( $cccbr_orbitas_sql, 0, 150 ) );
}
