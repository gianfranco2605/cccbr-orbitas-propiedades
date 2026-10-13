<?php
/**
 * Unit tests for the location level resolver.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Tests;

use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCode;
use ElPuas\CCCBR\OrbitasPropiedades\Support\LocationResolver;
use PHPUnit\Framework\TestCase;

/**
 * The resolver must never pick between conflicting terms: an invented location is worse than none.
 */
final class LocationResolverTest extends TestCase {

	/**
	 * Resolver under test.
	 *
	 * @var LocationResolver
	 */
	private LocationResolver $resolver;

	/**
	 * Builds the resolver.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->resolver = new LocationResolver();
	}

	/**
	 * A three-level taxonomy shaped like the real one: two provinces, two cantones each.
	 *
	 * @return array<int, array{name: string, slug: string, parent: int}>
	 */
	private function index(): array {
		return array(
			// Level 1.
			10 => array(
				'name'   => 'San José',
				'slug'   => 'san-jose',
				'parent' => 0,
			),
			11 => array(
				'name'   => 'Alajuela',
				'slug'   => 'alajuela',
				'parent' => 0,
			),
			// Level 2.
			20 => array(
				'name'   => 'Santa Ana',
				'slug'   => 'santa-ana',
				'parent' => 10,
			),
			21 => array(
				'name'   => 'Escazú',
				'slug'   => 'escazu',
				'parent' => 10,
			),
			22 => array(
				'name'   => 'San Carlos',
				'slug'   => 'san-carlos',
				'parent' => 11,
			),
			// Level 3.
			30 => array(
				'name'   => 'Pozos',
				'slug'   => 'pozos',
				'parent' => 20,
			),
			31 => array(
				'name'   => 'San Rafael',
				'slug'   => 'san-rafael',
				'parent' => 21,
			),
			32 => array(
				'name'   => 'La Tigra',
				'slug'   => 'la-tigra',
				'parent' => 22,
			),
		);
	}

	/**
	 * Issue codes the resolver reported.
	 *
	 * @param array $result Resolver result.
	 * @return list<string>
	 *
	 * @phpstan-param array{location: array<string, mixed>, issues: list<array{code: string, detail: string|null}>} $result
	 */
	private function codes( array $result ): array {
		return array_column( $result['issues'], 'code' );
	}

	/**
	 * A resolved level, asserted to be present.
	 *
	 * Keeps the assertions readable and gives static analysis a non-nullable array to work with.
	 *
	 * @param array  $result Resolver result.
	 * @param string $member province, canton or district.
	 * @return array<string, mixed>
	 *
	 * @phpstan-param array{location: array<string, mixed>, issues: list<array{code: string, detail: string|null}>} $result
	 */
	private function level( array $result, string $member ): array {
		$term = $result['location'][ $member ] ?? null;

		$this->assertIsArray( $term, $member . ' should have been resolved' );

		return $term;
	}

	/**
	 * The ordinary case: one term per level, consistent lineage.
	 *
	 * @return void
	 */
	public function test_full_consistent_path(): void {
		$result = $this->resolver->resolve( array( 10, 20, 30 ), $this->index() );

		$this->assertSame(
			array(
				'id'   => 10,
				'name' => 'San José',
				'slug' => 'san-jose',
			),
			$result['location']['province']
		);
		$this->assertSame( 20, $this->level( $result, 'canton' )['id'] );
		$this->assertSame( 30, $this->level( $result, 'district' )['id'] );
		$this->assertSame( array(), $result['issues'], 'A clean path must raise nothing.' );
	}

	/**
	 * Term order in the source is arbitrary and must not matter.
	 *
	 * @return void
	 */
	public function test_order_does_not_matter(): void {
		$result = $this->resolver->resolve( array( 30, 10, 20 ), $this->index() );

		$this->assertSame( 10, $this->level( $result, 'province' )['id'] );
		$this->assertSame( 20, $this->level( $result, 'canton' )['id'] );
		$this->assertSame( 30, $this->level( $result, 'district' )['id'] );
		$this->assertSame( array(), $result['issues'] );
	}

	/**
	 * No terms at all.
	 *
	 * @return void
	 */
	public function test_no_terms_reports_missing(): void {
		$result = $this->resolver->resolve( array(), $this->index() );

		$this->assertSame(
			array(
				'province' => null,
				'canton'   => null,
				'district' => null,
			),
			$result['location']
		);
		$this->assertSame( array( IssueCode::LOCATION_MISSING ), $this->codes( $result ) );
	}

	/**
	 * Terms that are not in the taxonomy are no terms at all.
	 *
	 * @return void
	 */
	public function test_unknown_terms_report_missing(): void {
		$result = $this->resolver->resolve( array( 9999 ), $this->index() );

		$this->assertNull( $result['location']['province'] );
		$this->assertSame( array( IssueCode::LOCATION_MISSING ), $this->codes( $result ) );
	}

	/**
	 * A province on its own is a valid, if partial, answer.
	 *
	 * @return void
	 */
	public function test_province_only_is_incomplete(): void {
		$result = $this->resolver->resolve( array( 10 ), $this->index() );

		$this->assertSame( 10, $this->level( $result, 'province' )['id'] );
		$this->assertNull( $result['location']['canton'] );
		$this->assertNull( $result['location']['district'] );
		$this->assertSame( array( IssueCode::LOCATION_INCOMPLETE ), $this->codes( $result ) );
		$this->assertSame( 'canton,district', $result['issues'][0]['detail'] );
	}

	/**
	 * A distrito alone implies its cantón and province through its own ancestry — which is a fact
	 * in the taxonomy, not a guess.
	 *
	 * @return void
	 */
	public function test_district_alone_is_completed_from_its_own_ancestors(): void {
		$result = $this->resolver->resolve( array( 30 ), $this->index() );

		$this->assertSame( 30, $this->level( $result, 'district' )['id'] );
		$this->assertSame( 20, $this->level( $result, 'canton' )['id'] );
		$this->assertSame( 10, $this->level( $result, 'province' )['id'] );
		$this->assertSame( array(), $result['issues'], 'Completing from ancestry is not a data problem.' );
	}

	/**
	 * Two cantones: there is no way to tell which one, so the level is cleared and reported.
	 *
	 * @return void
	 */
	public function test_two_terms_on_one_level_is_ambiguous(): void {
		$result = $this->resolver->resolve( array( 10, 20, 21 ), $this->index() );

		$this->assertSame( 10, $this->level( $result, 'province' )['id'] );
		$this->assertNull( $result['location']['canton'], 'A contested level must not be guessed.' );
		$this->assertContains( IssueCode::LOCATION_AMBIGUOUS, $this->codes( $result ) );
		$this->assertContains( IssueCode::LOCATION_INCOMPLETE, $this->codes( $result ) );
	}

	/**
	 * A distrito whose parent is not the assigned cantón: the deeper level is the contradicted one.
	 *
	 * @return void
	 */
	public function test_lineage_conflict_clears_the_deeper_level(): void {
		// Pozos (30) belongs to Santa Ana (20), not to Escazú (21).
		$result = $this->resolver->resolve( array( 10, 21, 30 ), $this->index() );

		$this->assertSame( 10, $this->level( $result, 'province' )['id'] );
		$this->assertSame( 21, $this->level( $result, 'canton' )['id'], 'The cantón was assigned and stays.' );
		$this->assertNull( $result['location']['district'], 'Its ancestry contradicts the cantón.' );

		$codes = $this->codes( $result );
		$this->assertContains( IssueCode::LOCATION_AMBIGUOUS, $codes );
		$this->assertContains( IssueCode::LOCATION_INCOMPLETE, $codes );
	}

	/**
	 * A cantón from another province is the same kind of conflict, one level up.
	 *
	 * @return void
	 */
	public function test_canton_from_another_province_is_ambiguous(): void {
		// San Carlos (22) belongs to Alajuela (11), not to San José (10).
		$result = $this->resolver->resolve( array( 10, 22 ), $this->index() );

		$this->assertSame( 10, $this->level( $result, 'province' )['id'] );
		$this->assertNull( $result['location']['canton'] );
		$this->assertContains( IssueCode::LOCATION_AMBIGUOUS, $this->codes( $result ) );
	}

	/**
	 * Two provinces leaves nothing resolvable at all.
	 *
	 * @return void
	 */
	public function test_two_provinces_leaves_nothing(): void {
		$result = $this->resolver->resolve( array( 10, 11 ), $this->index() );

		$this->assertNull( $result['location']['province'] );
		$codes = $this->codes( $result );
		$this->assertContains( IssueCode::LOCATION_AMBIGUOUS, $codes );
		$this->assertContains( IssueCode::LOCATION_MISSING, $codes, 'Nothing could be resolved.' );
	}

	/**
	 * A repeated term is one term.
	 *
	 * @return void
	 */
	public function test_duplicate_term_is_not_a_conflict(): void {
		$result = $this->resolver->resolve( array( 20, 20, 10 ), $this->index() );

		$this->assertSame( 20, $this->level( $result, 'canton' )['id'] );
		$this->assertSame( array( IssueCode::LOCATION_INCOMPLETE ), $this->codes( $result ) );
	}

	/**
	 * A term deeper than the taxonomy's three levels is reported rather than placed.
	 *
	 * @return void
	 */
	public function test_term_deeper_than_three_levels_is_ambiguous(): void {
		$index     = $this->index();
		$index[40] = array(
			'name'   => 'Too deep',
			'slug'   => 'too-deep',
			'parent' => 30,
		);
		$result    = $this->resolver->resolve( array( 40 ), $index );

		$this->assertContains( IssueCode::LOCATION_AMBIGUOUS, $this->codes( $result ) );
		$this->assertSame( '40', $result['issues'][0]['detail'] );
	}

	/**
	 * Every member is always present, so a consumer can read it positionally.
	 *
	 * @return void
	 */
	public function test_the_three_members_are_always_present(): void {
		foreach ( array( array(), array( 10 ), array( 10, 20, 30 ), array( 9999 ) ) as $assigned ) {
			$result = $this->resolver->resolve( $assigned, $this->index() );

			$this->assertSame(
				array( 'province', 'canton', 'district' ),
				array_keys( $result['location'] )
			);
		}
	}
}
