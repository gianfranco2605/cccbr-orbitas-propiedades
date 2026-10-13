<?php
/**
 * Authorization for the Orbitas properties endpoint.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Auth;

use WP_Error;

/**
 * Decides whether the current request may read the properties collection.
 *
 * Authentication itself is WordPress Application Passwords (HTTP Basic); this class only answers
 * the authorization question, and distinguishes "not authenticated" (401) from "authenticated but
 * not allowed" (403) so the consumer can tell a bad credential from a missing permission.
 */
final class Permissions {

	/**
	 * Capability required to read the properties collection.
	 *
	 * Granted to the administrator role on activation and removed from every role on uninstall.
	 * The plugin never creates users or application passwords.
	 */
	public const CAPABILITY = 'cccbr_orbitas_read_propiedades';

	/**
	 * REST permission callback.
	 *
	 * @return true|WP_Error True when the request may proceed, WP_Error with a 401 or 403 status otherwise.
	 */
	public function check() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'cccbr_orbitas_not_authenticated',
				__( 'Authentication is required. Send a WordPress Application Password using HTTP Basic authentication.', 'cccbr-orbitas-propiedades' ),
				array( 'status' => 401 )
			);
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return new WP_Error(
				'cccbr_orbitas_forbidden',
				__( 'This account is not allowed to read CCCBR properties.', 'cccbr-orbitas-propiedades' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
