<?php
/**
 * Guards the plugin header against regressions that would break activation or production parity.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Static metadata guard for the main plugin file.
 */
final class PluginHeaderTest extends TestCase {

	/**
	 * Parsed plugin header fields.
	 *
	 * @var array<string, string>
	 */
	private static array $header = array();

	/**
	 * Parses the main plugin file header once for the whole class.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		$file     = CCCBR_ORBITAS_PLUGIN_ROOT . '/cccbr-orbitas-propiedades.php';
		$contents = (string) file_get_contents( $file );

		preg_match_all( '/^\s*\*\s*([A-Za-z][A-Za-z ]*?):\s*(.+?)\s*$/m', $contents, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			self::$header[ $match[1] ] = $match[2];
		}
	}

	/**
	 * The header must declare the fields WordPress and the ticket require.
	 *
	 * @return void
	 */
	public function test_header_declares_required_fields(): void {
		$expected = array(
			'Plugin Name' => 'CCCBR Orbitas Propiedades',
			'Author'      => 'ElPuas Digital Crafts',
			'Text Domain' => 'cccbr-orbitas-propiedades',
			'License'     => 'GPL-2.0-or-later',
		);

		foreach ( $expected as $field => $value ) {
			$this->assertSame( $value, self::$header[ $field ] ?? null, "Header field {$field}" );
		}
	}

	/**
	 * The version must be SemVer.
	 *
	 * The number itself is deliberately not asserted — pinning it here would mean editing a test on
	 * every release for no protection. What matters is the format, and that the header and the
	 * constant agree, which {@see self::test_version_constant_matches_header()} checks.
	 *
	 * @return void
	 */
	public function test_version_is_semver(): void {
		$this->assertMatchesRegularExpression(
			'/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?$/',
			(string) ( self::$header['Version'] ?? '' )
		);
	}

	/**
	 * Production runs PHP 8.1.13 and WordPress 6.9: the header must not promise more.
	 *
	 * @return void
	 */
	public function test_header_targets_production_versions(): void {
		$this->assertSame( '8.1', self::$header['Requires PHP'] ?? null );
		$this->assertSame( '6.9', self::$header['Requires at least'] ?? null );
	}

	/**
	 * The header version and the version constant must not drift apart.
	 *
	 * @return void
	 */
	public function test_version_constant_matches_header(): void {
		$file     = CCCBR_ORBITAS_PLUGIN_ROOT . '/cccbr-orbitas-propiedades.php';
		$contents = (string) file_get_contents( $file );

		preg_match( "/CCCBR_ORBITAS_VERSION\s*=\s*'([^']+)'/", $contents, $match );

		$header_version = self::$header['Version'] ?? null;

		$this->assertSame( $header_version, $match[1] ?? null, 'CCCBR_ORBITAS_VERSION' );
	}
}
