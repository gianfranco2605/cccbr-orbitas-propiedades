<?php
/**
 * Maps featured image and gallery to the contract.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Mapper;

use ElPuas\CCCBR\OrbitasPropiedades\Support\IdListParser;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCode;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCollector;
use WP_Post;

/**
 * Builds `media.featured` and the ordered `media.gallery`.
 *
 * Every attachment of a whole page is loaded in **one** query by {@see self::prime()} before any
 * property is mapped, so a page of 100 properties with a thousand gallery images costs one
 * attachment query instead of a thousand.
 *
 * Broken references are reported, never hidden: an ID with no attachment post raises
 * `media_attachment_missing` and is left out of the gallery, and a gallery token that is not an ID
 * raises `media_gallery_unparsable`.
 */
final class MediaMapper {

	/**
	 * Meta keys that can hold attachment IDs for a property.
	 *
	 * @var list<string>
	 */
	private const ID_KEYS = array( '_thumbnail_id', 'imagenes', 'imagen-principal' );

	/**
	 * Attachment ID parser.
	 *
	 * @var IdListParser
	 */
	private IdListParser $ids;

	/**
	 * Whether this environment can answer "does the file exist?".
	 *
	 * @var bool
	 */
	private bool $check_files;

	/**
	 * Attachments of the current page, keyed by ID.
	 *
	 * @var array<int, WP_Post>
	 */
	private array $attachments = array();

	/**
	 * Constructor.
	 *
	 * @param IdListParser $ids         Attachment ID parser.
	 * @param bool         $check_files Whether to verify that the attachment's file exists on disk.
	 *                                  False on an environment whose uploads are not local — such
	 *                                  as the development replica, where every uploads request is
	 *                                  redirected to production — because there the plugin cannot
	 *                                  tell a deleted file from an absent media library, and
	 *                                  claiming either would be a lie.
	 */
	public function __construct( IdListParser $ids, bool $check_files ) {
		$this->ids         = $ids;
		$this->check_files = $check_files;
	}

	/**
	 * Loads every attachment referenced by a page, in one query.
	 *
	 * @param array $meta_by_id Raw meta of each property on the page, keyed by property ID.
	 * @return void
	 *
	 * @phpstan-param array<int, array<string, mixed>> $meta_by_id
	 */
	public function prime( array $meta_by_id ): void {
		$this->attachments = array();

		$wanted = array();

		foreach ( $meta_by_id as $meta ) {
			foreach ( self::ID_KEYS as $key ) {
				if ( ! isset( $meta[ $key ] ) || ! is_array( $meta[ $key ] ) ) {
					continue;
				}

				$value = $meta[ $key ][0] ?? null;
				$value = is_string( $value ) ? maybe_unserialize( $value ) : $value;

				foreach ( $this->ids->parse( $value )['ids'] as $id ) {
					$wanted[ $id ] = true;
				}
			}
		}

		if ( array() === $wanted ) {
			return;
		}

		$found = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post__in'               => array_keys( $wanted ),
				'posts_per_page'         => count( $wanted ),
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $found as $attachment ) {
			if ( $attachment instanceof WP_Post ) {
				$this->attachments[ $attachment->ID ] = $attachment;
			}
		}
	}

	/**
	 * Maps the media of one property.
	 *
	 * @param array          $meta   Raw meta, already unserialized by the caller's accessor.
	 * @param IssueCollector $issues Issue sink.
	 * @return array{featured: array<string, mixed>|null, gallery: list<array<string, mixed>>}
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	public function map( array $meta, IssueCollector $issues ): array {
		return array(
			'featured' => $this->map_featured( $meta, $issues ),
			'gallery'  => $this->map_gallery( $meta, $issues ),
		);
	}

	/**
	 * Resolves the featured image.
	 *
	 * `_thumbnail_id` is the real featured image (2994 usable IDs). `imagen-principal` is only a
	 * fallback: it exists on 5 properties and uses JetEngine's `both` format.
	 *
	 * @param array          $meta   Raw meta.
	 * @param IssueCollector $issues Issue sink.
	 * @return array<string, mixed>|null
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function map_featured( array $meta, IssueCollector $issues ): ?array {
		foreach ( array( '_thumbnail_id', 'imagen-principal' ) as $key ) {
			$id = $this->ids->first_id( $this->raw( $meta, $key ) );

			if ( null === $id ) {
				continue;
			}

			$attachment = $this->attachment( $id, 'media.featured', $issues );

			if ( null !== $attachment ) {
				return $attachment;
			}
		}

		$issues->add( IssueCode::MEDIA_FEATURED_MISSING, 'media.featured', null );

		return null;
	}

	/**
	 * Resolves the gallery, in stored order.
	 *
	 * The featured image is **not** removed from the gallery: the source lists it there, and
	 * silently dropping it would change the order Orbitas imports.
	 *
	 * @param array          $meta   Raw meta.
	 * @param IssueCollector $issues Issue sink.
	 * @return list<array<string, mixed>>
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function map_gallery( array $meta, IssueCollector $issues ): array {
		$parsed  = $this->ids->parse( $this->raw( $meta, 'imagenes' ) );
		$gallery = array();

		foreach ( $parsed['invalid'] as $token ) {
			$issues->add( IssueCode::MEDIA_GALLERY_UNPARSABLE, 'media.gallery', $token );
		}

		foreach ( $parsed['ids'] as $id ) {
			$attachment = $this->attachment( $id, 'media.gallery', $issues );

			if ( null !== $attachment ) {
				$gallery[] = $attachment;
			}
		}

		if ( array() === $gallery ) {
			$issues->add( IssueCode::MEDIA_GALLERY_EMPTY, 'media.gallery', null );
		}

		return $gallery;
	}

	/**
	 * Builds one attachment object from the primed index.
	 *
	 * @param int            $id    Attachment ID.
	 * @param string         $field Contract field, for issue reports.
	 * @param IssueCollector $issues Issue sink.
	 * @return array<string, mixed>|null Null when the attachment does not exist.
	 */
	private function attachment( int $id, string $field, IssueCollector $issues ): ?array {
		if ( ! isset( $this->attachments[ $id ] ) ) {
			$issues->add( IssueCode::MEDIA_ATTACHMENT_MISSING, $field, (string) $id );

			return null;
		}

		$attachment = $this->attachments[ $id ];
		$metadata   = wp_get_attachment_metadata( $id );
		$metadata   = is_array( $metadata ) ? $metadata : array();

		$url = wp_get_attachment_url( $id );
		$url = is_string( $url ) && '' !== $url ? $url : null;

		$alt = get_post_meta( $id, '_wp_attachment_image_alt', true );

		$this->check_file( $id, $field, $issues );

		return array(
			'id'        => $id,
			'url'       => $url,
			'mime_type' => '' !== $attachment->post_mime_type ? $attachment->post_mime_type : null,
			'width'     => isset( $metadata['width'] ) ? (int) $metadata['width'] : null,
			'height'    => isset( $metadata['height'] ) ? (int) $metadata['height'] : null,
			'alt'       => is_string( $alt ) ? $alt : '',
			'sizes'     => (object) $this->sizes( $metadata, $url ),
		);
	}

	/**
	 * Builds the derivative map from the attachment metadata.
	 *
	 * URLs are derived from the full-size URL and the per-size file name, which is exactly how
	 * WordPress itself composes them — no extra query per size.
	 *
	 * @param array       $metadata Attachment metadata.
	 * @param string|null $url      Full-size URL.
	 * @return array<string, array{url: string, width: int, height: int}>
	 *
	 * @phpstan-param array<string, mixed> $metadata
	 */
	private function sizes( array $metadata, ?string $url ): array {
		if ( null === $url || ! isset( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return array();
		}

		$base  = substr( $url, 0, (int) strrpos( $url, '/' ) + 1 );
		$sizes = array();

		foreach ( $metadata['sizes'] as $name => $size ) {
			if ( ! is_array( $size ) || ! isset( $size['file'] ) || ! is_string( $size['file'] ) ) {
				continue;
			}

			$sizes[ (string) $name ] = array(
				'url'    => $base . $size['file'],
				'width'  => isset( $size['width'] ) ? (int) $size['width'] : 0,
				'height' => isset( $size['height'] ) ? (int) $size['height'] : 0,
			);
		}

		return $sizes;
	}

	/**
	 * Reports an attachment whose file is missing on disk.
	 *
	 * Skipped entirely when the environment cannot answer the question; see the constructor.
	 *
	 * @param int            $id     Attachment ID.
	 * @param string         $field  Contract field, for issue reports.
	 * @param IssueCollector $issues Issue sink.
	 * @return void
	 */
	private function check_file( int $id, string $field, IssueCollector $issues ): void {
		if ( ! $this->check_files ) {
			return;
		}

		$path = get_attached_file( $id );

		if ( ! is_string( $path ) || '' === $path || ! file_exists( $path ) ) {
			$issues->add( IssueCode::MEDIA_FILE_MISSING, $field, (string) $id );
		}
	}

	/**
	 * First stored value of a meta key, unserialized.
	 *
	 * @param array  $meta Raw meta.
	 * @param string $key  Meta key.
	 * @return mixed
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function raw( array $meta, string $key ) {
		if ( ! isset( $meta[ $key ] ) || ! is_array( $meta[ $key ] ) ) {
			return null;
		}

		$value = $meta[ $key ][0] ?? null;

		return is_string( $value ) ? maybe_unserialize( $value ) : $value;
	}
}
