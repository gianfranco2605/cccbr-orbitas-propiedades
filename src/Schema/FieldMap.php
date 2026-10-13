<?php
/**
 * Field definitions read from the live JetEngine configuration.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Schema;

use ElPuas\CCCBR\OrbitasPropiedades\Repository\PropertyRepository;
use wpdb;

/**
 * Supplies the option set (`key => label`) of each select and checkbox field.
 *
 * The sets are read from the real configuration — the `jet_engine_meta_boxes` option and, for the
 * fields that use one, the JetEngine glossary rows — rather than copied into code, so a label
 * CCCBR edits in JetEngine shows up in the API without a release.
 *
 * Everything is memoized per request: a page of 100 properties resolves the option sets once.
 *
 * When the configuration cannot be read (JetEngine removed, option deleted), the option set is
 * empty and every stored token is returned raw with an `option_value_unmapped` issue. The export
 * degrades, it does not lie.
 */
final class FieldMap {

	/**
	 * Option holding the JetEngine meta boxes.
	 */
	private const META_BOXES_OPTION = 'jet_engine_meta_boxes';

	/**
	 * JetEngine table holding post types and glossaries, without the prefix.
	 */
	private const JET_TABLE = 'jet_post_types';

	/**
	 * WordPress database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Resolved option sets, keyed by field name.
	 *
	 * @var array<string, array<string, string>>|null
	 */
	private ?array $options = null;

	/**
	 * Memoized glossary option sets, keyed by glossary ID.
	 *
	 * @var array<int, array<string, string>>
	 */
	private array $glossaries = array();

	/**
	 * Whether the JetEngine table exists. Null until checked.
	 *
	 * @var bool|null
	 */
	private ?bool $jet_table_exists = null;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database handle.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Option set of one field.
	 *
	 * @param string $field JetEngine field name, for example `tipo-de-propiedad`.
	 * @return array<string, string> `key => label`, empty when the field has no option set.
	 */
	public function options_for( string $field ): array {
		if ( null === $this->options ) {
			$this->options = $this->load();
		}

		return $this->options[ $field ] ?? array();
	}

	/**
	 * Reads every option set of the properties meta box.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function load(): array {
		$boxes = get_option( self::META_BOXES_OPTION );

		if ( ! is_array( $boxes ) ) {
			return array();
		}

		$options = array();

		foreach ( $boxes as $box ) {
			if ( ! is_array( $box ) || ! $this->box_targets_properties( $box ) ) {
				continue;
			}

			$fields = isset( $box['meta_fields'] ) && is_array( $box['meta_fields'] ) ? $box['meta_fields'] : array();

			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['name'] ) || ! is_string( $field['name'] ) ) {
					continue;
				}

				$options[ $field['name'] ] = $this->options_of_field( $field );
			}
		}

		return $options;
	}

	/**
	 * Whether a meta box applies to the properties post type.
	 *
	 * @param array $box Meta box definition.
	 * @return bool
	 *
	 * @phpstan-param array<string, mixed> $box
	 */
	private function box_targets_properties( array $box ): bool {
		$args = isset( $box['args'] ) && is_array( $box['args'] ) ? $box['args'] : array();

		if ( ( $args['object_type'] ?? '' ) !== 'post' ) {
			return false;
		}

		$allowed = $args['allowed_post_type'] ?? array();

		if ( ! is_array( $allowed ) ) {
			return false;
		}

		return in_array( PropertyRepository::POST_TYPE, $allowed, true );
	}

	/**
	 * Resolves the option set of one field definition.
	 *
	 * A field either carries its options inline (`options_source: manual`) or points at a
	 * glossary. `amenidades` is the awkward case: it has a `glossary_id` but
	 * `options_from_glossary` is falsy and its inline options are empty, so JetEngine itself
	 * renders no choices. The glossary is therefore **not** applied — doing so would invent a
	 * mapping the site does not use (`docs/schema.md` §2.1).
	 *
	 * @param array $field Field definition.
	 * @return array<string, string>
	 *
	 * @phpstan-param array<string, mixed> $field
	 */
	private function options_of_field( array $field ): array {
		$from_glossary = ! empty( $field['options_from_glossary'] );
		$glossary_id   = isset( $field['glossary_id'] ) ? (int) $field['glossary_id'] : 0;

		if ( $from_glossary && $glossary_id > 0 ) {
			return $this->glossary( $glossary_id );
		}

		$inline = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
		$result = array();

		foreach ( $inline as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			// In a meta box definition the stored key is `key` and the human label is `value`.
			$key   = isset( $option['key'] ) ? (string) $option['key'] : '';
			$label = isset( $option['value'] ) ? (string) $option['value'] : '';

			if ( '' === $key ) {
				continue;
			}

			$result[ $key ] = '' === $label ? $key : $label;
		}

		return $result;
	}

	/**
	 * Reads one glossary's entries.
	 *
	 * @param int $glossary_id Glossary row ID in the JetEngine table.
	 * @return array<string, string>
	 */
	private function glossary( int $glossary_id ): array {
		if ( isset( $this->glossaries[ $glossary_id ] ) ) {
			return $this->glossaries[ $glossary_id ];
		}

		$this->glossaries[ $glossary_id ] = array();

		if ( ! $this->jet_table_exists() ) {
			return $this->glossaries[ $glossary_id ];
		}

		$table = $this->wpdb->prefix . self::JET_TABLE;

		// The table name is bound with the `%i` identifier placeholder (WordPress 6.2+), so the
		// statement stays a literal string and nothing is interpolated into SQL.
		$sql = $this->wpdb->prepare(
			'SELECT meta_fields FROM %i WHERE id = %d AND status = %s',
			$table,
			$glossary_id,
			'glossary'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- JetEngine exposes no API for glossary rows; the statement above is prepared and the result is memoized for the request.
		$raw = is_string( $sql ) ? $this->wpdb->get_var( $sql ) : null;

		if ( ! is_string( $raw ) || '' === $raw ) {
			return $this->glossaries[ $glossary_id ];
		}

		$entries = maybe_unserialize( $raw );

		if ( ! is_array( $entries ) ) {
			return $this->glossaries[ $glossary_id ];
		}

		$result = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			// In a glossary the stored key is `value` and the human label is `label`.
			$key   = isset( $entry['value'] ) ? (string) $entry['value'] : '';
			$label = isset( $entry['label'] ) ? (string) $entry['label'] : '';

			if ( '' === $key ) {
				continue;
			}

			$result[ $key ] = '' === $label ? $key : $label;
		}

		$this->glossaries[ $glossary_id ] = $result;

		return $result;
	}

	/**
	 * Whether the JetEngine table is present.
	 *
	 * Checked once per request so a deactivated JetEngine produces an empty option set instead of
	 * a database error leaking into the JSON response.
	 *
	 * @return bool
	 */
	private function jet_table_exists(): bool {
		if ( null !== $this->jet_table_exists ) {
			return $this->jet_table_exists;
		}

		$table = $this->wpdb->prefix . self::JET_TABLE;

		$sql = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence probe for a third-party table, prepared above and run once per request.
		$found = is_string( $sql ) ? $this->wpdb->get_var( $sql ) : null;

		$this->jet_table_exists = ( $found === $table );

		return $this->jet_table_exists;
	}
}
