<?php
/**
 * Inspects the production zip and asserts it is shippable.
 *
 * Usage: `composer run build:verify`.
 *
 * Lists every entry, then checks four things that a human reading a file list would otherwise have
 * to check by eye:
 *
 * 1. the plugin's own runtime files are present, `vendor/autoload.php` included — the main file
 *    needs it and does nothing without it;
 * 2. no development dependency shipped (PHPCS, PHPStan, PHPUnit and the packages they pull in);
 * 3. nothing listed in `.distignore` shipped;
 * 4. the archive has exactly one top-level folder, named after the slug.
 *
 * There is deliberately no `declare(strict_types=1)` here, for the same reason as `build.php`.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

namespace ElPuas\CCCBR\OrbitasPropiedades\Build;

/**
 * Plugin slug, which is also the zip's top-level folder.
 */
const VERIFY_SLUG = 'cccbr-orbitas-propiedades';

/**
 * Package names that must never reach the zip.
 *
 * The six direct dev requirements plus the transitive packages Composer installs with them, which
 * is where an accidental `composer install` without `--no-dev` would show up first.
 *
 * @var list<string>
 */
const DEV_PACKAGES = array(
	'phpunit',
	'phpstan',
	'squizlabs',
	'phpcsstandards',
	'wp-coding-standards',
	'phpcompatibility',
	'dealerdirect',
	'szepeviktor',
	'php-stubs',
	'sebastian',
	'phar-io',
	'myclabs',
	'doctrine',
	'nikic',
	'theseer',
	'symfony',
	'psr',
);

/**
 * Runs the verification.
 *
 * @return void
 */
function verify(): void {
	$root = str_replace( '\\', '/', dirname( __DIR__ ) );
	$path = $root . '/dist/' . VERIFY_SLUG . '.zip';

	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, 'No zip at dist/' . VERIFY_SLUG . '.zip — run `composer run build` first.' . PHP_EOL );

		exit( 1 );
	}

	$zip = new \ZipArchive();

	if ( true !== $zip->open( $path ) ) {
		fwrite( STDERR, 'Could not open ' . $path . PHP_EOL );

		exit( 1 );
	}

	$entries = array();
	$files   = array();
	$bytes   = 0;

	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ZipArchive's own property name.
	$count = (int) $zip->numFiles;

	for ( $i = 0; $i < $count; $i++ ) {
		$stat = $zip->statIndex( $i );

		if ( false === $stat ) {
			continue;
		}

		$entries[] = (string) $stat['name'];

		if ( '/' !== substr( (string) $stat['name'], -1 ) ) {
			$files[] = (string) $stat['name'];
			$bytes  += (int) $stat['size'];
		}
	}

	$zip->close();
	sort( $entries );
	sort( $files );

	printf( '# Contents of dist/%s.zip%s%s', VERIFY_SLUG, PHP_EOL, PHP_EOL );
	printf(
		'%d files, %s KB uncompressed, archive %s KB%s%s',
		count( $files ),
		number_format( (float) $bytes / 1024, 1 ),
		number_format( (float) filesize( $path ) / 1024, 1 ),
		PHP_EOL,
		PHP_EOL
	);

	foreach ( $files as $file ) {
		printf( '  %s%s', $file, PHP_EOL );
	}

	$problems = array();

	// 1. Everything the plugin needs at runtime.
	$required = array(
		VERIFY_SLUG . '/' . VERIFY_SLUG . '.php',
		VERIFY_SLUG . '/uninstall.php',
		VERIFY_SLUG . '/vendor/autoload.php',
		VERIFY_SLUG . '/src/Plugin.php',
		VERIFY_SLUG . '/src/Factory.php',
		VERIFY_SLUG . '/src/Rest/PropertiesController.php',
	);

	foreach ( $required as $needed ) {
		if ( ! in_array( $needed, $files, true ) ) {
			$problems[] = 'missing runtime file: ' . $needed;
		}
	}

	// 2. No development dependency.
	foreach ( $files as $file ) {
		if ( 0 !== strpos( $file, VERIFY_SLUG . '/vendor/' ) ) {
			continue;
		}

		foreach ( DEV_PACKAGES as $package ) {
			if ( false !== stripos( $file, '/' . $package ) ) {
				$problems[] = 'development dependency shipped: ' . $file;

				break;
			}
		}
	}

	// 3. Nothing the .distignore excludes.
	$forbidden = array( 'tests/', 'tools/', 'docs/', 'postman/', '.claude/', 'dist/' );

	foreach ( $forbidden as $prefix ) {
		foreach ( $files as $file ) {
			if ( 0 === strpos( $file, VERIFY_SLUG . '/' . $prefix ) ) {
				$problems[] = 'development path shipped: ' . $file;
			}
		}
	}

	foreach ( array( 'composer.json', 'composer.lock', 'CLAUDE.md', 'LOOP_PROMPT.md', 'phpcs.xml.dist', 'phpstan.neon.dist', 'phpunit.xml.dist', '.distignore', '.gitignore' ) as $name ) {
		if ( in_array( VERIFY_SLUG . '/' . $name, $files, true ) ) {
			$problems[] = 'development file shipped: ' . $name;
		}
	}

	// 4. Exactly one top-level folder.
	$tops = array();

	foreach ( $entries as $entry ) {
		$tops[ strtok( $entry, '/' ) ] = true;
	}

	if ( array( VERIFY_SLUG ) !== array_keys( $tops ) ) {
		$problems[] = 'top-level folders are ' . implode( ', ', array_keys( $tops ) ) . ' — expected only ' . VERIFY_SLUG;
	}

	printf( '%s## Checks%s%s', PHP_EOL, PHP_EOL, PHP_EOL );

	if ( array() === $problems ) {
		printf( '  OK  runtime files present, including vendor/autoload.php%s', PHP_EOL );
		printf( '  OK  no development dependency in vendor/%s', PHP_EOL );
		printf( '  OK  no tests, tools, docs, postman, .claude, manifests or tool configs%s', PHP_EOL );
		printf( '  OK  exactly one top-level folder: %s/%s', VERIFY_SLUG, PHP_EOL );
		printf( '%sThe archive is shippable.%s', PHP_EOL, PHP_EOL );

		return;
	}

	foreach ( $problems as $problem ) {
		printf( '  FAIL  %s%s', $problem, PHP_EOL );
	}

	fwrite( STDERR, PHP_EOL . count( $problems ) . ' problem(s): the archive is NOT shippable.' . PHP_EOL );

	exit( 1 );
}

verify();
