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

use ElPuas\CCCBR\OrbitasPropiedades\Factory;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\LocationMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Repository\PropertyRepository;
use ElPuas\CCCBR\OrbitasPropiedades\Rest\PropertiesController;

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
		require_once dirname( __DIR__ ) . '/vendor/autoload.php';

		cccbr_orbitas_tools_register_objects();

		/*
		 * The same composition root the plugin uses, so these tools exercise exactly the graph an
		 * HTTP request does and the wiring cannot drift into a second copy.
		 *
		 * The media file-existence check is switched **off explicitly**, not left to the
		 * environment. Measured on this replica, an HTTP request reports
		 * `wp_get_environment_type() === 'local'` while the same call inside the `wpcli` container
		 * reports `'production'` — the containers do not share that setting, and core offers no
		 * filter to correct it. Trusting it made the reconciliation emit 33 219
		 * `media_file_missing` issues for files the web request correctly says nothing about. These
		 * tools reconcile counts and can verify a file no better than the web path can; broken
		 * attachment *references* are still fully checked, because that needs only `wp_posts`.
		 */
		$controller = ( new Factory( false ) )->controller();

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
	 * Performs one request against the single-property route.
	 *
	 * @param int $id Property ID.
	 * @return array{status: int, body: mixed, queries: int, seconds: float}
	 */
	function cccbr_orbitas_tools_request_item( int $id ): array {
		global $wpdb;

		$request = new \WP_REST_Request(
			'GET',
			'/' . PropertiesController::REST_NAMESPACE . '/' . PropertiesController::REST_BASE . '/' . $id
		);

		$request->set_param( 'id', $id );

		$queries_before = $wpdb->num_queries;
		$started        = microtime( true );

		$response = rest_do_request( $request );

		return array(
			'status'  => $response->get_status(),
			'body'    => $response->get_data(),
			'queries' => $wpdb->num_queries - $queries_before,
			'seconds' => microtime( true ) - $started,
		);
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
