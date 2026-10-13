<?php
/**
 * Database access for the properties collection.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Repository;

use ElPuas\CCCBR\OrbitasPropiedades\Rest\QueryArgs;
use WP_Post;
use wpdb;

/**
 * Selects property IDs and aggregate counts.
 *
 * `WP_Query` has no keyset mode: it paginates with `LIMIT … OFFSET`, which can skip or repeat rows
 * when the set changes during a walk, and it cannot express `ID > :cursor`. So the two queries
 * here go through `$wpdb` with prepared statements — the exception §4.2 of `CLAUDE.md` allows
 * "where no API exists". Everything else (meta, terms, users, attachments) is read through
 * WordPress APIs with primed caches.
 */
final class PropertyRepository {

	/**
	 * The post type this plugin exposes.
	 */
	public const POST_TYPE = 'cccbr-propiedades';

	/**
	 * Marker replaced by the status placeholder list.
	 */
	private const STATUS_MARKER = '{statuses}';

	/**
	 * WordPress database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database handle.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Selects one page of property IDs, plus one extra row used to decide `has_more`.
	 *
	 * Ordering is by the primary key ascending, so it is total and stable: no row can move across
	 * a page boundary while the walk is running.
	 *
	 * @param QueryArgs $args Validated request arguments.
	 * @return list<int> Ascending IDs, up to `per_page + 1` of them.
	 */
	public function find_page_ids( QueryArgs $args ): array {
		$statuses = $args->statuses();

		$values = $statuses;
		array_unshift( $values, self::POST_TYPE );
		$values[] = $args->after_id();
		$values[] = $args->per_page() + 1;

		$sql = $this->prepare_with_statuses(
			'SELECT ID FROM ' . $this->wpdb->posts . '
				WHERE post_type = %s
					AND post_status IN ( ' . self::STATUS_MARKER . ' )
					AND ID > %d
				ORDER BY ID ASC
				LIMIT %d',
			$values,
			count( $statuses )
		);

		if ( null === $sql ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Keyset pagination has no WordPress API; the statement is prepared in prepare_with_statuses(), and the response must reflect live data so reconciliation can be trusted.
		$ids = $this->wpdb->get_col( $sql );

		return array_values( array_map( 'intval', $ids ) );
	}

	/**
	 * Loads the posts of one page, with the post, meta and term caches primed in bulk.
	 *
	 * This is `WP_Query` territory, so it uses `get_posts()` rather than SQL. Two defaults matter:
	 * `post_status` is the explicit list, because `'any'` silently drops `trash`; and
	 * `suppress_filters` stays at the `get_posts()` default of true, so no other plugin or theme
	 * can narrow an export that the ticket requires to be complete.
	 *
	 * @param array     $ids  Ascending property IDs.
	 * @param QueryArgs $args Validated request arguments.
	 * @return list<WP_Post> Posts in the same ascending ID order.
	 *
	 * @phpstan-param list<int> $ids
	 */
	public function find_posts( array $ids, QueryArgs $args ): array {
		if ( array() === $ids ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => $args->statuses(),
				'post__in'               => $ids,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'posts_per_page'         => count( $ids ),
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
			)
		);

		$found = array();

		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$found[] = $post;
			}
		}

		return $found;
	}

	/**
	 * Counts the matching properties per status and reports the largest matching ID.
	 *
	 * One aggregate query answers all three questions the envelope needs: `totals.total`,
	 * `totals.by_status` and `pagination.max_id`. None of them depends on the cursor, so they stay
	 * identical across every page of a walk, which is what makes the snapshot recipe in
	 * `docs/contract.md` §5.2 work.
	 *
	 * @param QueryArgs $args Validated request arguments.
	 * @return array{total: int, by_status: array<string, int>, max_id: int|null}
	 */
	public function aggregate( QueryArgs $args ): array {
		$statuses = $args->statuses();

		$values = $statuses;
		array_unshift( $values, self::POST_TYPE );

		$empty = array(
			'total'     => 0,
			'by_status' => array(),
			'max_id'    => null,
		);

		$sql = $this->prepare_with_statuses(
			'SELECT post_status, COUNT(*) AS total, MAX(ID) AS max_id FROM ' . $this->wpdb->posts . '
				WHERE post_type = %s
					AND post_status IN ( ' . self::STATUS_MARKER . ' )
				GROUP BY post_status',
			$values,
			count( $statuses )
		);

		if ( null === $sql ) {
			return $empty;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Aggregate over a filtered set; no WordPress API returns it in one query, and a cached count would make reconciliation meaningless.
		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return $empty;
		}

		$total     = 0;
		$by_status = array();
		$max_id    = null;

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['post_status'], $row['total'] ) ) {
				continue;
			}

			$status = (string) $row['post_status'];
			$count  = (int) $row['total'];

			$by_status[ $status ] = $count;
			$total               += $count;

			$row_max = isset( $row['max_id'] ) ? (int) $row['max_id'] : 0;

			if ( null === $max_id || $row_max > $max_id ) {
				$max_id = $row_max;
			}
		}

		return array(
			'total'     => $total,
			'by_status' => $by_status,
			'max_id'    => $max_id,
		);
	}

	/**
	 * Prepares a statement whose `IN` list length is only known at runtime.
	 *
	 * `$wpdb->prepare()` cannot expand a variable-length list, so the `%s` placeholders are built
	 * from the **count** of statuses — never from their values — and every value, statuses
	 * included, is then bound by `prepare()`. This is the only place in the plugin that assembles
	 * SQL text, which is why the sniff exemptions live here and nowhere else.
	 *
	 * @param string $template     SQL containing the status marker.
	 * @param array  $values       Values to bind, in placeholder order.
	 * @param int    $status_count Number of status placeholders to expand.
	 * @return string|null The prepared statement, or null when it could not be prepared.
	 *
	 * @phpstan-param list<string|int> $values
	 */
	private function prepare_with_statuses( string $template, array $values, int $status_count ): ?string {
		if ( $status_count < 1 ) {
			return null;
		}

		$sql = str_replace(
			self::STATUS_MARKER,
			implode( ', ', array_fill( 0, $status_count, '%s' ) ),
			$template
		);

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- The placeholder list is derived from a count, not from input; prepare() binds every value, and the number of replacements is only known at runtime.
		// PHPStan wants a literal-string here, which a runtime-length placeholder list cannot be.
		// Both parts are safe by construction: the template is a literal, and the only substitution
		// is a list of `%s` built from an integer count.
		// @phpstan-ignore-next-line -- Safe by construction, see the note above.
		$prepared = $this->wpdb->prepare( $sql, $values );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		return is_string( $prepared ) ? $prepared : null;
	}
}
