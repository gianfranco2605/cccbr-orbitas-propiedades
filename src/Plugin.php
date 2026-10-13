<?php
/**
 * Plugin wiring.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades;

use ElPuas\CCCBR\OrbitasPropiedades\Rest\PropertiesController;

/**
 * Registers the plugin's hooks.
 *
 * This class only wires services to hooks: it holds no business logic, and every callback
 * delegates straight to a service.
 */
final class Plugin {

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Properties endpoint.
	 *
	 * @var PropertiesController
	 */
	private PropertiesController $controller;

	/**
	 * Constructor.
	 *
	 * @param string               $plugin_file Absolute path to the main plugin file.
	 * @param PropertiesController $controller  Properties endpoint.
	 */
	public function __construct( string $plugin_file, PropertiesController $controller ) {
		$this->plugin_file = $plugin_file;
		$this->controller  = $controller;
	}

	/**
	 * Hooks the plugin into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load_text_domain' ) );
		add_action( 'rest_api_init', array( $this->controller, 'register_routes' ) );
	}

	/**
	 * Loads the plugin translations.
	 *
	 * Runs on `init` because WordPress 6.7+ warns when a text domain is loaded earlier.
	 *
	 * @return void
	 */
	public function load_text_domain(): void {
		load_plugin_textdomain(
			'cccbr-orbitas-propiedades',
			false,
			dirname( plugin_basename( $this->plugin_file ) ) . '/languages'
		);
	}
}
