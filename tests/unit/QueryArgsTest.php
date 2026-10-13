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
 * `__()`, so they need WordPress and are exercised over HTTP instead (the Postman collection and
 * the `curl` runs in `docs/progress.md`). Reading a class constant autoloads the class without
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
	 * The page-size bounds the contract promises.
	 *
	 * @return void
	 */
	public function test_page_size_bounds(): void {
		$this->assertSame( 50, QueryArgs::PER_PAGE_DEFAULT );
		$this->assertSame( 100, QueryArgs::PER_PAGE_MAX );
	}
}
