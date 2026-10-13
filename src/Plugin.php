<?php
/**
 * Plugin wiring.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades;

/**
 * Registers the plugin's hooks.
 *
 * This class only wires services to hooks: it holds no business logic, and every callback
 * delegates straight to a service.
 *
 * It holds a {@see Factory} rather than a built controller, so the object graph is created only
 * when a REST request actually reaches `rest_api_init` — not on every page view, admin screen or
 * cron run.
 */
final class Plugin {

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Builds the object graph on demand.
	 *
	 * @var Factory
	 */
	private Factory $factory;

	/**
	 * Constructor.
	 *
	 * @param string  $plugin_file Absolute path to the main plugin file.
	 * @param Factory $factory     Builds the object graph on demand.
	 */
	public function __construct( string $plugin_file, Factory $factory ) {
		$this->plugin_file = $plugin_file;
		$this->factory     = $factory;
	}

	/**
	 * Hooks the plugin into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load_text_domain' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Builds the endpoint and registers its route. Hooked to `rest_api_init`.
	 *
	 * The graph is constructed here, at the latest possible moment, so a request that never touches
	 * the REST API pays nothing for it.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$this->factory->controller()->register_routes();
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
