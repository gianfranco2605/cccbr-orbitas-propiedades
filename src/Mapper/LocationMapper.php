<?php
/**
 * Maps the location taxonomy to contract terms with their ancestor path.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Mapper;

use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCode;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCollector;
use WP_Term;

/**
 * Builds the `location` array: every assigned term plus its full ancestor path.
 *
 * `ubicacion-propiedad` is the only taxonomy the CPT uses. It is exactly three levels deep, and
 * its seven root terms are the seven provinces of Costa Rica, so `level` means
 * 1 = provincia, 2 = cantón, 3 = distrito (`docs/schema.md` §4).
 *
 * Ancestors are included because a property assigned only a district would otherwise arrive
 * without its cantón or province, and Orbitas cannot reconstruct them.
 *
 * The whole taxonomy — 577 terms — is read **once per request** into an index, so a page of 100
 * properties costs one term query instead of an ancestor walk per term.
 */
final class LocationMapper {

	/**
	 * The only taxonomy the properties CPT uses.
	 */
	public const TAXONOMY = 'ubicacion-propiedad';

	/**
	 * Term index: term ID => name, slug and parent.
	 *
	 * @var array<int, array{name: string, slug: string, parent: int}>|null
	 */
	private ?array $index = null;

	/**
	 * Maps the terms assigned to one property.
	 *
	 * @param int            $post_id Property ID.
	 * @param IssueCollector $issues  Issue sink.
	 * @return list<array{id: int, name: string, slug: string, level: int}>
	 */
	public function map( int $post_id, IssueCollector $issues ): array {
		$assigned = get_the_terms( $post_id, self::TAXONOMY );

		if ( ! is_array( $assigned ) || array() === $assigned ) {
			// 1063 of 3055 properties have no location at all. Reported, never inferred from the
			// address text or the coordinates.
			$issues->add( IssueCode::LOCATION_MISSING, 'location', null );

			return array();
		}

		$index   = $this->index();
		$terms   = array();
		$ordered = array();

		foreach ( $assigned as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			foreach ( $this->with_ancestors( (int) $term->term_id, $index ) as $id => $entry ) {
				$terms[ $id ] = $entry;
			}
		}

		if ( array() === $terms ) {
			$issues->add( IssueCode::LOCATION_MISSING, 'location', null );

			return array();
		}

		foreach ( $terms as $entry ) {
			$ordered[] = $entry;
		}

		usort(
			$ordered,
			static function ( array $a, array $b ): int {
				if ( $a['level'] === $b['level'] ) {
					return strcmp( $a['name'], $b['name'] );
				}

				return $a['level'] <=> $b['level'];
			}
		);

		return $ordered;
	}

	/**
	 * A term and every ancestor above it, keyed by term ID.
	 *
	 * @param int   $term_id Term to start from.
	 * @param array $index   Term index.
	 * @return array<int, array{id: int, name: string, slug: string, level: int}>
	 *
	 * @phpstan-param array<int, array{name: string, slug: string, parent: int}> $index
	 */
	private function with_ancestors( int $term_id, array $index ): array {
		$chain = array();
		$id    = $term_id;

		// A corrupt hierarchy must not loop forever: the taxonomy is three levels deep, so a few
		// more iterations than that is already impossible.
		$guard = 0;

		while ( isset( $index[ $id ] ) && $guard < 20 ) {
			$chain[] = $id;
			$id      = $index[ $id ]['parent'];
			++$guard;

			if ( 0 === $id ) {
				break;
			}
		}

		$depth  = count( $chain );
		$result = array();

		// $chain runs from the assigned term up to the root, so the root is the deepest entry.
		foreach ( $chain as $position => $chain_id ) {
			$result[ $chain_id ] = array(
				'id'    => $chain_id,
				'name'  => $index[ $chain_id ]['name'],
				'slug'  => $index[ $chain_id ]['slug'],
				'level' => $depth - $position,
			);
		}

		return $result;
	}

	/**
	 * The whole taxonomy, read once per request.
	 *
	 * @return array<int, array{name: string, slug: string, parent: int}>
	 */
	private function index(): array {
		if ( null !== $this->index ) {
			return $this->index;
		}

		$this->index = array();

		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( ! is_array( $terms ) ) {
			return $this->index;
		}

		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}

			$this->index[ (int) $term->term_id ] = array(
				'name'   => $term->name,
				'slug'   => $term->slug,
				'parent' => (int) $term->parent,
			);
		}

		return $this->index;
	}
}
