<?php
/**
 * Unit tests for the keyset cursor.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Tests;

use ElPuas\CCCBR\OrbitasPropiedades\Pagination\Cursor;
use PHPUnit\Framework\TestCase;

/**
 * The cursor decides `has_more` from the presence of one extra fetched row.
 */
final class CursorTest extends TestCase {

	/**
	 * An empty result set yields an empty page and no cursor.
	 *
	 * @return void
	 */
	public function test_empty_page_has_no_cursor(): void {
		$cursor = new Cursor( 50, array() );

		$this->assertSame( array(), $cursor->ids() );
		$this->assertNull( $cursor->next_after_id() );
		$this->assertFalse( $cursor->has_more() );
	}

	/**
	 * A partial page is the last page.
	 *
	 * @return void
	 */
	public function test_partial_page_is_the_last_page(): void {
		$cursor = new Cursor( 50, array( 101, 102, 103 ) );

		$this->assertSame( array( 101, 102, 103 ), $cursor->ids() );
		$this->assertSame( 103, $cursor->next_after_id() );
		$this->assertFalse( $cursor->has_more() );
	}

	/**
	 * Exactly `per_page` rows means the set ended on a page boundary: there is no more.
	 *
	 * @return void
	 */
	public function test_full_page_without_extra_row_is_the_last_page(): void {
		$cursor = new Cursor( 3, array( 1, 2, 3 ) );

		$this->assertSame( array( 1, 2, 3 ), $cursor->ids() );
		$this->assertSame( 3, $cursor->next_after_id() );
		$this->assertFalse( $cursor->has_more() );
	}

	/**
	 * The extra row is what reveals another page, and it never leaks into the payload.
	 *
	 * @return void
	 */
	public function test_extra_row_sets_has_more_and_is_dropped(): void {
		$cursor = new Cursor( 3, array( 1, 2, 3, 4 ) );

		$this->assertSame( array( 1, 2, 3 ), $cursor->ids() );
		$this->assertSame( 3, $cursor->next_after_id(), 'The cursor must be the last ID of the page, not of the fetch.' );
		$this->assertTrue( $cursor->has_more() );
	}

	/**
	 * A page size of one still paginates correctly.
	 *
	 * @return void
	 */
	public function test_page_size_of_one(): void {
		$cursor = new Cursor( 1, array( 80187, 80188 ) );

		$this->assertSame( array( 80187 ), $cursor->ids() );
		$this->assertSame( 80187, $cursor->next_after_id() );
		$this->assertTrue( $cursor->has_more() );
	}

	/**
	 * A nonsensical page size cannot produce a negative slice.
	 *
	 * @return void
	 */
	public function test_non_positive_page_size_is_clamped_to_one(): void {
		$cursor = new Cursor( 0, array( 7, 8 ) );

		$this->assertSame( array( 7 ), $cursor->ids() );
		$this->assertTrue( $cursor->has_more() );
	}

	/**
	 * Walking the whole set with the cursor visits every ID exactly once.
	 *
	 * @return void
	 */
	public function test_walking_a_set_visits_every_id_once(): void {
		$all      = range( 1000, 1024 );
		$per_page = 7;
		$after    = 0;
		$seen     = array();

		do {
			$remaining = array_values(
				array_filter(
					$all,
					static function ( int $id ) use ( $after ): bool {
						return $id > $after;
					}
				)
			);

			$cursor = new Cursor( $per_page, array_slice( $remaining, 0, $per_page + 1 ) );

			foreach ( $cursor->ids() as $id ) {
				$seen[] = $id;
			}

			$after = (int) $cursor->next_after_id();
		} while ( $cursor->has_more() );

		$this->assertSame( $all, $seen );
		$this->assertSame( count( $all ), count( array_unique( $seen ) ), 'No ID may repeat.' );
	}
}
