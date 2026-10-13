<?php
/**
 * REST controller for the properties collection.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Rest;

use ElPuas\CCCBR\OrbitasPropiedades\Auth\Permissions;
use ElPuas\CCCBR\OrbitasPropiedades\Mapper\PropertyMapper;
use ElPuas\CCCBR\OrbitasPropiedades\Pagination\Cursor;
use ElPuas\CCCBR\OrbitasPropiedades\Repository\PropertyRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Registers and serves `GET /cccbr-orbitas/v1/propiedades`.
 *
 * The class deliberately does not extend `WP_REST_Controller`: that base class declares its
 * callbacks with untyped parameters, and PHP forbids narrowing them in a subclass, which would
 * cost the strict typing required by the project standards. The endpoint also returns a custom
 * envelope rather than a plain item list, so little of the base class would be used. Everything
 * the base class would have provided — a real permission callback, argument validation and a
 * published schema — is implemented explicitly here.
 */
final class PropertiesController {

	/**
	 * REST namespace.
	 */
	public const REST_NAMESPACE = 'cccbr-orbitas/v1';

	/**
	 * Route base.
	 */
	public const REST_BASE = 'propiedades';

	/**
	 * Authorization service.
	 *
	 * @var Permissions
	 */
	private Permissions $permissions;

	/**
	 * Response schema provider.
	 *
	 * @var PropertySchema
	 */
	private PropertySchema $schema;

	/**
	 * Property data source.
	 *
	 * @var PropertyRepository
	 */
	private PropertyRepository $repository;

	/**
	 * Property mapper.
	 *
	 * @var PropertyMapper
	 */
	private PropertyMapper $mapper;

	/**
	 * Constructor.
	 *
	 * @param Permissions        $permissions Authorization service.
	 * @param PropertySchema     $schema      Response schema provider.
	 * @param PropertyRepository $repository  Property data source.
	 * @param PropertyMapper     $mapper      Property mapper.
	 */
	public function __construct(
		Permissions $permissions,
		PropertySchema $schema,
		PropertyRepository $repository,
		PropertyMapper $mapper
	) {
		$this->permissions = $permissions;
		$this->schema      = $schema;
		$this->repository  = $repository;
		$this->mapper      = $mapper;
	}

	/**
	 * Registers the route. Hooked to `rest_api_init`.
	 *
	 * Only a readable method is registered, so the endpoint cannot be used to write anything.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/' . self::REST_BASE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this->permissions, 'check' ),
					'args'                => $this->collection_params(),
				),
				'schema' => array( $this->schema, 'collection' ),
			)
		);
	}

	/**
	 * Serves the collection.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public function get_items( WP_REST_Request $request ) {
		$args = QueryArgs::from_request( $request );

		if ( $args instanceof WP_Error ) {
			return $args;
		}

		$cursor    = new Cursor( $args->per_page(), $this->repository->find_page_ids( $args ) );
		$aggregate = $this->repository->aggregate( $args );

		$posts      = $this->repository->find_posts( $cursor->ids() );
		$meta_by_id = array();
		$loaded     = array();

		foreach ( $posts as $post ) {
			$meta = get_post_meta( $post->ID );

			$meta_by_id[ $post->ID ] = is_array( $meta ) ? $meta : array();
			$loaded[]                = $post->ID;
		}

		// Load everything the whole page references in bulk before mapping anything, so no
		// property triggers a query of its own.
		$this->mapper->prime( $posts, $meta_by_id );

		$items = array();

		foreach ( $posts as $post ) {
			$items[] = $this->mapper->map( $post, $meta_by_id[ $post->ID ] );
		}

		$response = new WP_REST_Response(
			array(
				'data'         => $items,
				'pagination'   => array(
					'per_page'      => $args->per_page(),
					'after_id'      => $args->after_id(),
					'next_after_id' => $cursor->next_after_id(),
					'has_more'      => $cursor->has_more(),
					'max_id'        => $aggregate['max_id'],
					'skipped_ids'   => $cursor->skipped_ids( $loaded ),
				),
				'totals'       => array(
					'total'     => $aggregate['total'],
					// Cast to an object so an empty map serializes as `{}` and not as `[]`.
					'by_status' => (object) $aggregate['by_status'],
				),
				'generated_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			)
		);

		// The payload carries personal data of the property creator: it must not be cached by any
		// intermediary.
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	/**
	 * Declares the supported query arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function collection_params(): array {
		return array(
			'per_page' => array(
				'description'       => __( 'Page size. Maximum 100; a larger value is rejected, never clamped.', 'cccbr-orbitas-propiedades' ),
				'type'              => 'integer',
				'default'           => QueryArgs::PER_PAGE_DEFAULT,
				'minimum'           => 1,
				'maximum'           => QueryArgs::PER_PAGE_MAX,
				'required'          => false,
				'validate_callback' => array( QueryArgs::class, 'validate_per_page' ),
				'sanitize_callback' => 'absint',
			),
			'after_id' => array(
				'description'       => __( 'Keyset cursor: only properties with a larger ID are returned.', 'cccbr-orbitas-propiedades' ),
				'type'              => 'integer',
				'default'           => 0,
				'minimum'           => 0,
				'required'          => false,
				'validate_callback' => array( QueryArgs::class, 'validate_after_id' ),
				'sanitize_callback' => 'absint',
			),
			'status'   => array(
				'description'       => __( 'Optional status filter. Accepts a comma-separated list or a repeated parameter. Defaults to every status.', 'cccbr-orbitas-propiedades' ),
				'type'              => array( 'string', 'array' ),
				'items'             => array(
					'type' => 'string',
					'enum' => QueryArgs::ALLOWED_STATUSES,
				),
				'required'          => false,
				'validate_callback' => array( QueryArgs::class, 'validate_status' ),
				'sanitize_callback' => array( $this, 'sanitize_status' ),
			),
		);
	}

	/**
	 * Sanitizes the status parameter without changing its shape.
	 *
	 * Validation has already rejected anything unexpected; this only strips stray whitespace and
	 * control characters so the value is safe to log and compare.
	 *
	 * @param mixed $value Raw parameter value.
	 * @return string|list<string>
	 */
	public function sanitize_status( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $item ) {
				$sanitized[] = sanitize_text_field( is_string( $item ) ? $item : '' );
			}

			return $sanitized;
		}

		return sanitize_text_field( is_string( $value ) ? $value : '' );
	}
}
