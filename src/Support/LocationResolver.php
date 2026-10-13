<?php
/**
 * Resolves assigned location terms into province, cantón and distrito.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Support;

/**
 * Turns the terms assigned to a property into one `{ province, canton, district }` object.
 *
 * `ubicacion-propiedad` is exactly three levels deep and its seven root terms are the seven
 * provinces of Costa Rica, which the property form confirms by labelling the three selects
 * "Provincia / Cantón / Distrito". So the taxonomy depth **is** the administrative level, and the
 * three levels can be named instead of returned as an anonymous ladder.
 *
 * The source does not guarantee one term per level. A property can carry two cantones, or a
 * distrito whose parent is not the cantón also assigned to it. **Nothing is guessed:** a level with
 * a conflict is set to `null` and the conflict is reported, because inventing a location is worse
 * than admitting there is none.
 *
 * Pure PHP, no WordPress: covered by the unit suite.
 */
final class LocationResolver {

	/**
	 * Depth of the taxonomy, and the contract member each depth maps to.
	 *
	 * @var array<int, string>
	 */
	public const LEVELS = array(
		1 => 'province',
		2 => 'canton',
		3 => 'district',
	);

	/**
	 * Resolves the assigned terms.
	 *
	 * @param array $assigned_ids Term IDs assigned to the property, in any order.
	 * @param array $index        The whole taxonomy: term ID => name, slug and parent.
	 * @return array{location: array<string, array{id: int, name: string, slug: string}|null>, issues: list<array{code: string, detail: string|null}>}
	 *
	 * @phpstan-param list<int> $assigned_ids
	 * @phpstan-param array<int, array{name: string, slug: string, parent: int}> $index
	 */
	public function resolve( array $assigned_ids, array $index ): array {
		$location = array(
			'province' => null,
			'canton'   => null,
			'district' => null,
		);

		$known = array();

		foreach ( $assigned_ids as $term_id ) {
			if ( isset( $index[ $term_id ] ) && ! in_array( $term_id, $known, true ) ) {
				$known[] = $term_id;
			}
		}

		if ( array() === $known ) {
			return array(
				'location' => $location,
				'issues'   => array( $this->issue( IssueCode::LOCATION_MISSING, null ) ),
			);
		}

		$issues = array();

		// Group the assigned terms by their depth in the taxonomy.
		$by_level = array();

		foreach ( $known as $term_id ) {
			$level = $this->level_of( $term_id, $index );

			if ( null === $level ) {
				// Deeper than the three levels the taxonomy is supposed to have.
				$issues[] = $this->issue( IssueCode::LOCATION_AMBIGUOUS, (string) $term_id );

				continue;
			}

			$by_level[ $level ][] = $term_id;
		}

		// A level holding more than one term is a conflict: there is no way to tell which the
		// property is actually in.
		foreach ( self::LEVELS as $level => $member ) {
			$terms = $by_level[ $level ] ?? array();

			if ( count( $terms ) > 1 ) {
				$issues[] = $this->issue( IssueCode::LOCATION_AMBIGUOUS, $member );

				continue;
			}

			if ( array() === $terms ) {
				continue;
			}

			$location[ $member ] = $this->entry( $terms[0], $index );
		}

		$issues = array_merge( $issues, $this->check_lineage( $location, $by_level, $index ) );

		// Fill a level that was not assigned but is implied by a deeper one, and only when that
		// deeper term's own ancestry says so — never by picking a plausible parent.
		$this->fill_from_ancestors( $location, $index );

		$missing = array();

		foreach ( self::LEVELS as $member ) {
			if ( null === $location[ $member ] ) {
				$missing[] = $member;
			}
		}

		if ( array() !== $missing && count( $missing ) < count( self::LEVELS ) ) {
			$issues[] = $this->issue( IssueCode::LOCATION_INCOMPLETE, implode( ',', $missing ) );
		}

		if ( count( $missing ) === count( self::LEVELS ) ) {
			$issues[] = $this->issue( IssueCode::LOCATION_MISSING, null );
		}

		return array(
			'location' => $location,
			'issues'   => $this->unique_issues( $issues ),
		);
	}

	/**
	 * Reports levels whose terms do not belong to the same lineage.
	 *
	 * A distrito whose parent is not the assigned cantón means one of the two is wrong, and the
	 * data does not say which — so both stay as assigned and the conflict is reported. The
	 * conflicting deeper level is cleared, because it is the one whose ancestry is contradicted.
	 *
	 * @param array $location Resolved members, modified in place.
	 * @param array $by_level Assigned term IDs grouped by level.
	 * @param array $index    The whole taxonomy.
	 * @return list<array{code: string, detail: string|null}>
	 *
	 * @phpstan-param array<string, array{id: int, name: string, slug: string}|null> $location
	 * @phpstan-param array<int, list<int>> $by_level
	 * @phpstan-param array<int, array{name: string, slug: string, parent: int}> $index
	 */
	private function check_lineage( array &$location, array $by_level, array $index ): array {
		$issues = array();

		foreach ( array(
			3 => 'district',
			2 => 'canton',
		) as $level => $member ) {
			$parent_member = self::LEVELS[ $level - 1 ];

			if ( null === $location[ $member ] || null === $location[ $parent_member ] ) {
				continue;
			}

			$parent_id = $index[ $location[ $member ]['id'] ]['parent'] ?? 0;

			if ( $parent_id === $location[ $parent_member ]['id'] ) {
				continue;
			}

			$issues[]            = $this->issue( IssueCode::LOCATION_AMBIGUOUS, $member );
			$location[ $member ] = null;
		}

		return $issues;
	}

	/**
	 * Completes a missing level from the ancestry of a deeper, unambiguous one.
	 *
	 * @param array $location Resolved members, modified in place.
	 * @param array $index    The whole taxonomy.
	 * @return void
	 *
	 * @phpstan-param array<string, array{id: int, name: string, slug: string}|null> $location
	 * @phpstan-param array<int, array{name: string, slug: string, parent: int}> $index
	 */
	private function fill_from_ancestors( array &$location, array $index ): void {
		foreach ( array(
			3 => 'district',
			2 => 'canton',
		) as $level => $member ) {
			$parent_member = self::LEVELS[ $level - 1 ];

			if ( null === $location[ $member ] || null !== $location[ $parent_member ] ) {
				continue;
			}

			$parent_id = $index[ $location[ $member ]['id'] ]['parent'] ?? 0;

			if ( $parent_id > 0 && isset( $index[ $parent_id ] ) ) {
				$location[ $parent_member ] = $this->entry( $parent_id, $index );
			}
		}
	}

	/**
	 * Depth of a term, 1 for a root.
	 *
	 * @param int   $term_id Term to measure.
	 * @param array $index   The whole taxonomy.
	 * @return int|null Null when the term sits deeper than the taxonomy's three levels.
	 *
	 * @phpstan-param array<int, array{name: string, slug: string, parent: int}> $index
	 */
	private function level_of( int $term_id, array $index ): ?int {
		$level = 1;
		$id    = $index[ $term_id ]['parent'] ?? 0;

		while ( $id > 0 && isset( $index[ $id ] ) ) {
			++$level;
			$id = $index[ $id ]['parent'];

			if ( $level > count( self::LEVELS ) ) {
				return null;
			}
		}

		return $level;
	}

	/**
	 * Builds one published term entry.
	 *
	 * @param int   $term_id Term ID.
	 * @param array $index   The whole taxonomy.
	 * @return array{id: int, name: string, slug: string}
	 *
	 * @phpstan-param array<int, array{name: string, slug: string, parent: int}> $index
	 */
	private function entry( int $term_id, array $index ): array {
		return array(
			'id'   => $term_id,
			'name' => $index[ $term_id ]['name'],
			'slug' => $index[ $term_id ]['slug'],
		);
	}

	/**
	 * Builds one issue tuple.
	 *
	 * @param string      $code   Issue code.
	 * @param string|null $detail Short machine-oriented detail.
	 * @return array{code: string, detail: string|null}
	 */
	private function issue( string $code, ?string $detail ): array {
		return array(
			'code'   => $code,
			'detail' => $detail,
		);
	}

	/**
	 * Drops repeated issue tuples while keeping their order.
	 *
	 * @param array $issues Collected issues.
	 * @return list<array{code: string, detail: string|null}>
	 *
	 * @phpstan-param list<array{code: string, detail: string|null}> $issues
	 */
	private function unique_issues( array $issues ): array {
		$seen = array();

		foreach ( $issues as $issue ) {
			if ( ! in_array( $issue, $seen, true ) ) {
				$seen[] = $issue;
			}
		}

		return $seen;
	}
}
