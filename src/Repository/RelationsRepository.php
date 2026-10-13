<?php
/**
 * Access to the JetEngine relation tables.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Repository;

use wpdb;

/**
 * Reads the two JetEngine relations the export needs.
 *
 * - **131** "Relación Empresa Agentes", `mix::users` → `mix::users`, own table `wp_jet_rel_131`:
 *   the parent is the company, the child the associate. No child has two parents (verified in
 *   Task 1), so resolving a creator's company is unambiguous.
 * - **171** "Propiedad Leads", `posts::cccbr-propiedades` → `posts::leads-cccbr`, own table
 *   `wp_jet_rel_171`: used **only** to count leads. No lead content is ever read.
 *
 * Both queries are bulk — one per page, never one per property. JetEngine offers no API for these
 * tables, which is the exception `CLAUDE.md` §4.2 allows. Each table's existence is probed once per
 * request so a deactivated JetEngine degrades to "no company, zero leads" instead of letting a
 * database error leak into the JSON response. `rel_id` is matched explicitly even on a dedicated
 * table, because the column exists and is populated there.
 */
final class RelationsRepository {

	/**
	 * Relation whose parent is a company and whose children are its associates.
	 */
	public const COMPANY_RELATION_ID = 131;

	/**
	 * Relation whose parent is a property and whose children are its leads.
	 */
	public const LEADS_RELATION_ID = 171;

	/**
	 * WordPress database handle.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Memoized table-existence probes, keyed by table name.
	 *
	 * @var array<string, bool>
	 */
	private array $tables = array();

	/**
	 * Constructor.
	 *
	 * @param wpdb $wpdb WordPress database handle.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Resolves the company of each given associate.
	 *
	 * @param array $child_ids Associate user IDs.
	 * @return array<int, int> Associate user ID => company user ID.
	 *
	 * @phpstan-param list<int> $child_ids
	 */
	public function company_parents( array $child_ids ): array {
		$rows = $this->query(
			self::COMPANY_RELATION_ID,
			'SELECT child_object_id AS subject, parent_object_id AS related FROM %i
				WHERE rel_id = %s AND child_object_id IN ( {ids} )',
			$child_ids
		);

		$parents = array();

		foreach ( $rows as $row ) {
			$child  = (int) ( $row['subject'] ?? 0 );
			$parent = (int) ( $row['related'] ?? 0 );

			if ( $child > 0 && $parent > 0 && ! isset( $parents[ $child ] ) ) {
				$parents[ $child ] = $parent;
			}
		}

		return $parents;
	}

	/**
	 * Counts the leads attached to each given property.
	 *
	 * @param array $post_ids Property IDs.
	 * @return array<int, int> Property ID => lead count. Properties with no lead are absent.
	 *
	 * @phpstan-param list<int> $post_ids
	 */
	public function lead_counts( array $post_ids ): array {
		$rows = $this->query(
			self::LEADS_RELATION_ID,
			'SELECT parent_object_id AS subject, COUNT(*) AS related FROM %i
				WHERE rel_id = %s AND parent_object_id IN ( {ids} )
				GROUP BY parent_object_id',
			$post_ids
		);

		$counts = array();

		foreach ( $rows as $row ) {
			$id = (int) ( $row['subject'] ?? 0 );

			if ( $id > 0 ) {
				$counts[ $id ] = (int) ( $row['related'] ?? 0 );
			}
		}

		return $counts;
	}

	/**
	 * Runs one bulk statement against a relation table.
	 *
	 * The only runtime-built part of the SQL is the `{ids}` marker, replaced by a run of `%d`
	 * derived from the **count** of IDs — never from their values. The table name is bound with the
	 * `%i` identifier placeholder (WordPress 6.2+) and every value, IDs included, is bound by
	 * `prepare()`. This is the one place in the class that assembles SQL text, which is why the
	 * sniff and PHPStan exemptions live here only.
	 *
	 * @param int    $relation_id Relation ID, which is also the table suffix.
	 * @param string $template    SQL containing `%i`, `%s` for the relation id, and the `{ids}` marker.
	 * @param array  $ids         IDs to filter by.
	 * @return list<array<string, mixed>>
	 *
	 * @phpstan-param list<int> $ids
	 */
	private function query( int $relation_id, string $template, array $ids ): array {
		$table = $this->table( $relation_id );

		if ( null === $table || array() === $ids ) {
			return array();
		}

		$ids    = array_values( array_map( 'intval', $ids ) );
		$sql    = str_replace( '{ids}', implode( ', ', array_fill( 0, count( $ids ), '%d' ) ), $template );
		$values = $ids;

		array_unshift( $values, $table, (string) $relation_id );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See the docblock: the only runtime-built part is a run of %d derived from a count, and relation rows must be read live for the reconciliation in Task 11 to mean anything.
		// @phpstan-ignore-next-line -- prepare() wants a literal-string, which a runtime-length placeholder list cannot be.
		$prepared = $this->wpdb->prepare( $sql, $values );

		$rows = is_string( $prepared ) ? $this->wpdb->get_results( $prepared, ARRAY_A ) : null;
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$result = array();

		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				$result[] = $row;
			}
		}

		return $result;
	}

	/**
	 * Returns the relation's table name, or null when it does not exist.
	 *
	 * @param int $relation_id Relation ID.
	 * @return string|null
	 */
	private function table( int $relation_id ): ?string {
		$table = $this->wpdb->prefix . 'jet_rel_' . $relation_id;

		if ( ! isset( $this->tables[ $table ] ) ) {
			$sql = $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence probe for a third-party table, prepared above and run once per request.
			$found = is_string( $sql ) ? $this->wpdb->get_var( $sql ) : null;

			$this->tables[ $table ] = ( $found === $table );
		}

		return $this->tables[ $table ] ? $table : null;
	}
}
