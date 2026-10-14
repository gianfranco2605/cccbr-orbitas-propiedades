<?php
/**
 * Unit tests for the exported status set.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Tests;

use ElPuas\CCCBR\OrbitasPropiedades\Rest\QueryArgs;
use PHPUnit\Framework\TestCase;

/**
 * Guards `QueryArgs::ALLOWED_STATUSES`, which is a four-way decision in one constant.
 *
 * Only the constant is covered here: the validation methods build `WP_Error` objects and call
 * `__()`, so they need WordPress and are exercised over HTTP instead (the `curl` runs in
 * `docs/progress.md`). Reading a class constant autoloads the class without
 * touching either, which is why this works in a WordPress-free suite.
 */
final class QueryArgsTest extends TestCase {

	/**
	 * The exported set, exactly.
	 *
	 * This constant simultaneously decides the default page, the `totals` population, which
	 * statuses `find_posts()` will load, and which `status` values are accepted — so a careless
	 * edit here changes four behaviours at once.
	 *
	 * @return void
	 */
	public function test_the_exported_status_set_is_exact(): void {
		$this->assertSame(
			array( 'publish', 'draft', 'pending', 'private', 'future' ),
			QueryArgs::ALLOWED_STATUSES
		);
	}

	/**
	 * Trashed properties must never be exported: CCCBR decided they are not sent to Orbitas.
	 *
	 * @return void
	 */
	public function test_trash_is_not_exported(): void {
		$this->assertNotContains(
			'trash',
			QueryArgs::ALLOWED_STATUSES,
			'Trashed properties must not reach Orbitas, by the client\'s decision.'
		);
	}

	/**
	 * `auto-draft` has no property data and `inherit` belongs to revisions and attachments.
	 *
	 * @return void
	 */
	public function test_internal_wordpress_statuses_are_not_exported(): void {
		$this->assertNotContains( 'auto-draft', QueryArgs::ALLOWED_STATUSES );
		$this->assertNotContains( 'inherit', QueryArgs::ALLOWED_STATUSES );
	}

	/**
	 * Every entry is a non-empty string, with no duplicates.
	 *
	 * @return void
	 */
	public function test_the_set_is_well_formed(): void {
		$statuses = QueryArgs::ALLOWED_STATUSES;

		$this->assertNotEmpty( $statuses );
		$this->assertSame( $statuses, array_values( array_unique( $statuses ) ), 'No duplicates.' );

		foreach ( $statuses as $status ) {
			$this->assertIsString( $status );
			$this->assertNotSame( '', $status );
		}
	}

	/**
	 * Values the single-property route must accept as an id.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_valid_ids(): array {
		return array(
			'string digits'        => array( '184589' ),
			'native integer'       => array( 184589 ),
			'one'                  => array( '1' ),
			'surrounded by spaces' => array( '  42  ' ),
			'leading zeros'        => array( '007' ),
		);
	}

	/**
	 * A positive whole number is a usable id.
	 *
	 * @dataProvider provide_valid_ids
	 *
	 * @param mixed $value Raw parameter value.
	 * @return void
	 */
	public function test_valid_ids_are_accepted( $value ): void {
		$this->assertTrue( QueryArgs::is_positive_integer( $value ) );
	}

	/**
	 * Values that are not a usable post id.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_invalid_ids(): array {
		return array(
			'zero'            => array( '0' ),
			'native zero'     => array( 0 ),
			'negative'        => array( '-1' ),
			'decimal'         => array( '1.5' ),
			'words'           => array( 'abc' ),
			'mixed'           => array( '12abc' ),
			'empty string'    => array( '' ),
			'whitespace only' => array( '   ' ),
			'null'            => array( null ),
			'array'           => array( array( 1 ) ),
			'boolean true'    => array( true ),
			'scientific'      => array( '1e3' ),
		);
	}

	/**
	 * Anything that is not a positive whole number is rejected.
	 *
	 * `0` matters in particular: the route pattern `(?P<id>\d+)` matches it, so only this rule
	 * keeps `/propiedades/0` from reaching the lookup.
	 *
	 * @dataProvider provide_invalid_ids
	 *
	 * @param mixed $value Raw parameter value.
	 * @return void
	 */
	public function test_invalid_ids_are_rejected( $value ): void {
		$this->assertFalse( QueryArgs::is_positive_integer( $value ) );
	}

	/**
	 * The page-size bounds the contract promises.
	 *
	 * @return void
	 */
	public function test_page_size_bounds(): void {
		$this->assertSame( 50, QueryArgs::PER_PAGE_DEFAULT );
		$this->assertSame( 100, QueryArgs::PER_PAGE_MAX );
	}
}
