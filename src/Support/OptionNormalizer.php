<?php
/**
 * Tolerant normalization of stored select and checkbox values.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Support;

/**
 * Turns whatever the source stored for a select or checkbox field into `{ key, label }` entries.
 *
 * The data uses six storage shapes and mixes option keys, exact labels, case-folded labels,
 * slugified labels and free text in the same field (`docs/schema.md` §2.2). This class implements
 * the resolution order the contract documents, keeps every raw token it cannot resolve, and never
 * invents a label.
 *
 * Pure PHP, no WordPress: covered by the unit suite. Values reaching it are already unserialized,
 * because WordPress's meta cache does that.
 */
final class OptionNormalizer {

	/**
	 * Tokens that carry no selection and are dropped.
	 *
	 * `false` / `true` come from JetFormBuilder writing a boolean into a checkbox; `-` and `-1`
	 * are select placeholders.
	 *
	 * @var list<string>
	 */
	private const JUNK_TOKENS = array( 'false', 'true', '-', '-1' );

	/**
	 * Normalizes a stored value.
	 *
	 * @param mixed $raw     Stored value: string, array, nested array, or null.
	 * @param array $options Option set as `key => label`. May be empty.
	 * @return list<array{key: string, label: string|null}>
	 *
	 * @phpstan-param array<string, string> $options
	 */
	public function normalize( $raw, array $options ): array {
		$lookup  = $this->build_lookup( $options );
		$entries = array();
		$seen    = array();

		foreach ( $this->tokenize( $raw ) as $token ) {
			$resolved = $this->resolve( $token, $lookup );

			if ( in_array( $resolved['key'], $seen, true ) ) {
				continue;
			}

			$seen[]    = $resolved['key'];
			$entries[] = $resolved;
		}

		return $entries;
	}

	/**
	 * Flattens any stored shape into a list of meaningful tokens.
	 *
	 * Arrays are flattened recursively, every scalar is split on `|`, and junk is dropped.
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

		if ( is_int( $raw ) || is_float( $raw ) ) {
			$raw = (string) $raw;
		}

		if ( ! is_string( $raw ) ) {
			// null and booleans carry no selection.
			return array();
		}

		$tokens = array();

		foreach ( explode( '|', $raw ) as $piece ) {
			$piece = trim( $piece );

			if ( '' === $piece ) {
				continue;
			}

			if ( in_array( strtolower( $piece ), self::JUNK_TOKENS, true ) ) {
				continue;
			}

			$tokens[] = $piece;
		}

		return $tokens;
	}

	/**
	 * Resolves one token against the option set.
	 *
	 * Order: exact key, exact label, folded key, folded label, slugified key, slugified label.
	 * How a token matched is not reported: a successful mapping is not a data problem, and saying so
	 * on 367 properties was noise. Only a token that matches **nothing** is reported.
	 *
	 * @param string $token  Raw token.
	 * @param array  $lookup Pre-built lookup tables.
	 * @return array{key: string, label: string|null}
	 *
	 * @phpstan-param array{keys: array<string, string>, labels: array<string, string>, folded: array<string, string>, slugs: array<string, string>} $lookup
	 */
	private function resolve( string $token, array $lookup ): array {
		if ( isset( $lookup['keys'][ $token ] ) ) {
			return $this->entry( $token, $lookup['keys'][ $token ] );
		}

		if ( isset( $lookup['labels'][ $token ] ) ) {
			$key = $lookup['labels'][ $token ];

			return $this->entry( $key, $lookup['keys'][ $key ] ?? $token );
		}

		$folded = $this->fold( $token );

		if ( isset( $lookup['folded'][ $folded ] ) ) {
			$key = $lookup['folded'][ $folded ];

			return $this->entry( $key, $lookup['keys'][ $key ] ?? $token );
		}

		$slug = $this->slugify( $token );

		if ( isset( $lookup['slugs'][ $slug ] ) ) {
			$key = $lookup['slugs'][ $slug ];

			return $this->entry( $key, $lookup['keys'][ $key ] ?? $token );
		}

		// Unmapped: the raw token is preserved and the label stays null. Never guessed.
		return array(
			'key'   => $token,
			'label' => null,
		);
	}

	/**
	 * Builds a resolved entry.
	 *
	 * @param string $key   Canonical option key.
	 * @param string $label Option label.
	 * @return array{key: string, label: string|null}
	 */
	private function entry( string $key, string $label ): array {
		return array(
			'key'   => $key,
			'label' => $label,
		);
	}

	/**
	 * Builds the lookup tables for an option set.
	 *
	 * @param array $options Option set as `key => label`.
	 * @return array{keys: array<string, string>, labels: array<string, string>, folded: array<string, string>, slugs: array<string, string>}
	 *
	 * @phpstan-param array<string, string> $options
	 */
	private function build_lookup( array $options ): array {
		$lookup = array(
			'keys'   => array(),
			'labels' => array(),
			'folded' => array(),
			'slugs'  => array(),
		);

		foreach ( $options as $key => $label ) {
			$key   = (string) $key;
			$label = (string) $label;

			$lookup['keys'][ $key ]     = $label;
			$lookup['labels'][ $label ] = $key;

			foreach ( array( $key, $label ) as $candidate ) {
				$folded = $this->fold( $candidate );
				$slug   = $this->slugify( $candidate );

				if ( '' !== $folded && ! isset( $lookup['folded'][ $folded ] ) ) {
					$lookup['folded'][ $folded ] = $key;
				}

				if ( '' !== $slug && ! isset( $lookup['slugs'][ $slug ] ) ) {
					$lookup['slugs'][ $slug ] = $key;
				}
			}
		}

		return $lookup;
	}

	/**
	 * Folds accents, case and repeated whitespace.
	 *
	 * Implemented locally rather than with `remove_accents()` so the class stays WordPress-free
	 * and unit-testable. The map covers the characters that occur in CCCBR's Spanish option sets.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function fold( string $value ): string {
		$map = array(
			'á' => 'a',
			'à' => 'a',
			'ä' => 'a',
			'â' => 'a',
			'ã' => 'a',
			'Á' => 'a',
			'À' => 'a',
			'Ä' => 'a',
			'Â' => 'a',
			'Ã' => 'a',
			'é' => 'e',
			'è' => 'e',
			'ë' => 'e',
			'ê' => 'e',
			'É' => 'e',
			'È' => 'e',
			'Ë' => 'e',
			'Ê' => 'e',
			'í' => 'i',
			'ì' => 'i',
			'ï' => 'i',
			'î' => 'i',
			'Í' => 'i',
			'Ì' => 'i',
			'Ï' => 'i',
			'Î' => 'i',
			'ó' => 'o',
			'ò' => 'o',
			'ö' => 'o',
			'ô' => 'o',
			'õ' => 'o',
			'Ó' => 'o',
			'Ò' => 'o',
			'Ö' => 'o',
			'Ô' => 'o',
			'Õ' => 'o',
			'ú' => 'u',
			'ù' => 'u',
			'ü' => 'u',
			'û' => 'u',
			'Ú' => 'u',
			'Ù' => 'u',
			'Ü' => 'u',
			'Û' => 'u',
			'ñ' => 'n',
			'Ñ' => 'n',
			'ç' => 'c',
			'Ç' => 'c',
		);

		$folded = strtr( $value, $map );
		$folded = (string) preg_replace( '/\s+/', ' ', $folded );

		return strtolower( trim( $folded ) );
	}

	/**
	 * Builds the underscore slug form used by the JetFormBuilder submissions.
	 *
	 * `Local comercial` becomes `local_comercial`, which is how 55 properties store it.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function slugify( string $value ): string {
		$slug = (string) preg_replace( '/[^a-z0-9]+/', '_', $this->fold( $value ) );

		return trim( $slug, '_' );
	}
}
