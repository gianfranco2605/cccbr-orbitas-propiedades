<?php
/**
 * Plugin activation behaviour.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Lifecycle;

/**
 * Activation hook.
 *
 * Since change request 5 (14 Oct 2026) the endpoint is public, so there is no capability to grant
 * and activation persists **nothing**: no capability, no table, no option, no user and no
 * application password. It never flushes rewrite rules either — REST namespaces need no rewrite.
 *
 * The class is kept so the activation hook keeps a single documented home, and because
 * `uninstall.php` still removes the capability that versions up to 0.4.0 granted.
 */
final class Activator {

	/**
	 * Does nothing, on purpose.
	 *
	 * @return void
	 */
	public function activate(): void {
		// The endpoint is public: there is nothing to persist.
	}
}
