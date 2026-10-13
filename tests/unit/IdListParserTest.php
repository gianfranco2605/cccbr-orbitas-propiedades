<?php
/**
 * Unit tests for the attachment ID parser.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Tests;

use ElPuas\CCCBR\OrbitasPropiedades\Support\IdListParser;
use PHPUnit\Framework\TestCase;

/**
 * Every shape below is one the `imagenes` column actually holds (`docs/schema.md` §6).
 */
final class IdListParserTest extends TestCase {

	/**
	 * Parser under test.
	 *
	 * @var IdListParser
	 */
	private IdListParser $parser;

	/**
	 * Builds the parser.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->parser = new IdListParser();
	}

	/**
	 * A serialized array of ID strings, as the meta cache hands it over.
	 *
	 * @return void
	 */
	public function test_array_of_id_strings(): void {
		$result = $this->parser->parse( array( '101', '102', '103' ) );

		$this->assertSame( array( 101, 102, 103 ), $result['ids'] );
		$this->assertSame( array(), $result['invalid'] );
	}

	/**
	 * A comma-separated list, the shape 899 properties use.
	 *
	 * @return void
	 */
	public function test_comma_separated_list(): void {
		$result = $this->parser->parse( '101,102 , 103' );

		$this->assertSame( array( 101, 102, 103 ), $result['ids'] );
		$this->assertSame( array(), $result['invalid'] );
	}

	/**
	 * A single ID, as a string and as a native integer.
	 *
	 * @return void
	 */
	public function test_single_id(): void {
		$this->assertSame( array( 90210 ), $this->parser->parse( '90210' )['ids'] );
		$this->assertSame( array( 90210 ), $this->parser->parse( 90210 )['ids'] );
	}

	/**
	 * Gallery order is the display order and must survive untouched.
	 *
	 * @return void
	 */
	public function test_order_is_preserved(): void {
		$this->assertSame( array( 300, 100, 200 ), $this->parser->parse( '300,100,200' )['ids'] );
	}

	/**
	 * A repeated ID is listed once.
	 *
	 * @return void
	 */
	public function test_duplicates_are_collapsed_on_first_occurrence(): void {
		$this->assertSame( array( 100, 200 ), $this->parser->parse( '100,200,100' )['ids'] );
	}

	/**
	 * Every shape of "nothing", including the NULL column.
	 *
	 * @return void
	 */
	public function test_empty_shapes(): void {
		foreach ( array( null, '', '   ', array(), array( '' ), false ) as $raw ) {
			$result = $this->parser->parse( $raw );

			$this->assertSame( array(), $result['ids'] );
			$this->assertSame( array(), $result['invalid'] );
		}
	}

	/**
	 * A zero ID is JetEngine's "nothing selected", not a broken reference.
	 *
	 * @return void
	 */
	public function test_zero_is_not_a_broken_reference(): void {
		$result = $this->parser->parse( '0' );

		$this->assertSame( array(), $result['ids'] );
		$this->assertSame( array(), $result['invalid'], 'Zero must not be reported as an unparsable token.' );
	}

	/**
	 * A token that is not an ID is reported, never silently dropped.
	 *
	 * @return void
	 */
	public function test_non_id_tokens_are_reported(): void {
		$result = $this->parser->parse( '101,https://example.org/a.jpg,102' );

		$this->assertSame( array( 101, 102 ), $result['ids'] );
		$this->assertSame( array( 'https://example.org/a.jpg' ), $result['invalid'] );
	}

	/**
	 * Negative and decimal tokens are not IDs.
	 *
	 * @return void
	 */
	public function test_negative_and_decimal_tokens_are_invalid(): void {
		$result = $this->parser->parse( array( '-5', '1.5', '7' ) );

		$this->assertSame( array( 7 ), $result['ids'] );
		$this->assertSame( array( '-5', '1.5' ), $result['invalid'] );
	}

	/**
	 * JetEngine's `both` format carries the ID next to a URL.
	 *
	 * @return void
	 */
	public function test_first_id_reads_the_both_format(): void {
		$value = array(
			'id'  => '4242',
			'url' => 'https://example.org/a.jpg',
		);

		$this->assertSame( 4242, $this->parser->first_id( $value ) );
	}

	/**
	 * `first_id()` on the other shapes.
	 *
	 * @return void
	 */
	public function test_first_id_on_plain_values(): void {
		$this->assertSame( 101, $this->parser->first_id( '101,102' ) );
		$this->assertSame( 101, $this->parser->first_id( array( '101', '102' ) ) );
		$this->assertNull( $this->parser->first_id( null ) );
		$this->assertNull( $this->parser->first_id( '' ) );
		$this->assertNull( $this->parser->first_id( 'not-an-id' ) );
	}

	/**
	 * A nested array, which a serialized gallery can produce.
	 *
	 * @return void
	 */
	public function test_nested_arrays_are_flattened(): void {
		$result = $this->parser->parse( array( array( '101', '102' ), '103' ) );

		$this->assertSame( array( 101, 102, 103 ), $result['ids'] );
	}
}
