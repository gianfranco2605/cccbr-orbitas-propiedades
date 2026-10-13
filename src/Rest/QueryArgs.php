<?php
/**
 * Validated request arguments for the properties collection.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Rest;

use WP_Error;
use WP_REST_Request;

/**
 * Immutable, already-validated view of the three supported request arguments.
 *
 * The same rules are used twice on purpose: the route declares them as argument
 * `validate_callback`s, so WordPress rejects a bad request before the callback runs and `OPTIONS`
 * documents them, and {@see self::from_request()} applies them again at the point of use, so the
 * controller can never receive an out-of-range value even if a filter stripped the route's args.
 */
final class QueryArgs {

	/**
	 * Default page size.
	 */
	public const PER_PAGE_DEFAULT = 50;

	/**
	 * Hard maximum page size. A larger request is rejected, never silently clamped.
	 */
	public const PER_PAGE_MAX = 100;

	/**
	 * Statuses a caller may ask for, and the default set when no filter is given.
	 *
	 * Three groups are deliberately absent:
	 *
	 * - **`trash`** — CCCBR decided that deleted properties must not reach Orbitas. Excluding it
	 *   here removes trashed properties from the default page, from `totals`, from the loader, and
	 *   makes `?status=trash` a 400 like any other unknown value. One constant, four behaviours.
	 * - `auto-draft` — an unsaved editor placeholder with no property data.
	 * - `inherit` — belongs to revisions and attachments, not to properties.
	 *
	 * @var list<string>
	 */
	public const ALLOWED_STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Page size.
	 *
	 * @var int
	 */
	private int $per_page;

	/**
	 * Keyset cursor: only properties with a larger ID are returned.
	 *
	 * @var int
	 */
	private int $after_id;

	/**
	 * Requested statuses, or every allowed status when the caller did not filter.
	 *
	 * @var list<string>
	 */
	private array $statuses;

	/**
	 * Whether the caller passed an explicit status filter.
	 *
	 * @var bool
	 */
	private bool $has_status_filter;

	/**
	 * Constructor.
	 *
	 * @param int   $per_page          Page size, already validated.
	 * @param int   $after_id          Keyset cursor, already validated.
	 * @param array $statuses          Statuses, already validated and non-empty.
	 * @param bool  $has_status_filter Whether the statuses came from the request.
	 *
	 * @phpstan-param list<string> $statuses
	 */
	private function __construct( int $per_page, int $after_id, array $statuses, bool $has_status_filter ) {
		$this->per_page          = $per_page;
		$this->after_id          = $after_id;
		$this->statuses          = $statuses;
		$this->has_status_filter = $has_status_filter;
	}

	/**
	 * Builds the arguments from a REST request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return self|WP_Error Validated arguments, or a 400 error naming the offending argument.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 */
	public static function from_request( WP_REST_Request $request ) {
		$per_page_raw = $request->get_param( 'per_page' );
		$valid        = self::validate_per_page( $per_page_raw );

		if ( $valid instanceof WP_Error ) {
			return $valid;
		}

		$after_id_raw = $request->get_param( 'after_id' );
		$valid        = self::validate_after_id( $after_id_raw );

		if ( $valid instanceof WP_Error ) {
			return $valid;
		}

		$status_raw = $request->get_param( 'status' );
		$valid      = self::validate_status( $status_raw );

		if ( $valid instanceof WP_Error ) {
			return $valid;
		}

		$statuses = self::to_status_list( $status_raw );

		return new self(
			self::to_integer( $per_page_raw ) ?? self::PER_PAGE_DEFAULT,
			self::to_integer( $after_id_raw ) ?? 0,
			array() === $statuses ? self::ALLOWED_STATUSES : $statuses,
			array() !== $statuses
		);
	}

	/**
	 * Validates `per_page`.
	 *
	 * @param mixed $value Raw parameter value.
	 * @return true|WP_Error
	 */
	public static function validate_per_page( $value ) {
		if ( null === $value ) {
			return true;
		}

		$per_page = self::to_integer( $value );

		if ( null === $per_page || $per_page < 1 || $per_page > self::PER_PAGE_MAX ) {
			return new WP_Error(
				'cccbr_orbitas_invalid_per_page',
				sprintf(
					/* translators: %d: maximum page size. */
					__( 'The per_page argument must be an integer between 1 and %d.', 'cccbr-orbitas-propiedades' ),
					self::PER_PAGE_MAX
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validates `after_id`.
	 *
	 * @param mixed $value Raw parameter value.
	 * @return true|WP_Error
	 */
	public static function validate_after_id( $value ) {
		if ( null === $value ) {
			return true;
		}

		$after_id = self::to_integer( $value );

		if ( null === $after_id || $after_id < 0 ) {
			return new WP_Error(
				'cccbr_orbitas_invalid_after_id',
				__( 'The after_id argument must be an integer of 0 or more.', 'cccbr-orbitas-propiedades' ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Validates `status`.
	 *
	 * Accepts a single value, a comma-separated list and a repeated parameter.
	 *
	 * @param mixed $value Raw parameter value.
	 * @return true|WP_Error
	 */
	public static function validate_status( $value ) {
		if ( null === $value || '' === $value || array() === $value ) {
			return true;
		}

		$raw = is_array( $value ) ? $value : array( $value );

		foreach ( $raw as $item ) {
			if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
				return self::invalid_status_error();
			}

			foreach ( explode( ',', (string) $item ) as $token ) {
				$token = trim( $token );

				if ( '' === $token ) {
					continue;
				}

				if ( ! in_array( $token, self::ALLOWED_STATUSES, true ) ) {
					return self::invalid_status_error();
				}
			}
		}

		return true;
	}

	/**
	 * Page size.
	 *
	 * @return int
	 */
	public function per_page(): int {
		return $this->per_page;
	}

	/**
	 * Keyset cursor.
	 *
	 * @return int
	 */
	public function after_id(): int {
		return $this->after_id;
	}

	/**
	 * Statuses to return.
	 *
	 * @return list<string>
	 */
	public function statuses(): array {
		return $this->statuses;
	}

	/**
	 * Whether the caller filtered by status.
	 *
	 * @return bool
	 */
	public function has_status_filter(): bool {
		return $this->has_status_filter;
	}

	/**
	 * Converts a raw parameter to an integer, rejecting anything that is not an exact integer.
	 *
	 * `"50"` is accepted because query strings are text; `"50.5"`, `"abc"`, `""` and arrays are not.
	 *
	 * @param mixed $value Raw parameter value.
	 * @return int|null The integer, or null when the value is unusable.
	 */
	private static function to_integer( $value ): ?int {
		if ( is_int( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && 1 === preg_match( '/^-?\d+$/', trim( $value ) ) ) {
			return (int) trim( $value );
		}

		return null;
	}

	/**
	 * Normalizes an already-validated status parameter to a list of statuses.
	 *
	 * @param mixed $value Raw parameter value.
	 * @return list<string> Empty list when no filter was requested.
	 */
	private static function to_status_list( $value ): array {
		if ( null === $value || '' === $value || array() === $value ) {
			return array();
		}

		$raw    = is_array( $value ) ? $value : array( $value );
		$tokens = array();

		foreach ( $raw as $item ) {
			if ( ! is_string( $item ) && ! is_numeric( $item ) ) {
				continue;
			}

			foreach ( explode( ',', (string) $item ) as $token ) {
				$token = trim( $token );

				if ( '' === $token || ! in_array( $token, self::ALLOWED_STATUSES, true ) ) {
					continue;
				}

				$tokens[] = $token;
			}
		}

		return array_values( array_unique( $tokens ) );
	}

	/**
	 * The single error returned for any invalid status value.
	 *
	 * The message never echoes the submitted value back to the caller.
	 *
	 * @return WP_Error
	 */
	private static function invalid_status_error(): WP_Error {
		return new WP_Error(
			'cccbr_orbitas_invalid_status',
			sprintf(
				/* translators: %s: comma-separated list of allowed statuses. */
				__( 'The status argument accepts only these values: %s.', 'cccbr-orbitas-propiedades' ),
				implode( ', ', self::ALLOWED_STATUSES )
			),
			array( 'status' => 400 )
		);
	}
}
