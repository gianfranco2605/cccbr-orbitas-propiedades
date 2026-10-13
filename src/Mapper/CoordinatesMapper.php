<?php
/**
 * Maps the JetEngine geocoding cache to contract coordinates.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Mapper;

use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCode;
use ElPuas\CCCBR\OrbitasPropiedades\Support\IssueCollector;

/**
 * Reads the three meta keys JetEngine's Maps Listings module writes for the `mapa` field.
 *
 * The keys are `md5( 'mapa' )` suffixed with `_lat`, `_lng` and `_hash`
 * (`jet-engine/includes/modules/maps-listings/inc/map-field.php:242` and
 * `…/inc/sources/posts.php:28`), and the stored `_hash` is `md5( <address that was geocoded> )`
 * (`…/inc/lat-lng.php:447`), which makes it possible to tell whether the cached coordinates still
 * belong to the current address.
 *
 * Two hazards are measured in the real data (`docs/schema.md` §5): **107 of 498** properties store
 * a geocoding *error marker* instead of a coordinate, and **7** hold coordinates for an address
 * that has since changed. Neither is exposed as a value.
 *
 * Pure PHP, no WordPress: covered by the unit suite.
 */
final class CoordinatesMapper {

	/**
	 * JetEngine field the coordinates were geocoded from.
	 */
	public const SOURCE_FIELD = 'mapa';

	/**
	 * Reported as `coordinates.source`, so a consumer knows these are geocoded, not surveyed.
	 */
	public const SOURCE_LABEL = 'jetengine_geocode:mapa';

	/**
	 * Marker JetEngine writes when geocoding fails (`…/inc/lat-lng.php:12`).
	 */
	public const ERROR_PREFIX = 'je-coord-error';

	/**
	 * Meta key prefix for the coordinate cache of the `mapa` field.
	 *
	 * @return string
	 */
	public function meta_prefix(): string {
		return md5( self::SOURCE_FIELD );
	}

	/**
	 * Maps the stored cache to contract coordinates.
	 *
	 * @param mixed          $lat      Stored latitude.
	 * @param mixed          $lng      Stored longitude.
	 * @param mixed          $hash     Stored address hash.
	 * @param string         $map_raw  Current raw value of the `mapa` field.
	 * @param IssueCollector $issues   Issue sink.
	 * @return array{lat: float, lng: float, source: string}|null
	 */
	public function map( $lat, $lng, $hash, string $map_raw, IssueCollector $issues ): ?array {
		$lat_text = $this->to_text( $lat );
		$lng_text = $this->to_text( $lng );

		// No geocode cache at all: 2557 of 3055 properties. Not an issue, just absent.
		if ( '' === $lat_text && '' === $lng_text ) {
			return null;
		}

		if ( $this->is_error_marker( $lat_text ) || $this->is_error_marker( $lng_text ) ) {
			$issues->add( IssueCode::COORDINATES_GEOCODE_ERROR, 'coordinates', null );

			return null;
		}

		if ( ! $this->is_valid_pair( $lat_text, $lng_text ) ) {
			$issues->add( IssueCode::COORDINATES_INVALID, 'coordinates', null );

			return null;
		}

		$stored_hash = $this->to_text( $hash );

		if ( '' !== $stored_hash && md5( $map_raw ) !== $stored_hash ) {
			// The address changed after it was geocoded: the coordinates are still the best
			// available answer, but they belong to the previous address.
			$issues->add( IssueCode::COORDINATES_STALE, 'coordinates', null );
		}

		return array(
			'lat'    => (float) $lat_text,
			'lng'    => (float) $lng_text,
			'source' => self::SOURCE_LABEL,
		);
	}

	/**
	 * Whether a stored value is a JetEngine geocoding error marker.
	 *
	 * @param string $value Stored value.
	 * @return bool
	 */
	private function is_error_marker( string $value ): bool {
		return '' !== $value && false !== strpos( $value, self::ERROR_PREFIX );
	}

	/**
	 * Validates a coordinate pair the way JetEngine itself does.
	 *
	 * @param string $lat Latitude text.
	 * @param string $lng Longitude text.
	 * @return bool
	 */
	private function is_valid_pair( string $lat, string $lng ): bool {
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return false;
		}

		return abs( (float) $lat ) <= 90.0 && abs( (float) $lng ) <= 180.0;
	}

	/**
	 * Normalizes a stored value to trimmed text.
	 *
	 * @param mixed $value Stored value.
	 * @return string
	 */
	private function to_text( $value ): string {
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		if ( ! is_string( $value ) ) {
			return '';
		}

		return trim( $value );
	}
}
