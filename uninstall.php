<?php
/**
 * Uninstall routine.
 *
 * Removes the capability that versions up to 0.4.0 granted on activation. It touches no property,
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
 * Versions up to 0.4.0 granted it on activation. Change request 5 made the endpoint public, so the
 * plugin no longer grants or checks it — but a site that installed an earlier version still carries
 * it, and leaving it behind would outlive the plugin. Declared literally: there is no class constant
 * to read any more, and uninstall must work even when Composer dependencies are gone.
 */
$cccbr_orbitas_capability = 'cccbr_orbitas_read_propiedades';

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

unset( $cccbr_orbitas_capability, $cccbr_orbitas_roles, $cccbr_orbitas_role_name, $cccbr_orbitas_role );
