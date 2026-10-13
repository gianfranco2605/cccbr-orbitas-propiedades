<?php
/**
 * Plugin Name:       CCCBR Orbitas Propiedades
 * Plugin URI:        https://github.com/gianfranco2605/cccbr-orbitas-propiedades
 * Description:       Authenticated, read-only REST endpoint exposing all CCCBR properties (data, media, relations and creator) for the Orbitas Marketplace import.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            ElPuas Digital Crafts
 * Author URI:        https://elpuasdigitalcrafts.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cccbr-orbitas-propiedades
 * Domain Path:       /languages
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CCCBR_ORBITAS_VERSION = '0.1.0';

define( 'CCCBR_ORBITAS_PLUGIN_FILE', __FILE__ );
define( 'CCCBR_ORBITAS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Renders an admin notice when Composer dependencies have not been installed.
 *
 * The plugin never fatals on a missing autoloader: it simply does nothing.
 *
 * @return void
 */
function cccbr_orbitas_missing_autoload_notice(): void {
	$message = __(
		'CCCBR Orbitas Propiedades is inactive: Composer dependencies are missing. Run "composer install" in the plugin folder.',
		'cccbr-orbitas-propiedades'
	);

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html( $message )
	);
}

$cccbr_orbitas_autoload = CCCBR_ORBITAS_PLUGIN_DIR . 'vendor/autoload.php';

if ( ! is_readable( $cccbr_orbitas_autoload ) ) {
	add_action( 'admin_notices', __NAMESPACE__ . '\\cccbr_orbitas_missing_autoload_notice' );

	return;
}

require_once $cccbr_orbitas_autoload;

register_activation_hook(
	__FILE__,
	static function (): void {
		( new Lifecycle\Activator() )->activate();
	}
);

/*
 * This file is the composition root: it is the one place that reads WordPress's global database
 * handle, and it passes it in by constructor so no class of this plugin ever touches a global.
 */
global $wpdb;

/*
 * Verifying that an attachment's file exists is only meaningful where the uploads directory is the
 * real one. On a local replica every uploads request is redirected to production, so the check
 * would report every file as missing; reporting nothing is the honest answer there.
 * See docs/OPEN-QUESTIONS.md Q3.
 */
$cccbr_orbitas_check_media_files = ( 'local' !== wp_get_environment_type() );

$cccbr_orbitas_id_parser = new Support\IdListParser();

$cccbr_orbitas_controller = new Rest\PropertiesController(
	new Auth\Permissions(),
	new Rest\PropertySchema(),
	new Repository\PropertyRepository( $wpdb ),
	new Mapper\PropertyMapper(
		new Schema\FieldMap( $wpdb ),
		new Support\OptionNormalizer(),
		new Support\NumberParser(),
		new Mapper\LocationMapper( new Support\LocationResolver() ),
		new Mapper\CoordinatesMapper(),
		new Mapper\MediaMapper( $cccbr_orbitas_id_parser, $cccbr_orbitas_check_media_files ),
		new Mapper\PeopleMapper(
			new Repository\RelationsRepository( $wpdb ),
			$cccbr_orbitas_id_parser
		)
	)
);

( new Plugin( __FILE__, $cccbr_orbitas_controller ) )->register();

unset( $cccbr_orbitas_controller, $cccbr_orbitas_check_media_files, $cccbr_orbitas_id_parser );
