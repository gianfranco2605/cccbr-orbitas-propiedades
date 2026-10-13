<?php
/**
 * JSON Schema for the properties endpoint.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Rest;

/**
 * Describes the response documented in docs/contract.md.
 *
 * The schema is served through the route's `schema` callback, so `OPTIONS` on the endpoint returns
 * it and consumers can generate types from the live API instead of transcribing the document.
 */
final class PropertySchema {

	/**
	 * Schema of a single property.
	 *
	 * @return array<string, mixed>
	 */
	public function item(): array {
		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'cccbr_orbitas_propiedad',
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'id'                    => $this->field( 'integer', 'Stable WordPress post ID. Also the pagination cursor key.' ),
				'title'                 => $this->field( 'string', 'Raw post_title.' ),
				'slug'                  => $this->field( 'string', 'Raw post_name. Trashed properties may keep the __trashed suffix.' ),
				'historical_slug'       => $this->nullable_field( 'string', 'Slug before trashing, from _wp_desired_post_slug. Null when the property was never trashed.' ),
				'url'                   => $this->nullable_field( 'string', 'Permalink for audit. Null when WordPress cannot build one.' ),
				'status'                => $this->field( 'string', 'Current post status.' ),
				'status_before_trash'   => $this->nullable_field( 'string', 'Status before trashing, from _wp_trash_meta_status.' ),
				'trashed_at_gmt'        => $this->nullable_field( 'string', 'UTC ISO 8601 moment of trashing, from _wp_trash_meta_time.' ),
				'created_at_gmt'        => $this->field( 'string', 'UTC ISO 8601 creation date, from post_date_gmt.' ),
				'modified_at_gmt'       => $this->field( 'string', 'UTC ISO 8601 modification date, from post_modified_gmt.' ),
				'content'               => $this->field( 'string', 'Raw stored post_content. Content filters are never applied.' ),
				'description_en'        => $this->field( 'string', 'Raw stored value of descripcion-en-ingles.' ),
				'expiration'            => array(
					'description'          => 'Scheduled expiration from the JetEngine post expiration module, or null.',
					'type'                 => array( 'object', 'null' ),
					'additionalProperties' => false,
					'properties'           => array(
						'at_gmt' => $this->field( 'string', 'UTC ISO 8601 expiration moment, from _jet_pep_period.' ),
						'action' => $this->field( 'string', 'Action WordPress will take, from _jet_pep_action.' ),
					),
				),
				'operation'             => $this->option_list( 'Operation type, from estatus-propiedad (glossary 15).' ),
				'property_type'         => $this->option_list( 'Property type, from tipo-de-propiedad (glossary 12). Zero or one entry.' ),
				'land_use'              => $this->option_list( 'Land use, from uso-de-suelo.' ),
				'exclusivity'           => $this->option_list( 'Exclusivity agreement, from exclusividad.' ),
				'sold'                  => $this->option_list( 'Sold marker, from propiedad_vendida.' ),
				'share'                 => $this->option_list( 'Shared-portfolio marker, from compartir_propiedad.' ),
				'amenities'             => $this->option_list( 'Amenities, from amenidades.' ),
				'price'                 => array(
					'description'          => 'Prices and currency. Currency is never inferred.',
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'sale'       => $this->number_field( 'Sale price, from precio-de-venta.' ),
						'rent'       => $this->number_field( 'Rent price, from precio-de-alquiler.' ),
						'final_sale' => $this->number_field( 'Final sale price, from precio-final-de-venta.' ),
						'currency'   => $this->nullable_field( 'string', 'Currency from moneda: Colones, Dolares, or null when absent.' ),
					),
				),
				'features'              => array(
					'description'          => 'Physical features. Every member keeps the raw stored string.',
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'bedrooms'   => $this->number_field( 'From habitaciones.' ),
						'bathrooms'  => $this->number_field( 'From banos.' ),
						'garage'     => $this->number_field( 'From garage.' ),
						'floors'     => $this->number_field( 'From plantas.' ),
						'land_area'  => $this->number_field( 'From terreno.' ),
						'built_area' => $this->number_field( 'From construccion.' ),
						'year_built' => $this->number_field( 'From ano-de-construccion.' ),
					),
				),
				'registry_number'       => $this->field( 'string', 'Property registry number, from numero-de-finca.' ),
				'purchase_option_terms' => $this->field( 'string', 'Raw stored value of opciondecompracondiciones.' ),
				'video_url'             => $this->field( 'string', 'Raw stored value of video_propiedad. Not validated.' ),
				'map_raw'               => $this->field( 'string', 'Raw stored value of mapa, the string that was geocoded.' ),
				'location'              => array(
					'description' => 'Assigned ubicacion-propiedad terms with their full ancestor path. Empty when the property has no location.',
					'type'        => 'array',
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'id'    => $this->field( 'integer', 'Term ID.' ),
							'name'  => $this->field( 'string', 'Term name.' ),
							'slug'  => $this->field( 'string', 'Term slug.' ),
							'level' => $this->field( 'integer', '1 = provincia, 2 = canton, 3 = distrito.' ),
						),
					),
				),
				'coordinates'           => array(
					'description'          => 'Coordinates from the JetEngine geocoding cache of the mapa field, or null.',
					'type'                 => array( 'object', 'null' ),
					'additionalProperties' => false,
					'properties'           => array(
						'lat'    => $this->field( 'number', 'Latitude, validated to the -90..90 range.' ),
						'lng'    => $this->field( 'number', 'Longitude, validated to the -180..180 range.' ),
						'source' => $this->field( 'string', 'Always jetengine_geocode:mapa. These coordinates are geocoded, not surveyed.' ),
					),
				),
				'media'                 => array(
					'description'          => 'Featured image and ordered gallery.',
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'featured' => $this->attachment( 'Featured image from _thumbnail_id, falling back to imagen-principal. Null when neither resolves.' ),
						'gallery'  => array(
							'description' => 'Gallery from imagenes, in stored order, duplicates removed.',
							'type'        => 'array',
							'items'       => $this->attachment( 'Gallery image.' ),
						),
					),
				),
				'private_documents'     => array(
					'description'          => 'Existence flags only. The documents themselves are never exposed.',
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'has_contract_photo' => $this->field( 'boolean', 'Whether foto-del-contrato holds a value.' ),
						'has_attachments'    => $this->field( 'boolean', 'Whether documentos-anexos holds a value.' ),
					),
				),
				'creator'               => $this->person( 'Author of the property. Only ID, display name and email are ever exposed.', true ),
				'responsible'           => $this->person( 'Responsible associate from corredor_asignado, when different from the creator.', true ),
				'company'               => $this->person( 'Company of the creator through JetEngine relation 131.', false ),
				'associate_id'          => $this->nullable_field( 'string', 'CCCBR associate number of the creator, from the numero-de-asociado user meta.' ),
				'marketplace_id'        => array(
					'description' => 'Always null: no Marketplace or Orbitas identifier exists in the source.',
					'type'        => 'null',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'leads_count'           => $this->field( 'integer', 'Number of leads related through JetEngine relation 171. No lead data is exposed.' ),
				'origin'                => array(
					'description'          => 'Where the property came from, for audit.',
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'source'       => $this->field( 'string', 'wp_user_frontend when _wpuf_form_id is present, otherwise unknown.' ),
						'wpuf_form_id' => $this->nullable_field( 'integer', 'WP User Frontend form ID.' ),
					),
				),
				'issues'                => array(
					'description' => 'Data-quality codes for anything missing, ambiguous or broken on this property. Empty when clean.',
					'type'        => 'array',
					'items'       => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'code'   => $this->field( 'string', 'Stable issue code.' ),
							'field'  => $this->nullable_field( 'string', 'Contract field the issue belongs to.' ),
							'detail' => $this->nullable_field( 'string', 'Short machine-oriented detail such as an ID or a raw token. Never personal data.' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Schema of the collection envelope.
	 *
	 * @return array<string, mixed>
	 */
	public function collection(): array {
		return array(
			'$schema'              => 'http://json-schema.org/draft-04/schema#',
			'title'                => 'cccbr_orbitas_propiedades_collection',
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'data'         => array(
					'description' => 'Properties on this page, ordered by id ascending.',
					'type'        => 'array',
					'items'       => $this->item(),
				),
				'pagination'   => array(
					'description'          => 'Keyset pagination state.',
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'per_page'      => $this->field( 'integer', 'Effective page size.' ),
						'after_id'      => $this->field( 'integer', 'Cursor this page was requested with.' ),
						'next_after_id' => $this->nullable_field( 'integer', 'Pass as the next after_id. Null when the page is empty.' ),
						'has_more'      => $this->field( 'boolean', 'Whether at least one more property exists after this page.' ),
						'max_id'        => $this->nullable_field( 'integer', 'Largest matching property id at request time, for snapshot consistency.' ),
					),
				),
				'totals'       => array(
					'description'          => 'Counts at request time, not across the whole walk.',
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'total'     => $this->field( 'integer', 'Properties matching the filter.' ),
						'by_status' => array(
							'description'          => 'Count per status. Statuses with no match are omitted.',
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'integer' ),
						),
					),
				),
				'generated_at' => $this->field( 'string', 'UTC ISO 8601 moment the response was built.' ),
			),
		);
	}

	/**
	 * A read-only scalar field.
	 *
	 * @param string $type        JSON Schema type.
	 * @param string $description Human description.
	 * @return array<string, mixed>
	 */
	private function field( string $type, string $description ): array {
		return array(
			'description' => $description,
			'type'        => $type,
			'context'     => array( 'view' ),
			'readonly'    => true,
		);
	}

	/**
	 * A read-only scalar field that may be null.
	 *
	 * @param string $type        JSON Schema type.
	 * @param string $description Human description.
	 * @return array<string, mixed>
	 */
	private function nullable_field( string $type, string $description ): array {
		return array(
			'description' => $description,
			'type'        => array( $type, 'null' ),
			'context'     => array( 'view' ),
			'readonly'    => true,
		);
	}

	/**
	 * A `{ raw, value }` numeric field.
	 *
	 * @param string $description Human description.
	 * @return array<string, mixed>
	 */
	private function number_field( string $description ): array {
		return array(
			'description'          => $description . ' value is null unless the stored string is unambiguously numeric; raw always holds the stored string.',
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'raw'   => $this->field( 'string', 'Stored value exactly as it is in the database.' ),
				'value' => $this->nullable_field( 'number', 'Parsed number, or null when empty, ambiguous or unparsable.' ),
			),
		);
	}

	/**
	 * A list of `{ key, label }` option selections.
	 *
	 * @param string $description Human description.
	 * @return array<string, mixed>
	 */
	private function option_list( string $description ): array {
		return array(
			'description' => $description . ' label is null for a token that matches no known option; the raw token is kept as key.',
			'type'        => 'array',
			'items'       => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'key'   => $this->field( 'string', 'Canonical option key, or the raw stored token when unmapped.' ),
					'label' => $this->nullable_field( 'string', 'Human label, or null when the token could not be mapped.' ),
				),
			),
		);
	}

	/**
	 * An attachment object.
	 *
	 * @param string $description Human description.
	 * @return array<string, mixed>
	 */
	private function attachment( string $description ): array {
		return array(
			'description'          => $description,
			'type'                 => array( 'object', 'null' ),
			'additionalProperties' => false,
			'properties'           => array(
				'id'        => $this->field( 'integer', 'Attachment post ID.' ),
				'url'       => $this->nullable_field( 'string', 'Full-size URL.' ),
				'mime_type' => $this->nullable_field( 'string', 'Stored MIME type.' ),
				'width'     => $this->nullable_field( 'integer', 'Full-size width in pixels.' ),
				'height'    => $this->nullable_field( 'integer', 'Full-size height in pixels.' ),
				'alt'       => $this->field( 'string', 'Alternative text.' ),
			),
		);
	}

	/**
	 * A person object built from the explicit allow-list.
	 *
	 * @param string $description   Human description.
	 * @param bool   $include_email Whether the object carries an email address.
	 * @return array<string, mixed>
	 */
	private function person( string $description, bool $include_email ): array {
		$properties = array(
			'id'           => $this->field( 'integer', 'WordPress user ID.' ),
			'display_name' => $this->field( 'string', 'Display name.' ),
		);

		if ( $include_email ) {
			$properties['email'] = $this->field( 'string', 'Account email address.' );
		}

		return array(
			'description'          => $description,
			'type'                 => array( 'object', 'null' ),
			'additionalProperties' => false,
			'properties'           => $properties,
		);
	}
}
