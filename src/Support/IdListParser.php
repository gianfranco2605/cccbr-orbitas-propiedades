<?php
/**
 * Parses the several shapes in which attachment IDs are stored.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Support;

/**
 * Turns a stored media value into a list of attachment IDs.
 *
 * The `imagenes` gallery uses three shapes in the same column — a serialized array (2092
 * properties), a **comma-separated list** (899), and a single ID (26) — plus 13 rows holding
 * `NULL` (`docs/schema.md` §6). `imagen-principal` uses JetEngine's `both` format, an array
 * carrying an `id` alongside a `url`.
 *
 * Order is preserved because the gallery order is the display order Orbitas needs; duplicates are
 * collapsed on first occurrence. Anything that is not an ID is returned separately so the caller
 * can report it instead of silently dropping it.
 *
 * Pure PHP, no WordPress: covered by the unit suite. Values arrive already unserialized.
 */
final class IdListParser {

	/**
	 * Parses a stored value into IDs and rejected tokens.
	 *
	 * @param mixed $raw Stored value: array, comma-separated string, single value or null.
	 * @return array{ids: list<int>, invalid: list<string>}
	 */
	public function parse( $raw ): array {
		$ids     = array();
		$invalid = array();

		foreach ( $this->tokenize( $raw ) as $token ) {
			if ( 1 !== preg_match( '/^\d+$/', $token ) ) {
				$invalid[] = $token;

				continue;
			}

			$id = (int) $token;

			// A zero ID is JetEngine's "nothing selected", not a broken reference.
			if ( $id < 1 || in_array( $id, $ids, true ) ) {
				continue;
			}

			$ids[] = $id;
		}

		return array(
			'ids'     => $ids,
			'invalid' => $invalid,
		);
	}

	/**
	 * The first usable ID of a stored value.
	 *
	 * Handles JetEngine's `both` format, where the value is an array carrying an `id` key next to
	 * a `url`.
	 *
	 * @param mixed $raw Stored value.
	 * @return int|null
	 */
	public function first_id( $raw ): ?int {
		if ( is_array( $raw ) && isset( $raw['id'] ) ) {
			$raw = $raw['id'];
		}

		$parsed = $this->parse( $raw );

		return $parsed['ids'][0] ?? null;
	}

	/**
	 * Flattens any stored shape into a list of trimmed tokens.
	 *
	 * @param mixed $raw Stored value.
	 * @return list<string>
	 */
	private function tokenize( $raw ): array {
		if ( is_array( $raw ) ) {
			$tokens = array();

			foreach ( $raw as $item ) {
				foreach ( $this->tokenize( $item ) as $token ) {
					$tokens[] = $token;
				}
			}

			return $tokens;
		}

		if ( is_int( $raw ) ) {
			return array( (string) $raw );
		}

		if ( ! is_string( $raw ) ) {
			// null and booleans carry no ID.
			return array();
		}

		$tokens = array();

		foreach ( explode( ',', $raw ) as $piece ) {
			$piece = trim( $piece );

			if ( '' === $piece ) {
				continue;
			}

			$tokens[] = $piece;
		}

		return $tokens;
	}
}
