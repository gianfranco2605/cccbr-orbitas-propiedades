<?php
/**
 * Stable data-quality issue codes.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Support;

/**
 * The closed set of codes a property may report in `issues`.
 *
 * These values are part of the published contract: consumers branch on them, so they are only
 * ever added to, never renamed. Each one is documented in `docs/contract.md` §4.11.
 */
final class IssueCode {

	/**
	 * `nombre-de-propiedad` holds a value that differs from `post_title`.
	 */
	public const TITLE_NAME_MISMATCH = 'title_name_mismatch';

	/**
	 * An option token matched only after case, accent or slug folding.
	 */
	public const OPTION_VALUE_NORMALIZED = 'option_value_normalized';

	/**
	 * An option token matched no known option and is returned raw.
	 */
	public const OPTION_VALUE_UNMAPPED = 'option_value_unmapped';

	/**
	 * A numeric value could be thousands-separated or decimal, so it was not parsed.
	 */
	public const NUMBER_AMBIGUOUS = 'number_ambiguous';

	/**
	 * A non-empty numeric field could not be parsed at all.
	 */
	public const NUMBER_UNPARSABLE = 'number_unparsable';

	/**
	 * A sale price is expected but missing.
	 */
	public const NUMBER_MISSING = 'number_missing';

	/**
	 * A price has a value but no currency is recorded.
	 */
	public const PRICE_CURRENCY_MISSING = 'price_currency_missing';

	/**
	 * The property has no location term.
	 */
	public const LOCATION_MISSING = 'location_missing';

	/**
	 * At least one of province, cantón or distrito could not be determined.
	 */
	public const LOCATION_INCOMPLETE = 'location_incomplete';

	/**
	 * Two terms occupy the same level, or a term's ancestry contradicts another assigned term.
	 */
	public const LOCATION_AMBIGUOUS = 'location_ambiguous';

	/**
	 * The stored coordinates are a JetEngine geocoding error marker.
	 */
	public const COORDINATES_GEOCODE_ERROR = 'coordinates_geocode_error';

	/**
	 * The stored coordinates are non-numeric or out of range.
	 */
	public const COORDINATES_INVALID = 'coordinates_invalid';

	/**
	 * The geocode cache no longer matches the current address.
	 */
	public const COORDINATES_STALE = 'coordinates_stale';

	/**
	 * Neither `_thumbnail_id` nor `imagen-principal` resolves to an attachment.
	 */
	public const MEDIA_FEATURED_MISSING = 'media_featured_missing';

	/**
	 * The gallery is empty.
	 */
	public const MEDIA_GALLERY_EMPTY = 'media_gallery_empty';

	/**
	 * A gallery token is not an attachment ID.
	 */
	public const MEDIA_GALLERY_UNPARSABLE = 'media_gallery_unparsable';

	/**
	 * A referenced attachment post does not exist.
	 */
	public const MEDIA_ATTACHMENT_MISSING = 'media_attachment_missing';

	/**
	 * The attachment exists but its file does not.
	 */
	public const MEDIA_FILE_MISSING = 'media_file_missing';

	/**
	 * `_jet_pep_period` is not a usable timestamp.
	 */
	public const EXPIRATION_INVALID = 'expiration_invalid';

	/**
	 * `post_author` points at a user that does not exist.
	 */
	public const CREATOR_MISSING = 'creator_missing';

	/**
	 * `corredor_asignado` points at a user that does not exist.
	 */
	public const RESPONSIBLE_MISSING_USER = 'responsible_missing_user';

	/**
	 * The creator has no `numero-de-asociado`.
	 */
	public const ASSOCIATE_ID_MISSING = 'associate_id_missing';

	/**
	 * No origin marker is recorded for the property.
	 */
	public const ORIGIN_UNKNOWN = 'origin_unknown';

	/**
	 * Every code, for documentation and tests.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::TITLE_NAME_MISMATCH,
			self::OPTION_VALUE_NORMALIZED,
			self::OPTION_VALUE_UNMAPPED,
			self::NUMBER_AMBIGUOUS,
			self::NUMBER_UNPARSABLE,
			self::NUMBER_MISSING,
			self::PRICE_CURRENCY_MISSING,
			self::LOCATION_MISSING,
			self::LOCATION_INCOMPLETE,
			self::LOCATION_AMBIGUOUS,
			self::COORDINATES_GEOCODE_ERROR,
			self::COORDINATES_INVALID,
			self::COORDINATES_STALE,
			self::MEDIA_FEATURED_MISSING,
			self::MEDIA_GALLERY_EMPTY,
			self::MEDIA_GALLERY_UNPARSABLE,
			self::MEDIA_ATTACHMENT_MISSING,
			self::MEDIA_FILE_MISSING,
			self::EXPIRATION_INVALID,
			self::CREATOR_MISSING,
			self::RESPONSIBLE_MISSING_USER,
			self::ASSOCIATE_ID_MISSING,
			self::ORIGIN_UNKNOWN,
		);
	}
}
