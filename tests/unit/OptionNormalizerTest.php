<?php
/**
 * Unit tests for the tolerant option normalizer.
 *
 * @package ElPuas\CCCBR\OrbitasPropiedades
 */

declare(strict_types=1);

namespace ElPuas\CCCBR\OrbitasPropiedades\Tests;

use ElPuas\CCCBR\OrbitasPropiedades\Support\OptionNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * Every case below is a storage shape measured in the real database (`docs/schema.md` §2.2–2.3).
 */
final class OptionNormalizerTest extends TestCase {

	/**
	 * Normalizer under test.
	 *
	 * @var OptionNormalizer
	 */
	private OptionNormalizer $normalizer;

	/**
	 * Builds the normalizer.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->normalizer = new OptionNormalizer();
	}

	/**
	 * The real `uso-de-suelo` option set: keys differ from labels.
	 *
	 * @return array<string, string>
	 */
	private function land_use_options(): array {
		return array(
			'usoresidencial' => 'Residencial',
			'usocomercial'   => 'Comercial',
			'usoindustrial'  => 'Industrial',
			'usoagricola'    => 'Agrícola',
		);
	}

	/**
	 * The real glossary 15 option set: one entry whose key is not its label.
	 *
	 * @return array<string, string>
	 */
	private function operation_options(): array {
		return array(
			'Alquiler'       => 'Alquiler',
			'Venta'          => 'Venta',
			'opciondecompra' => 'Alquiler con opción de compra',
		);
	}

	/**
	 * A serialized array, already unserialized by the meta cache.
	 *
	 * @return void
	 */
	public function test_array_of_keys(): void {
		$result = $this->normalizer->normalize( array( 'usoresidencial', 'usocomercial' ), $this->land_use_options() );

		$this->assertSame(
			array(
				array(
					'key'   => 'usoresidencial',
					'label' => 'Residencial',
					'exact' => true,
				),
				array(
					'key'   => 'usocomercial',
					'label' => 'Comercial',
					'exact' => true,
				),
			),
			$result
		);
	}

	/**
	 * A plain scalar holding a label rather than a key.
	 *
	 * @return void
	 */
	public function test_scalar_label_resolves_to_its_key(): void {
		$result = $this->normalizer->normalize( 'Residencial', $this->land_use_options() );

		$this->assertSame( 'usoresidencial', $result[0]['key'] );
		$this->assertSame( 'Residencial', $result[0]['label'] );
		$this->assertTrue( $result[0]['exact'] );
	}

	/**
	 * A single value holding several selections joined by pipes.
	 *
	 * @return void
	 */
	public function test_pipe_joined_string_is_split(): void {
		$result = $this->normalizer->normalize( 'Residencial | Comercial | agrícola', $this->land_use_options() );

		$this->assertCount( 3, $result );
		$this->assertSame( array( 'usoresidencial', 'usocomercial', 'usoagricola' ), array_column( $result, 'key' ) );
		$this->assertSame( array( true, true, false ), array_column( $result, 'exact' ), 'The lowercased accent form matches only after folding.' );
	}

	/**
	 * A pipe-joined string nested inside a serialized array.
	 *
	 * @return void
	 */
	public function test_pipe_joined_string_inside_an_array(): void {
		$result = $this->normalizer->normalize( array( 'Residencial | agrícola' ), $this->land_use_options() );

		$this->assertSame( array( 'usoresidencial', 'usoagricola' ), array_column( $result, 'key' ) );
	}

	/**
	 * Literal booleans written by the form builder carry no selection.
	 *
	 * @return void
	 */
	public function test_literal_false_tokens_are_dropped(): void {
		$this->assertSame( array(), $this->normalizer->normalize( 'false | false | false', $this->land_use_options() ) );
		$this->assertSame( array(), $this->normalizer->normalize( array( 'compartir_propiedad' => 'false' ), array( 'compartir_propiedad' => 'Compartir Propiedad' ) ) );
	}

	/**
	 * Select placeholders carry no selection either.
	 *
	 * @return void
	 */
	public function test_placeholder_tokens_are_dropped(): void {
		$this->assertSame( array(), $this->normalizer->normalize( '-1', $this->operation_options() ) );
		$this->assertSame( array(), $this->normalizer->normalize( array( '-' ), $this->land_use_options() ) );
	}

	/**
	 * Every shape of "nothing" yields an empty list.
	 *
	 * @return void
	 */
	public function test_empty_shapes(): void {
		$options = $this->land_use_options();

		$this->assertSame( array(), $this->normalizer->normalize( '', $options ) );
		$this->assertSame( array(), $this->normalizer->normalize( null, $options ), 'A NULL meta column must not crash or invent a value.' );
		$this->assertSame( array(), $this->normalizer->normalize( array(), $options ) );
		$this->assertSame( array(), $this->normalizer->normalize( array( '' ), $options ) );
	}

	/**
	 * A glossary key whose label differs resolves to the label, not to the key.
	 *
	 * @return void
	 */
	public function test_glossary_key_resolves_to_a_different_label(): void {
		$result = $this->normalizer->normalize( array( 'Venta', 'opciondecompra' ), $this->operation_options() );

		$this->assertSame( 'Venta', $result[0]['label'] );
		$this->assertSame( 'opciondecompra', $result[1]['key'] );
		$this->assertSame( 'Alquiler con opción de compra', $result[1]['label'] );
	}

	/**
	 * The slug spelling the JetFormBuilder submissions use.
	 *
	 * @return void
	 */
	public function test_slug_spelling_matches_after_folding(): void {
		$options = array(
			'Local comercial' => 'Local comercial',
			'Proyecto'        => 'Proyecto',
			'Finca agrícola'  => 'Finca agrícola',
		);

		$local = $this->normalizer->normalize( 'local_comercial', $options );
		$this->assertSame( 'Local comercial', $local[0]['label'] );
		$this->assertFalse( $local[0]['exact'] );

		$project = $this->normalizer->normalize( 'proyecto', $options );
		$this->assertSame( 'Proyecto', $project[0]['label'] );

		$farm = $this->normalizer->normalize( 'finca agrícola', $options );
		$this->assertSame( 'Finca agrícola', $farm[0]['label'] );
	}

	/**
	 * A token with no counterpart is preserved verbatim with a null label.
	 *
	 * @return void
	 */
	public function test_unmapped_token_is_preserved_and_never_guessed(): void {
		$result = $this->normalizer->normalize( 'lote_residencial_/_comercial', array( 'Lote residencial' => 'Lote residencial' ) );

		$this->assertCount( 1, $result );
		$this->assertSame( 'lote_residencial_/_comercial', $result[0]['key'], 'The raw token must survive.' );
		$this->assertNull( $result[0]['label'], 'No label may be invented.' );
	}

	/**
	 * A variant spelling that is in neither the key nor the label stays unmapped.
	 *
	 * @return void
	 */
	public function test_unknown_operation_variant_stays_unmapped(): void {
		$result = $this->normalizer->normalize( 'alquiler_con_opción_de_compra', $this->operation_options() );

		$this->assertSame( 'opciondecompra', $result[0]['key'], 'It folds to the same slug as the label "Alquiler con opción de compra".' );
		$this->assertFalse( $result[0]['exact'] );
	}

	/**
	 * Duplicate selections are collapsed.
	 *
	 * @return void
	 */
	public function test_duplicates_are_collapsed(): void {
		$result = $this->normalizer->normalize(
			array( 'usoresidencial', 'Residencial', 'usoresidencial' ),
			$this->land_use_options()
		);

		$this->assertCount( 1, $result );
		$this->assertSame( 'usoresidencial', $result[0]['key'] );
	}

	/**
	 * With no option set at all, tokens are returned raw instead of being dropped.
	 *
	 * @return void
	 */
	public function test_without_an_option_set_tokens_survive_unmapped(): void {
		$result = $this->normalizer->normalize( 'Piscina | Gimnasio', array() );

		$this->assertSame( array( 'Piscina', 'Gimnasio' ), array_column( $result, 'key' ) );
		$this->assertSame( array( null, null ), array_column( $result, 'label' ) );
	}
}
