<?php
/**
 * Builds the production zip.
 *
 * Usage: `composer run build`.
 *
 * Produces `dist/cccbr-orbitas-propiedades.zip`, whose single top-level folder is
 * `cccbr-orbitas-propiedades/` so WordPress installs it under that slug.
 *
 * The build never touches the repository's own `vendor/`. It copies the shippable files into a
 * temporary directory, runs `composer install --no-dev --optimize-autoloader` **there**, deletes
 * the manifests it needed for that, and zips the result. The development `vendor/` — PHPCS,
 * PHPStan, PHPUnit and their dependencies — stays exactly as it was, so `composer cs` and friends
 * keep working immediately after a build.
 *
 * `vendor/` is shipped because the plugin requires it at runtime: the main file includes
 * `vendor/autoload.php` and shows an admin notice instead of doing anything when it is missing.
 * After `--no-dev` that directory holds nothing but Composer's own autoloader, because the plugin
 * has no production dependencies.
 *
 * There is deliberately no `declare(strict_types=1)` here: this file is also readable by
 * `wp eval-file`, which strips the opening tag and evaluates the rest, where PHP rejects a
 * strict-types declaration that is no longer the first statement.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

namespace ElPuas\CCCBR\OrbitasPropiedades\Build;

/**
 * Plugin slug, which is also the zip's top-level folder.
 */
const SLUG = 'cccbr-orbitas-propiedades';

/**
 * Prints a progress line.
 *
 * @param string $message Text to print.
 * @return void
 */
function say( string $message ): void {
	echo $message, PHP_EOL;
}

/**
 * Stops the build with a message and a non-zero exit code.
 *
 * @param string $message Reason.
 * @return void
 */
function fail( string $message ): void {
	fwrite( STDERR, 'BUILD FAILED: ' . $message . PHP_EOL );

	exit( 1 );
}

/**
 * Reads `.distignore` into a list of path prefixes.
 *
 * @param string $root Plugin root.
 * @return list<string>
 */
function excluded_paths( string $root ): array {
	$file = $root . '/.distignore';

	if ( ! is_readable( $file ) ) {
		fail( '.distignore is missing — refusing to guess what should ship.' );
	}

	$paths = array();

	foreach ( (array) file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
		$line = trim( (string) $line );

		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}

		$paths[] = trim( $line, '/' );
	}

	return $paths;
}

/**
 * Whether a path relative to the plugin root is excluded.
 *
 * @param string $relative Relative path, with forward slashes.
 * @param array  $excluded Exclusion list.
 * @return bool
 *
 * @phpstan-param list<string> $excluded
 */
function is_excluded( string $relative, array $excluded ): bool {
	foreach ( $excluded as $pattern ) {
		if ( $relative === $pattern || 0 === strpos( $relative, $pattern . '/' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Copies the shippable tree into the build directory.
 *
 * `vendor/` is skipped here on purpose: the copy gets its own, production-only one from
 * `composer install --no-dev` a moment later.
 *
 * @param string $root     Plugin root.
 * @param string $target   Build directory.
 * @param array  $excluded Exclusion list.
 * @return int Number of files copied.
 *
 * @phpstan-param list<string> $excluded
 */
function copy_tree( string $root, string $target, array $excluded ): int {
	$iterator = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $root, \FilesystemIterator::SKIP_DOTS ),
		\RecursiveIteratorIterator::SELF_FIRST
	);

	$copied = 0;

	foreach ( $iterator as $item ) {
		$path     = str_replace( '\\', '/', $item->getPathname() );
		$relative = ltrim( substr( $path, strlen( $root ) ), '/' );

		if ( '' === $relative || is_excluded( $relative, $excluded ) || 0 === strpos( $relative, 'vendor/' ) ) {
			continue;
		}

		$destination = $target . '/' . $relative;

		if ( $item->isDir() ) {
			if ( ! is_dir( $destination ) && ! mkdir( $destination, 0755, true ) ) {
				fail( 'could not create ' . $destination );
			}

			continue;
		}

		$parent = dirname( $destination );

		if ( ! is_dir( $parent ) && ! mkdir( $parent, 0755, true ) ) {
			fail( 'could not create ' . $parent );
		}

		if ( ! copy( $path, $destination ) ) {
			fail( 'could not copy ' . $relative );
		}

		++$copied;
	}

	return $copied;
}

/**
 * Deletes a directory tree.
 *
 * @param string $path Directory to remove.
 * @return void
 */
function remove_tree( string $path ): void {
	if ( ! is_dir( $path ) ) {
		return;
	}

	$iterator = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
		\RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		if ( $item->isDir() ) {
			rmdir( $item->getPathname() );

			continue;
		}

		unlink( $item->getPathname() );
	}

	rmdir( $path );
}

/**
 * Runs the build.
 *
 * @return void
 */
function build(): void {
	$root = str_replace( '\\', '/', dirname( __DIR__ ) );

	if ( ! is_readable( $root . '/' . SLUG . '.php' ) ) {
		fail( 'the plugin main file is not where it should be: ' . $root );
	}

	$excluded = excluded_paths( $root );
	$dist     = $root . '/dist';
	$work     = $dist . '/build-' . getmypid();
	$staged   = $work . '/' . SLUG;
	$zip_path = $dist . '/' . SLUG . '.zip';

	remove_tree( $work );

	if ( ! is_dir( $staged ) && ! mkdir( $staged, 0755, true ) ) {
		fail( 'could not create the build directory ' . $staged );
	}

	say( 'Staging the shippable files…' );

	$copied = copy_tree( $root, $staged, $excluded );

	say( '  ' . $copied . ' file(s) staged' );

	// The manifests are needed to resolve the production autoloader and are removed again below,
	// so they never reach the zip.
	foreach ( array( 'composer.json', 'composer.lock' ) as $manifest ) {
		if ( is_readable( $root . '/' . $manifest ) && ! copy( $root . '/' . $manifest, $staged . '/' . $manifest ) ) {
			fail( 'could not stage ' . $manifest );
		}
	}

	say( 'Installing production dependencies in the copy (the repository vendor/ is untouched)…' );

	$command = sprintf(
		'cd %s && composer install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1',
		escapeshellarg( $staged )
	);

	$output = array();
	$status = 0;

	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- This is a build script run from the command line by a developer, never by WordPress; shelling out to Composer is its entire job.
	exec( $command, $output, $status );

	if ( 0 !== $status ) {
		say( '  ' . implode( PHP_EOL . '  ', $output ) );

		fail( 'composer install --no-dev failed in the build copy' );
	}

	if ( ! is_readable( $staged . '/vendor/autoload.php' ) ) {
		fail( 'vendor/autoload.php is missing from the build — the plugin needs it at runtime' );
	}

	foreach ( array( 'composer.json', 'composer.lock' ) as $manifest ) {
		if ( file_exists( $staged . '/' . $manifest ) ) {
			unlink( $staged . '/' . $manifest );
		}
	}

	say( 'Creating the archive…' );

	if ( file_exists( $zip_path ) ) {
		unlink( $zip_path );
	}

	$zip = new \ZipArchive();

	if ( true !== $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
		fail( 'could not open ' . $zip_path . ' for writing' );
	}

	$entries  = 0;
	$iterator = new \RecursiveIteratorIterator(
		new \RecursiveDirectoryIterator( $staged, \FilesystemIterator::SKIP_DOTS ),
		\RecursiveIteratorIterator::SELF_FIRST
	);

	$files = array();

	foreach ( $iterator as $item ) {
		$files[] = str_replace( '\\', '/', $item->getPathname() );
	}

	sort( $files );

	foreach ( $files as $path ) {
		$entry = SLUG . '/' . ltrim( substr( $path, strlen( $staged ) ), '/' );

		if ( is_dir( $path ) ) {
			$zip->addEmptyDir( $entry );

			continue;
		}

		$zip->addFile( $path, $entry );
		++$entries;
	}

	if ( ! $zip->close() ) {
		fail( 'could not write ' . $zip_path );
	}

	remove_tree( $work );

	say( '' );
	say( 'Built ' . str_replace( $root . '/', '', $zip_path ) );
	say( '  ' . $entries . ' file(s), ' . number_format( (float) filesize( $zip_path ) / 1024, 1 ) . ' KB' );
	say( '  top-level folder: ' . SLUG . '/' );
	say( '' );
	say( 'Verify with:  composer run build:verify' );
}

build();
