<?php
/**
 * Keyset pagination state.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Pagination;

/**
 * Turns a "page size plus one" result set into the page itself and its cursor.
 *
 * The repository always asks the database for one row more than the caller wanted. The presence of
 * that extra row is what tells us whether another page exists, which keeps `has_more` correct even
 * while properties are being added or removed — a count comparison would not.
 *
 * This class touches no WordPress function, so it is covered by the unit suite.
 */
final class Cursor {

	/**
	 * Requested page size.
	 *
	 * @var int
	 */
	private int $per_page;

	/**
	 * IDs on this page, already trimmed to the page size.
	 *
	 * @var list<int>
	 */
	private array $ids;

	/**
	 * Whether the database held at least one row beyond this page.
	 *
	 * @var bool
	 */
	private bool $has_more;

	/**
	 * Constructor.
	 *
	 * @param int   $per_page Requested page size, 1 or more.
	 * @param array $fetched  IDs as fetched, in ascending order, containing up to `$per_page + 1` entries.
	 *
	 * @phpstan-param list<int> $fetched
	 */
	public function __construct( int $per_page, array $fetched ) {
		$this->per_page = max( 1, $per_page );
		$this->has_more = count( $fetched ) > $this->per_page;
		$this->ids      = array_values( array_slice( $fetched, 0, $this->per_page ) );
	}

	/**
	 * IDs belonging to this page.
	 *
	 * @return list<int>
	 */
	public function ids(): array {
		return $this->ids;
	}

	/**
	 * Cursor to pass as the next `after_id`.
	 *
	 * @return int|null The last ID of this page, or null when the page is empty.
	 */
	public function next_after_id(): ?int {
		if ( array() === $this->ids ) {
			return null;
		}

		return $this->ids[ count( $this->ids ) - 1 ];
	}

	/**
	 * Whether at least one more property exists after this page.
	 *
	 * @return bool
	 */
	public function has_more(): bool {
		return $this->has_more;
	}
}
