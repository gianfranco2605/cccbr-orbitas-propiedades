<?php
/**
 * Maps the location taxonomy to contract terms with their ancestor path.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Mapper;

use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCollector;
use ElPuas\CCCBR\OrbitasPropiedades\Support\LocationResolver;
use WP_Term;

/**
 * Builds the `location` object: province, cantón and distrito.
 *
 * `ubicacion-propiedad` is the only taxonomy the CPT uses. It is exactly three levels deep, its
 * seven root terms are the seven provinces of Costa Rica, and the property form labels its three
 * selects "Provincia / Cantón / Distrito" — so the taxonomy depth is the administrative level.
 *
 * This class is only the WordPress half: it reads the assigned terms and the taxonomy index, then
 * hands both to {@see LocationResolver}, which decides what the levels are and is unit-tested.
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
	 * Decides which term is the province, the cantón and the distrito.
	 *
	 * @var LocationResolver
	 */
	private LocationResolver $resolver;

	/**
	 * Term index: term ID => name, slug and parent.
	 *
	 * @var array<int, array{name: string, slug: string, parent: int}>|null
	 */
	private ?array $index = null;

	/**
	 * Constructor.
	 *
	 * @param LocationResolver $resolver Level resolver.
	 */
	public function __construct( LocationResolver $resolver ) {
		$this->resolver = $resolver;
	}

	/**
	 * Maps the terms assigned to one property.
	 *
	 * @param int            $post_id Property ID.
	 * @param IssueCollector $issues  Issue sink.
	 * @return array<string, array{id: int, name: string, slug: string}|null>
	 */
	public function map( int $post_id, IssueCollector $issues ): array {
		$assigned = get_the_terms( $post_id, self::TAXONOMY );
		$term_ids = array();

		if ( is_array( $assigned ) ) {
			foreach ( $assigned as $term ) {
				if ( $term instanceof WP_Term ) {
					$term_ids[] = (int) $term->term_id;
				}
			}
		}

		$resolved = $this->resolver->resolve( $term_ids, $this->index() );

		foreach ( $resolved['issues'] as $issue ) {
			$issues->add( $issue['code'], 'location', $issue['detail'] );
		}

		return $resolved['location'];
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
