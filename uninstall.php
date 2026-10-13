<?php
/**
 * Uninstall routine.
 *
 * Removes the only thing the plugin ever persisted: the read capability. It touches no property,
 * no user, no application password and no other plugin's data.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * The capability to remove.
 *
 * Declared literally so uninstall works even when Composer dependencies are gone; the autoloaded
 * constant is preferred when it is available, which keeps the two definitions from drifting.
 */
$cccbr_orbitas_capability = 'cccbr_orbitas_read_propiedades';

$cccbr_orbitas_autoload = __DIR__ . '/vendor/autoload.php';

if ( is_readable( $cccbr_orbitas_autoload ) ) {
	require_once $cccbr_orbitas_autoload;

	$cccbr_orbitas_capability = \ElPuas\CCCBR\OrbitasPropiedades\Auth\Permissions::CAPABILITY;
}

// Remove the capability from every role, not only from administrator: a site owner may have
// granted it to another role, and leaving it behind would outlive the plugin.
$cccbr_orbitas_roles = wp_roles();

foreach ( array_keys( $cccbr_orbitas_roles->get_names() ) as $cccbr_orbitas_role_name ) {
	$cccbr_orbitas_role = get_role( (string) $cccbr_orbitas_role_name );

	if ( null === $cccbr_orbitas_role ) {
		continue;
	}

	if ( ! $cccbr_orbitas_role->has_cap( $cccbr_orbitas_capability ) ) {
		continue;
	}

	$cccbr_orbitas_role->remove_cap( $cccbr_orbitas_capability );
}

unset( $cccbr_orbitas_autoload, $cccbr_orbitas_capability, $cccbr_orbitas_roles, $cccbr_orbitas_role_name, $cccbr_orbitas_role );
