<?php
/**
 * Unit tests for the conservative number parser.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Tests;

use ElPuas\CCCBR\OrbitasPropiedades\Support\NumberParser;
use PHPUnit\Framework\TestCase;

/**
 * The parser must never guess: a wrong price is worse than no price.
 */
final class NumberParserTest extends TestCase {

	/**
	 * Parser under test.
	 *
	 * @var NumberParser
	 */
	private NumberParser $parser;

	/**
	 * Builds the parser.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->parser = new NumberParser();
	}

	/**
	 * Values that have exactly one possible reading.
	 *
	 * @return array<string, array{0: mixed, 1: int|float}>
	 */
	public function provide_exact_values(): array {
		return array(
			'plain integer'       => array( '185000', 185000 ),
			'zero'                => array( '0', 0 ),
			'negative integer'    => array( '-5', -5 ),
			'one decimal'         => array( '1250.5', 1250.5 ),
			'two decimals'        => array( '1250.50', 1250.5 ),
			'integer with spaces' => array( '  42  ', 42 ),
			'native int'          => array( 7, 7 ),
			'native float'        => array( 7.5, 7.5 ),
		);
	}

	/**
	 * Exact values are parsed and keep their raw form.
	 *
	 * @dataProvider provide_exact_values
	 *
	 * @param mixed     $raw      Stored value.
	 * @param int|float $expected Expected parsed value.
	 * @return void
	 */
	public function test_exact_values_are_parsed( $raw, $expected ): void {
		$result = $this->parser->parse( $raw );

		$this->assertSame( NumberParser::STATE_EXACT, $result['state'] );
		$this->assertEquals( $expected, $result['value'] );
		$this->assertSame( trim( (string) $raw ), $result['raw'] );
	}

	/**
	 * Values whose separator convention cannot be determined.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function provide_ambiguous_values(): array {
		return array(
			'dot thousands'          => array( '150.000' ),
			'comma thousands'        => array( '150,000' ),
			'decimal comma'          => array( '1,5' ),
			'mixed separators'       => array( '1.250,75' ),
			'repeated dot thousands' => array( '1.250.000' ),
			'four fraction digits'   => array( '12.3456' ),
		);
	}

	/**
	 * Ambiguous values yield no number but keep the raw string.
	 *
	 * @dataProvider provide_ambiguous_values
	 *
	 * @param string $raw Stored value.
	 * @return void
	 */
	public function test_ambiguous_values_are_not_guessed( string $raw ): void {
		$result = $this->parser->parse( $raw );

		$this->assertSame( NumberParser::STATE_AMBIGUOUS, $result['state'], $raw );
		$this->assertNull( $result['value'], $raw );
		$this->assertSame( $raw, $result['raw'] );
	}

	/**
	 * Values that are not numbers at all.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_unparsable_values(): array {
		return array(
			'number with unit' => array( '180 m2' ),
			'currency symbol'  => array( '$120000' ),
			'range'            => array( '2 a 3' ),
			'words'            => array( 'consultar' ),
			'dash only'        => array( '-' ),
			'leading dot'      => array( '.5' ),
			'thousands and m2' => array( '1.250 m2' ),
		);
	}

	/**
	 * Unparsable values yield no number but keep the raw string.
	 *
	 * @dataProvider provide_unparsable_values
	 *
	 * @param mixed $raw Stored value.
	 * @return void
	 */
	public function test_unparsable_values_are_reported( $raw ): void {
		$result = $this->parser->parse( $raw );

		$this->assertSame( NumberParser::STATE_UNPARSABLE, $result['state'], (string) $raw );
		$this->assertNull( $result['value'] );
		$this->assertSame( (string) $raw, $result['raw'] );
	}

	/**
	 * Every shape of "nothing" the database can hold.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public function provide_empty_values(): array {
		return array(
			'empty string'            => array( '' ),
			'whitespace only'         => array( '   ' ),
			'null from a NULL column' => array( null ),
			'empty array'             => array( array() ),
			'boolean false'           => array( false ),
		);
	}

	/**
	 * Empty values are reported as empty, never as zero.
	 *
	 * @dataProvider provide_empty_values
	 *
	 * @param mixed $raw Stored value.
	 * @return void
	 */
	public function test_empty_values_are_never_zero( $raw ): void {
		$result = $this->parser->parse( $raw );

		$this->assertSame( NumberParser::STATE_EMPTY, $result['state'] );
		$this->assertNull( $result['value'], 'An empty field must not become 0.' );
		$this->assertSame( '', $result['raw'] );
	}
}
