<?php
/**
 * Plugin activation behaviour.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Lifecycle;

use ElPuas\CCCBR\OrbitasPropiedades\Auth\Permissions;
use WP_Role;

/**
 * Grants the read capability on activation.
 *
 * This is the only thing the plugin persists. It creates no table, no option, no user and no
 * application password, and it never flushes rewrite rules: REST namespaces need no rewrite.
 */
final class Activator {

	/**
	 * Role that receives the capability on activation.
	 */
	private const TARGET_ROLE = 'administrator';

	/**
	 * Adds the read capability to the administrator role.
	 *
	 * Safe to run repeatedly: adding a capability a role already has is a no-op.
	 *
	 * @return void
	 */
	public function activate(): void {
		$role = get_role( self::TARGET_ROLE );

		if ( ! $role instanceof WP_Role ) {
			return;
		}

		if ( $role->has_cap( Permissions::CAPABILITY ) ) {
			return;
		}

		$role->add_cap( Permissions::CAPABILITY );
	}
}
