<?php
/**
 * PHPUnit bootstrap.
 *
 * The suite covers pure units only: no WordPress is loaded. Classes under test must therefore not
 * call WordPress functions directly — data is injected through their constructors.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! defined( 'CCCBR_ORBITAS_TESTS_DIR' ) ) {
	define( 'CCCBR_ORBITAS_TESTS_DIR', __DIR__ );
}

if ( ! defined( 'CCCBR_ORBITAS_PLUGIN_ROOT' ) ) {
	define( 'CCCBR_ORBITAS_PLUGIN_ROOT', dirname( __DIR__ ) );
}
