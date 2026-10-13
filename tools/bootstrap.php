<?php
/**
 * Shared bootstrap for the command-line tools in this directory.
 *
 * The tools run through `wp eval-file … --skip-plugins --skip-themes`, which is the only way
 * WP-CLI stays inside this site's 128 MB memory limit (see `CLAUDE.md` §3.2). That means this
 * plugin is not loaded and neither is JetEngine, so the bootstrap does three things:
 *
 * 1. loads this plugin's Composer autoloader,
 * 2. registers the property post type and the location taxonomy *minimally*, because
 *    `get_the_terms()` refuses an unregistered taxonomy and `WP_Query` needs the post type to
 *    behave normally — JetEngine normally registers both,
 * 3. composes the controller exactly as the main plugin file does and hooks it to `rest_api_init`.
 *
 * Consequence worth knowing when reading the numbers: queries counted here are **this plugin's
 * own**, without the rest of the site's plugins. That is the point — it isolates what this code
 * costs — but it is not the same as the end-to-end cost of an HTTP request, which is measured
 * separately with `curl`.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Tools;

use ElPuas\CCCBR\OrbitasPropiedades\Auth\Permissions;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\CoordinatesMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\LocationMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\MediaMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\PeopleMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\PropertyMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Repository\PropertyRepository;
use ElPuas\CCCBR\OrbitasPropiedades\Repository\RelationsRepository;
use ElPuas\CCCBR\OrbitasPropiedades\Rest\PropertiesController;
use ElPuas\CCCBR\OrbitasPropiedades\Rest\PropertySchema;
use ElPuas\CCCBR\OrbitasPropiedades\Schema\FieldMap;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IdListParser;
use ElPuas\CCCBR\OrbitasPropiedades\Support\NumberParser;
use ElPuas\CCCBR\OrbitasPropiedades\Support\OptionNormalizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

if ( ! function_exists( __NAMESPACE__ . '\\cccbr_orbitas_tools_prepare' ) ) {
	/**
	 * Prepares the environment and returns the composed controller.
	 *
	 * @return PropertiesController
	 */
	function cccbr_orbitas_tools_prepare(): PropertiesController {
		global $wpdb;

		require_once dirname( __DIR__ ) . '/vendor/autoload.php';

		cccbr_orbitas_tools_register_objects();

		$id_parser   = new IdListParser();
		$check_files = ( 'local' !== wp_get_environment_type() );

		$controller = new PropertiesController(
			new Permissions(),
			new PropertySchema(),
			new PropertyRepository( $wpdb ),
			new PropertyMapper(
				new FieldMap( $wpdb ),
				new OptionNormalizer(),
				new NumberParser(),
				new LocationMapper(),
				new CoordinatesMapper(),
				new MediaMapper( $id_parser, $check_files ),
				new PeopleMapper( new RelationsRepository( $wpdb ), $id_parser )
			)
		);

		add_action( 'rest_api_init', array( $controller, 'register_routes' ) );

		return $controller;
	}

	/**
	 * Registers the post type and taxonomy JetEngine would normally register.
	 *
	 * Only the parts the read path needs: the names must exist so `WP_Query` and `get_the_terms()`
	 * behave. Nothing is written, and the registration lives only for this CLI process.
	 *
	 * @return void
	 */
	function cccbr_orbitas_tools_register_objects(): void {
		if ( ! post_type_exists( PropertyRepository::POST_TYPE ) ) {
			register_post_type(
				PropertyRepository::POST_TYPE,
				array(
					'public'   => true,
					'label'    => 'CCCBR Propiedades',
					'supports' => array( 'title', 'editor', 'thumbnail', 'author', 'custom-fields' ),
				)
			);
		}

		if ( ! taxonomy_exists( LocationMapper::TAXONOMY ) ) {
			register_taxonomy(
				LocationMapper::TAXONOMY,
				array( PropertyRepository::POST_TYPE ),
				array(
					'public'       => true,
					'hierarchical' => true,
					'label'        => 'Ubicación',
				)
			);
		}
	}

	/**
	 * Signs the process in as an administrator, so the permission callback passes.
	 *
	 * No credential is involved: the tools run on the server as the site itself. If no
	 * administrator exists the caller is told, rather than the tool silently measuring a 403.
	 *
	 * @return int The user ID, or 0 when none was found.
	 */
	function cccbr_orbitas_tools_login_as_admin(): int {
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'fields'  => 'ID',
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		if ( array() === $admins ) {
			return 0;
		}

		$user_id = (int) $admins[0];

		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Performs one request against the properties route and returns the decoded payload.
	 *
	 * Going through `rest_do_request()` exercises the real dispatch path, including argument
	 * validation and the permission callback.
	 *
	 * @param array $params Query parameters.
	 * @return array{status: int, body: array<string, mixed>, queries: int, seconds: float}
	 *
	 * @phpstan-param array<string, int|string> $params
	 */
	function cccbr_orbitas_tools_request( array $params ): array {
		global $wpdb;

		$request = new \WP_REST_Request(
			'GET',
			'/' . PropertiesController::REST_NAMESPACE . '/' . PropertiesController::REST_BASE
		);

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$queries_before = $wpdb->num_queries;
		$started        = microtime( true );

		$response = rest_do_request( $request );

		$seconds = microtime( true ) - $started;
		$queries = $wpdb->num_queries - $queries_before;

		$data = $response->get_data();

		return array(
			'status'  => $response->get_status(),
			'body'    => is_array( $data ) ? $data : array(),
			'queries' => $queries,
			'seconds' => $seconds,
		);
	}
}
