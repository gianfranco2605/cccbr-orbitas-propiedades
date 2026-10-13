<?php
/**
 * Per-property data-quality issue list.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Support;

/**
 * Collects the issues found while mapping one property.
 *
 * One instance per property, created by the mapper and thrown away with it — the mutability is
 * local to a single mapping and never shared.
 */
final class IssueCollector {

	/**
	 * Collected issues.
	 *
	 * @var list<array{code: string, field: string|null, detail: string|null}>
	 */
	private array $issues = array();

	/**
	 * Records an issue.
	 *
	 * Identical triples are recorded once, so a repeated token cannot inflate the list.
	 *
	 * @param string      $code   One of the {@see IssueCode} constants.
	 * @param string|null $field  Contract field the issue belongs to.
	 * @param string|null $detail Short machine-oriented detail: an ID or a raw token, never a
	 *                            sentence and never personal data.
	 * @return void
	 */
	public function add( string $code, ?string $field = null, ?string $detail = null ): void {
		$issue = array(
			'code'   => $code,
			'field'  => $field,
			'detail' => $detail,
		);

		if ( in_array( $issue, $this->issues, true ) ) {
			return;
		}

		$this->issues[] = $issue;
	}

	/**
	 * Every recorded issue, in the order it was found.
	 *
	 * @return list<array{code: string, field: string|null, detail: string|null}>
	 */
	public function all(): array {
		return $this->issues;
	}

	/**
	 * Whether anything was recorded.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->issues;
	}
}
