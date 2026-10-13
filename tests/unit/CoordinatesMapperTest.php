<?php
/**
 * Unit tests for the geocode cache mapper.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Tests;

use ElPuas\CCCBR\OrbitasPropiedades\Mapper\CoordinatesMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCode;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCollector;
use PHPUnit\Framework\TestCase;

/**
 * Covers the two hazards measured in the real data: error markers and a stale cache.
 */
final class CoordinatesMapperTest extends TestCase {

	/**
	 * Mapper under test.
	 *
	 * @var CoordinatesMapper
	 */
	private CoordinatesMapper $mapper;

	/**
	 * Issue sink.
	 *
	 * @var IssueCollector
	 */
	private IssueCollector $issues;

	/**
	 * Builds the mapper.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->mapper = new CoordinatesMapper();
		$this->issues = new IssueCollector();
	}

	/**
	 * Issue codes recorded so far.
	 *
	 * @return list<string>
	 */
	private function codes(): array {
		return array_column( $this->issues->all(), 'code' );
	}

	/**
	 * The meta prefix is the md5 of the field name, which is how JetEngine builds it.
	 *
	 * @return void
	 */
	public function test_meta_prefix_is_the_md5_of_the_field_name(): void {
		$this->assertSame( '4437ad177c1679b1090c78835bb6c221', $this->mapper->meta_prefix() );
		$this->assertSame( md5( 'mapa' ), $this->mapper->meta_prefix() );
	}

	/**
	 * A fresh cache yields coordinates and no issue.
	 *
	 * @return void
	 */
	public function test_valid_fresh_coordinates(): void {
		$address = 'Escazu, San Jose';

		$result = $this->mapper->map( '9.9281', '-84.0907', md5( $address ), $address, $this->issues );

		$this->assertSame(
			array(
				'lat'    => 9.9281,
				'lng'    => -84.0907,
				'source' => 'jetengine_geocode:mapa',
			),
			$result
		);
		$this->assertTrue( $this->issues->is_empty() );
	}

	/**
	 * A property with no cache is simply absent, not a data-quality problem.
	 *
	 * @return void
	 */
	public function test_no_cache_is_not_an_issue(): void {
		$this->assertNull( $this->mapper->map( null, null, null, '', $this->issues ) );
		$this->assertNull( $this->mapper->map( '', '', '', 'Some address', $this->issues ) );
		$this->assertTrue( $this->issues->is_empty() );
	}

	/**
	 * A geocoding error marker is never exposed as a coordinate.
	 *
	 * @return void
	 */
	public function test_error_marker_yields_null_and_an_issue(): void {
		$result = $this->mapper->map(
			'je-coord-error:google:empty',
			'je-coord-error:google:empty',
			md5( 'Nowhere' ),
			'Nowhere',
			$this->issues
		);

		$this->assertNull( $result );
		$this->assertSame( array( IssueCode::COORDINATES_GEOCODE_ERROR ), $this->codes() );
	}

	/**
	 * An error marker in only one of the two fields is still an error.
	 *
	 * @return void
	 */
	public function test_error_marker_in_one_field_only(): void {
		$this->assertNull( $this->mapper->map( 'je-coord-error', '-84.0907', '', '', $this->issues ) );
		$this->assertSame( array( IssueCode::COORDINATES_GEOCODE_ERROR ), $this->codes() );
	}

	/**
	 * Out-of-range and non-numeric values are reported as invalid.
	 *
	 * @return void
	 */
	public function test_invalid_values(): void {
		$this->assertNull( $this->mapper->map( '91', '0', '', '', $this->issues ) );
		$this->assertNull( $this->mapper->map( '0', '181', '', '', $this->issues ) );
		$this->assertNull( $this->mapper->map( 'north', 'west', '', '', $this->issues ) );

		$this->assertSame( array( IssueCode::COORDINATES_INVALID ), array_unique( $this->codes() ) );
	}

	/**
	 * A latitude with no longitude cannot be used.
	 *
	 * @return void
	 */
	public function test_half_a_pair_is_invalid(): void {
		$this->assertNull( $this->mapper->map( '9.9281', '', '', '', $this->issues ) );
		$this->assertSame( array( IssueCode::COORDINATES_INVALID ), $this->codes() );
	}

	/**
	 * When the address changed after geocoding, the coordinates are returned **with** a warning.
	 *
	 * @return void
	 */
	public function test_stale_cache_still_returns_coordinates(): void {
		$result = $this->mapper->map( '9.9281', '-84.0907', md5( 'Old address' ), 'New address', $this->issues );

		$this->assertIsArray( $result );
		$this->assertSame( 9.9281, $result['lat'] );
		$this->assertSame( array( IssueCode::COORDINATES_STALE ), $this->codes() );
	}

	/**
	 * A missing hash cannot prove staleness, so it must not claim it.
	 *
	 * @return void
	 */
	public function test_missing_hash_does_not_claim_staleness(): void {
		$result = $this->mapper->map( '9.9281', '-84.0907', '', 'Some address', $this->issues );

		$this->assertIsArray( $result );
		$this->assertTrue( $this->issues->is_empty() );
	}

	/**
	 * Integer zero coordinates are valid values, not emptiness.
	 *
	 * @return void
	 */
	public function test_zero_coordinates_are_valid(): void {
		$result = $this->mapper->map( '0', '0', '', '', $this->issues );

		$this->assertIsArray( $result );
		$this->assertSame( 0.0, $result['lat'] );
		$this->assertSame( 0.0, $result['lng'] );
	}
}
