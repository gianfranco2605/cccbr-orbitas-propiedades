<?php
/**
 * Maps a stored property to the published contract.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Mapper;

use ElPuas\CCCBR\OrbitasPropiedades\Schema\FieldMap;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCode;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCollector;
use ElPuas\CCCBR\OrbitasPropiedades\Support\NumberParser;
use ElPuas\CCCBR\OrbitasPropiedades\Support\OptionNormalizer;
use WP_Post;

/**
 * Builds the property object documented in `docs/contract.md` §4.
 *
 * Reading is strictly allow-listed: the mapper asks for the keys it knows and never iterates the
 * meta array, so a new plugin meta key cannot leak into the response. Nothing is inferred: a value
 * that is missing, ambiguous or broken produces `null` plus an `issues` entry.
 */
final class PropertyMapper {

	/**
	 * Numeric contract fields and the source key each reads.
	 *
	 * @var array<string, string>
	 */
	private const FEATURE_FIELDS = array(
		'bedrooms'   => 'habitaciones',
		'bathrooms'  => 'banos',
		'garage'     => 'garage',
		'floors'     => 'plantas',
		'land_area'  => 'terreno',
		'built_area' => 'construccion',
		'year_built' => 'ano-de-construccion',
	);

	/**
	 * Classification contract fields and the source key each reads.
	 *
	 * @var array<string, string>
	 */
	private const OPTION_FIELDS = array(
		'operation'     => 'estatus-propiedad',
		'property_type' => 'tipo-de-propiedad',
		'land_use'      => 'uso-de-suelo',
		'exclusivity'   => 'exclusividad',
		'sold'          => 'propiedad_vendida',
		'share'         => 'compartir_propiedad',
		'amenities'     => 'amenidades',
	);

	/**
	 * Option sets of the real JetEngine configuration.
	 *
	 * @var FieldMap
	 */
	private FieldMap $fields;

	/**
	 * Tolerant select/checkbox normalizer.
	 *
	 * @var OptionNormalizer
	 */
	private OptionNormalizer $options;

	/**
	 * Conservative number parser.
	 *
	 * @var NumberParser
	 */
	private NumberParser $numbers;

	/**
	 * Location taxonomy mapper.
	 *
	 * @var LocationMapper
	 */
	private LocationMapper $locations;

	/**
	 * Geocode cache mapper.
	 *
	 * @var CoordinatesMapper
	 */
	private CoordinatesMapper $coordinates;

	/**
	 * Media mapper.
	 *
	 * @var MediaMapper
	 */
	private MediaMapper $media;

	/**
	 * People and relations mapper.
	 *
	 * @var PeopleMapper
	 */
	private PeopleMapper $people;

	/**
	 * Constructor.
	 *
	 * @param FieldMap          $fields      Option sets of the real JetEngine configuration.
	 * @param OptionNormalizer  $options     Tolerant select/checkbox normalizer.
	 * @param NumberParser      $numbers     Conservative number parser.
	 * @param LocationMapper    $locations   Location taxonomy mapper.
	 * @param CoordinatesMapper $coordinates Geocode cache mapper.
	 * @param MediaMapper       $media       Media mapper.
	 * @param PeopleMapper      $people      People and relations mapper.
	 */
	public function __construct(
		FieldMap $fields,
		OptionNormalizer $options,
		NumberParser $numbers,
		LocationMapper $locations,
		CoordinatesMapper $coordinates,
		MediaMapper $media,
		PeopleMapper $people
	) {
		$this->fields      = $fields;
		$this->options     = $options;
		$this->numbers     = $numbers;
		$this->locations   = $locations;
		$this->coordinates = $coordinates;
		$this->media       = $media;
		$this->people      = $people;
	}

	/**
	 * Loads everything a whole page needs, in bulk, before any property is mapped.
	 *
	 * This is what keeps the endpoint free of N+1 queries: the attachments, relation rows and
	 * users of all 100 properties are fetched in a handful of queries here rather than one query
	 * per image, per relation and per user during mapping.
	 *
	 * @param array $posts      Posts on the page.
	 * @param array $meta_by_id Raw meta of each property on the page, keyed by property ID.
	 * @return void
	 *
	 * @phpstan-param list<WP_Post> $posts
	 * @phpstan-param array<int, array<string, mixed>> $meta_by_id
	 */
	public function prime( array $posts, array $meta_by_id ): void {
		$this->media->prime( $meta_by_id );
		$this->people->prime( $posts, $meta_by_id );
	}

	/**
	 * Maps one property.
	 *
	 * @param WP_Post $post Property post, from a primed cache.
	 * @param array   $meta Raw meta as `get_post_meta( $id )` returns it.
	 * @return array<string, mixed>
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	public function map( WP_Post $post, array $meta ): array {
		$issues = new IssueCollector();

		$classification = array();

		foreach ( self::OPTION_FIELDS as $contract_field => $source_key ) {
			$classification[ $contract_field ] = $this->map_options( $contract_field, $source_key, $meta, $issues );
		}

		$price    = $this->map_price( $meta, $classification['operation'], $issues );
		$features = $this->map_features( $meta, $issues );
		$map_raw  = $this->string( $meta, 'mapa' );
		$prefix   = $this->coordinates->meta_prefix();
		$people   = $this->people->map( $post, $meta, $issues );

		$this->check_title( $post, $meta, $issues );

		$item = array(
			'id'                    => $post->ID,
			'title'                 => $post->post_title,
			'slug'                  => $post->post_name,
			'historical_slug'       => $this->nullable_string( $meta, '_wp_desired_post_slug' ),
			'url'                   => $this->permalink( $post ),
			'status'                => $post->post_status,
			'status_before_trash'   => $this->nullable_string( $meta, '_wp_trash_meta_status' ),
			'trashed_at_gmt'        => $this->timestamp_to_iso( $this->first( $meta, '_wp_trash_meta_time' ) ),
			'created_at_gmt'        => $this->mysql_to_iso( $post->post_date_gmt ),
			'modified_at_gmt'       => $this->mysql_to_iso( $post->post_modified_gmt ),
			'content'               => $post->post_content,
			'description_en'        => $this->string( $meta, 'descripcion-en-ingles' ),

			'expiration'            => $this->map_expiration( $meta, $issues ),
			'operation'             => $classification['operation'],
			'property_type'         => $classification['property_type'],
			'land_use'              => $classification['land_use'],
			'exclusivity'           => $classification['exclusivity'],
			'sold'                  => $classification['sold'],
			'share'                 => $classification['share'],
			'amenities'             => $classification['amenities'],
			'price'                 => $price,
			'features'              => $features,
			'registry_number'       => $this->string( $meta, 'numero-de-finca' ),
			'purchase_option_terms' => $this->string( $meta, 'opciondecompracondiciones' ),
			'video_url'             => $this->string( $meta, 'video_propiedad' ),
			'map_raw'               => $map_raw,
			'location'              => $this->locations->map( $post->ID, $issues ),
			'coordinates'           => $this->coordinates->map(
				$this->first( $meta, $prefix . '_lat' ),
				$this->first( $meta, $prefix . '_lng' ),
				$this->first( $meta, $prefix . '_hash' ),
				$map_raw,
				$issues
			),

			'media'                 => $this->media->map( $meta, $issues ),
			'private_documents'     => array(
				'has_contract_photo' => $this->has_value( $meta, 'foto-del-contrato' ),
				'has_attachments'    => $this->has_value( $meta, 'documentos-anexos' ),
			),

			'creator'               => $people['creator'],
			'responsible'           => $people['responsible'],
			'company'               => $people['company'],
			'associate_id'          => $people['associate_id'],
			'marketplace_id'        => $people['marketplace_id'],
			'leads_count'           => $people['leads_count'],
			'origin'                => $this->map_origin( $meta, $issues ),

			'issues'                => $issues->all(),
		);

		return $item;
	}

	/**
	 * Maps one classification field to `{ key, label }` entries.
	 *
	 * @param string         $contract_field Contract field name, used in issue reports.
	 * @param string         $source_key     JetEngine meta key.
	 * @param array          $meta           Raw meta.
	 * @param IssueCollector $issues         Issue sink.
	 * @return list<array{key: string, label: string|null}>
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function map_options( string $contract_field, string $source_key, array $meta, IssueCollector $issues ): array {
		$entries = $this->options->normalize(
			$this->first( $meta, $source_key ),
			$this->fields->options_for( $source_key )
		);

		$mapped = array();

		foreach ( $entries as $entry ) {
			if ( null === $entry['label'] ) {
				$issues->add( IssueCode::OPTION_VALUE_UNMAPPED, $contract_field, $entry['key'] );
			} elseif ( false === $entry['exact'] ) {
				$issues->add( IssueCode::OPTION_VALUE_NORMALIZED, $contract_field, $entry['key'] );
			}

			$mapped[] = array(
				'key'   => $entry['key'],
				'label' => $entry['label'],
			);
		}

		return $mapped;
	}

	/**
	 * Maps the three prices and the currency.
	 *
	 * @param array          $meta      Raw meta.
	 * @param array          $operation Already-mapped operation entries.
	 * @param IssueCollector $issues    Issue sink.
	 * @return array<string, mixed>
	 *
	 * @phpstan-param array<string, mixed> $meta
	 * @phpstan-param list<array{key: string, label: string|null}> $operation
	 */
	private function map_price( array $meta, array $operation, IssueCollector $issues ): array {
		$sale       = $this->map_number( 'price.sale', $meta, 'precio-de-venta', $issues );
		$rent       = $this->map_number( 'price.rent', $meta, 'precio-de-alquiler', $issues );
		$final_sale = $this->map_number( 'price.final_sale', $meta, 'precio-final-de-venta', $issues );

		$currency = $this->map_currency( $meta, $issues );

		$has_amount = null !== $sale['value'] || null !== $rent['value'] || null !== $final_sale['value'];

		if ( $has_amount && null === $currency ) {
			$issues->add( IssueCode::PRICE_CURRENCY_MISSING, 'price.currency', null );
		}

		// A property offered for sale with no sale price is worth reporting: Orbitas cannot list it.
		if ( '' === $sale['raw'] && $this->operation_includes_sale( $operation ) ) {
			$issues->add( IssueCode::NUMBER_MISSING, 'price.sale', null );
		}

		return array(
			'sale'       => $this->strip_state( $sale ),
			'rent'       => $this->strip_state( $rent ),
			'final_sale' => $this->strip_state( $final_sale ),
			'currency'   => $currency,
		);
	}

	/**
	 * Maps the currency, resolved through the real `moneda` option set.
	 *
	 * The currency is never inferred from the amount, the location or anything else: only 9 of
	 * 3055 properties record one, and a guess would be indistinguishable from data.
	 *
	 * @param array          $meta   Raw meta.
	 * @param IssueCollector $issues Issue sink.
	 * @return string|null
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function map_currency( array $meta, IssueCollector $issues ): ?string {
		$entries = $this->options->normalize(
			$this->first( $meta, 'moneda' ),
			$this->fields->options_for( 'moneda' )
		);

		if ( array() === $entries ) {
			return null;
		}

		$entry = $entries[0];

		if ( null === $entry['label'] ) {
			$issues->add( IssueCode::OPTION_VALUE_UNMAPPED, 'price.currency', $entry['key'] );

			return $entry['key'];
		}

		return $entry['label'];
	}

	/**
	 * Maps the seven feature fields.
	 *
	 * @param array          $meta   Raw meta.
	 * @param IssueCollector $issues Issue sink.
	 * @return array<string, array{raw: string, value: int|float|null}>
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function map_features( array $meta, IssueCollector $issues ): array {
		$features = array();

		foreach ( self::FEATURE_FIELDS as $contract_field => $source_key ) {
			$features[ $contract_field ] = $this->strip_state(
				$this->map_number( 'features.' . $contract_field, $meta, $source_key, $issues )
			);
		}

		return $features;
	}

	/**
	 * Parses one numeric field and reports why it could not be parsed.
	 *
	 * @param string         $contract_field Contract field name, used in issue reports.
	 * @param array          $meta           Raw meta.
	 * @param string         $source_key     JetEngine meta key.
	 * @param IssueCollector $issues         Issue sink.
	 * @return array{raw: string, value: int|float|null, state: string}
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function map_number( string $contract_field, array $meta, string $source_key, IssueCollector $issues ): array {
		$parsed = $this->numbers->parse( $this->first( $meta, $source_key ) );

		if ( NumberParser::STATE_AMBIGUOUS === $parsed['state'] ) {
			$issues->add( IssueCode::NUMBER_AMBIGUOUS, $contract_field, $parsed['raw'] );
		} elseif ( NumberParser::STATE_UNPARSABLE === $parsed['state'] ) {
			$issues->add( IssueCode::NUMBER_UNPARSABLE, $contract_field, $parsed['raw'] );
		}

		return $parsed;
	}

	/**
	 * Maps the JetEngine post-expiration module's fields.
	 *
	 * 541 properties carry an expiration, all with the action `draft`. A property without one is
	 * simply `null`: that is not a data-quality issue.
	 *
	 * @param array          $meta   Raw meta.
	 * @param IssueCollector $issues Issue sink.
	 * @return array{at_gmt: string, action: string}|null
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function map_expiration( array $meta, IssueCollector $issues ): ?array {
		$period = $this->first( $meta, '_jet_pep_period' );

		if ( null === $period || '' === $period ) {
			return null;
		}

		$at_gmt = $this->timestamp_to_iso( $period );

		if ( null === $at_gmt ) {
			$issues->add( IssueCode::EXPIRATION_INVALID, 'expiration', null );

			return null;
		}

		return array(
			'at_gmt' => $at_gmt,
			'action' => $this->string( $meta, '_jet_pep_action' ),
		);
	}

	/**
	 * Maps the origin of the property, for audit.
	 *
	 * 2515 properties carry `_wpuf_form_id` (2512 of them form 9536). The other 540 carry **no
	 * origin marker at all**: there is no JetFormBuilder post meta on this CPT and
	 * `wp_jet_fb_records` is empty, so the source is reported as `unknown` rather than guessed
	 * from the creation date (`docs/schema.md` §9).
	 *
	 * @param array          $meta   Raw meta.
	 * @param IssueCollector $issues Issue sink.
	 * @return array{source: string, wpuf_form_id: int|null}
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function map_origin( array $meta, IssueCollector $issues ): array {
		$form_id = $this->string( $meta, '_wpuf_form_id' );

		if ( 1 === preg_match( '/^\d+$/', $form_id ) && (int) $form_id > 0 ) {
			return array(
				'source'       => 'wp_user_frontend',
				'wpuf_form_id' => (int) $form_id,
			);
		}

		$issues->add( IssueCode::ORIGIN_UNKNOWN, 'origin', null );

		return array(
			'source'       => 'unknown',
			'wpuf_form_id' => null,
		);
	}

	/**
	 * Reports a stored property name that disagrees with the post title.
	 *
	 * @param WP_Post        $post   Property post.
	 * @param array          $meta   Raw meta.
	 * @param IssueCollector $issues Issue sink.
	 * @return void
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function check_title( WP_Post $post, array $meta, IssueCollector $issues ): void {
		$name = $this->string( $meta, 'nombre-de-propiedad' );

		if ( '' !== $name && $name !== $post->post_title ) {
			$issues->add( IssueCode::TITLE_NAME_MISMATCH, 'title', null );
		}
	}

	/**
	 * Whether the mapped operation includes a sale.
	 *
	 * @param array $operation Mapped operation entries.
	 * @return bool
	 *
	 * @phpstan-param list<array{key: string, label: string|null}> $operation
	 */
	private function operation_includes_sale( array $operation ): bool {
		foreach ( $operation as $entry ) {
			if ( 'Venta' === $entry['key'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Drops the internal parse state, leaving the published `{ raw, value }` pair.
	 *
	 * @param array $parsed Parse result.
	 * @return array{raw: string, value: int|float|null}
	 *
	 * @phpstan-param array{raw: string, value: int|float|null, state: string} $parsed
	 */
	private function strip_state( array $parsed ): array {
		return array(
			'raw'   => $parsed['raw'],
			'value' => $parsed['value'],
		);
	}

	/**
	 * First stored value of a meta key, unserialized.
	 *
	 * Two WordPress details matter here, and getting either wrong corrupts the export:
	 *
	 * 1. `get_post_meta( $id )` with no key returns the **raw** meta cache. Unlike a single-key
	 *    read it does *not* unserialize, so a checkbox value arrives as the literal string
	 *    `a:1:{i:0;s:5:"Venta";}`. Without the `maybe_unserialize()` below, every serialized
	 *    selection would be reported as one unmapped token.
	 * 2. A `NULL` column value arrives as `null` — `foto-del-contrato` is `NULL` on 953 of its
	 *    1089 rows — so callers must tolerate it.
	 *
	 * @param array  $meta Raw meta.
	 * @param string $key  Meta key.
	 * @return mixed Null when the key is absent.
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function first( array $meta, string $key ) {
		if ( ! isset( $meta[ $key ] ) || ! is_array( $meta[ $key ] ) ) {
			return null;
		}

		$value = $meta[ $key ][0] ?? null;

		return is_string( $value ) ? maybe_unserialize( $value ) : $value;
	}

	/**
	 * Stored value as a string, empty when missing or `NULL`.
	 *
	 * @param array  $meta Raw meta.
	 * @param string $key  Meta key.
	 * @return string
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function string( array $meta, string $key ): string {
		$value = $this->first( $meta, $key );

		if ( is_string( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		return '';
	}

	/**
	 * Stored value as a string, or null when missing, `NULL` or empty.
	 *
	 * @param array  $meta Raw meta.
	 * @param string $key  Meta key.
	 * @return string|null
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function nullable_string( array $meta, string $key ): ?string {
		$value = $this->string( $meta, $key );

		return '' === $value ? null : $value;
	}

	/**
	 * Whether a meta key holds something.
	 *
	 * The distinction matters: `foto-del-contrato` exists on 1089 properties but holds `NULL` on
	 * 953 of them, so key presence alone would overstate the number of contract photos sevenfold.
	 *
	 * @param array  $meta Raw meta.
	 * @param string $key  Meta key.
	 * @return bool
	 *
	 * @phpstan-param array<string, mixed> $meta
	 */
	private function has_value( array $meta, string $key ): bool {
		$value = $this->first( $meta, $key );

		if ( null === $value || '' === $value || array() === $value ) {
			return false;
		}

		if ( is_string( $value ) && '' === trim( $value ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Converts a MySQL GMT datetime to the contract's ISO 8601 form.
	 *
	 * @param string $mysql_date Datetime as stored, already in UTC.
	 * @return string Empty string for a zero date (none exist in the source today).
	 */
	private function mysql_to_iso( string $mysql_date ): string {
		if ( '' === $mysql_date || 0 === strpos( $mysql_date, '0000-00-00' ) ) {
			return '';
		}

		return str_replace( ' ', 'T', $mysql_date ) . 'Z';
	}

	/**
	 * Converts a stored Unix timestamp to the contract's ISO 8601 form.
	 *
	 * @param mixed $timestamp Stored value.
	 * @return string|null Null when absent or unusable.
	 */
	private function timestamp_to_iso( $timestamp ): ?string {
		if ( ! is_string( $timestamp ) && ! is_int( $timestamp ) ) {
			return null;
		}

		$text = trim( (string) $timestamp );

		if ( 1 !== preg_match( '/^\d+$/', $text ) ) {
			return null;
		}

		return gmdate( 'Y-m-d\TH:i:s\Z', (int) $text );
	}

	/**
	 * Permalink of the property, for audit.
	 *
	 * @param WP_Post $post Property post.
	 * @return string|null Null when WordPress cannot build one.
	 */
	private function permalink( WP_Post $post ): ?string {
		$url = get_permalink( $post );

		return is_string( $url ) && '' !== $url ? $url : null;
	}
}
