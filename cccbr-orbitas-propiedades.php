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

// Task 3 wires the bootstrap call here: ( new Plugin() )->register().
