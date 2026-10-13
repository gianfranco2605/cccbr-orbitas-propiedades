<?php
/**
 * Conservative numeric parsing for stored text fields.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Support;

/**
 * Parses a stored value into a number **only when the intent is unambiguous**.
 *
 * Every numeric-ish field in the source contains non-numeric values (`docs/schema.md` §2.4), and
 * values such as `150.000` or `150,000` could mean either one hundred fifty thousand or a number
 * with three decimals. Guessing would silently corrupt prices and areas, so this class refuses:
 * it reports `ambiguous` and keeps the raw string, which the consumer can then resolve with CCCBR.
 *
 * Pure PHP, no WordPress: covered by the unit suite.
 */
final class NumberParser {

	/**
	 * The field is empty, absent or `NULL` in the database.
	 */
	public const STATE_EMPTY = 'empty';

	/**
	 * The value was parsed exactly.
	 */
	public const STATE_EXACT = 'exact';

	/**
	 * The value is digits and separators, but the separator meaning cannot be determined.
	 */
	public const STATE_AMBIGUOUS = 'ambiguous';

	/**
	 * The value is not a number at all (units, ranges, words).
	 */
	public const STATE_UNPARSABLE = 'unparsable';

	/**
	 * Parses a stored value.
	 *
	 * @param mixed $raw Stored value: a string, a number, `null`, or the first element of a meta array.
	 * @return array{raw: string, value: int|float|null, state: string}
	 */
	public function parse( $raw ): array {
		$text = $this->to_text( $raw );

		if ( '' === $text ) {
			return $this->result( $text, null, self::STATE_EMPTY );
		}

		// An exact integer: the only shape with no possible second reading.
		if ( 1 === preg_match( '/^-?\d+$/', $text ) ) {
			return $this->result( $text, (int) $text, self::STATE_EXACT );
		}

		// A decimal with one or two fraction digits: `1250.5`, `1250.50`. Three or more fraction
		// digits after a dot is the shape Spanish-language thousands separators produce, so it is
		// treated as ambiguous below rather than parsed.
		if ( 1 === preg_match( '/^-?\d+\.\d{1,2}$/', $text ) ) {
			return $this->result( $text, (float) $text, self::STATE_EXACT );
		}

		// Digits with separators only: a real number whose separator convention is unknowable.
		if ( 1 === preg_match( '/^-?\d[\d.,]*$/', $text ) ) {
			return $this->result( $text, null, self::STATE_AMBIGUOUS );
		}

		return $this->result( $text, null, self::STATE_UNPARSABLE );
	}

	/**
	 * Normalizes any stored shape to a trimmed string.
	 *
	 * @param mixed $raw Stored value.
	 * @return string
	 */
	private function to_text( $raw ): string {
		if ( is_int( $raw ) || is_float( $raw ) ) {
			return (string) $raw;
		}

		if ( ! is_string( $raw ) ) {
			// null, array, bool and anything else carry no number.
			return '';
		}

		return trim( $raw );
	}

	/**
	 * Builds the result triple.
	 *
	 * @param string         $raw   Raw text as stored.
	 * @param int|float|null $value Parsed value.
	 * @param string         $state One of the STATE_* constants.
	 * @return array{raw: string, value: int|float|null, state: string}
	 */
	private function result( string $raw, $value, string $state ): array {
		return array(
			'raw'   => $raw,
			'value' => $value,
			'state' => $state,
		);
	}
}
