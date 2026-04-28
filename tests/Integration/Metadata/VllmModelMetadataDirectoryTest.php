<?php

declare( strict_types=1 );

namespace GeorgeStephanis\AiProviderForVllm\Tests\Integration\Metadata;

use GeorgeStephanis\AiProviderForVllm\Metadata\VllmModelMetadataDirectory;
use GeorgeStephanis\AiProviderForVllm\Tests\Integration\Mocks\MockHttpTransporter;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;

/**
 * Tests for VllmModelMetadataDirectory.
 *
 * Uses a MockHttpTransporter with a single queued response matching vLLM's
 * /v1/models response shape.
 *
 * @covers \GeorgeStephanis\AiProviderForVllm\Metadata\VllmModelMetadataDirectory
 */
class VllmModelMetadataDirectoryTest extends TestCase {

	/**
	 * Directory under test.
	 *
	 * @var VllmModelMetadataDirectory
	 */
	private VllmModelMetadataDirectory $directory;

	/**
	 * Shared mock transporter (fresh instance per test).
	 *
	 * @var MockHttpTransporter
	 */
	private MockHttpTransporter $transporter;

	protected function setUp(): void {
		parent::setUp();
		putenv( 'VLLM_HOST=http://localhost:8000' );
		$this->transporter = new MockHttpTransporter();
		$this->directory   = new VllmModelMetadataDirectory();
		$this->directory->setHttpTransporter( $this->transporter );
		$this->directory->setRequestAuthentication( new ApiKeyRequestAuthentication( '' ) );
		$this->directory->invalidateCaches();
	}

	protected function tearDown(): void {
		$this->directory->invalidateCaches();
		putenv( 'VLLM_HOST' );
		parent::tearDown();
	}

	// -----------------------------------------------------------------------
	// Response helpers
	// -----------------------------------------------------------------------

	/**
	 * Builds a fake /v1/models 200 response containing the given model IDs.
	 *
	 * @param list<string> $model_ids The model IDs to include.
	 * @return Response
	 */
	private function make_models_response( array $model_ids ): Response {
		$data = array_map(
			static function ( string $id ): array {
				return array( 'id' => $id, 'object' => 'model' );
			},
			$model_ids
		);
		$body = (string) json_encode( array( 'object' => 'list', 'data' => $data ) );
		return new Response( 200, array(), $body );
	}

	/**
	 * Builds a fake error response.
	 *
	 * @param int $status HTTP status code.
	 * @return Response
	 */
	private function make_error_response( int $status = 500 ): Response {
		return new Response( $status, array(), '{"error":"Internal Server Error"}' );
	}

	// -----------------------------------------------------------------------
	// Basic listing tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that listModelMetadata() returns models parsed from the API response.
	 */
	public function test_returns_models_from_api(): void {
		$this->transporter->set_response_to_return( $this->make_models_response( array( 'qwen3-14b' ) ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'qwen3-14b', $models[0]->getId() );
	}

	/**
	 * Tests that returned models are sorted alphabetically by model ID.
	 */
	public function test_models_are_sorted_alphabetically(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response( array( 'zmodel', 'amodel' ) )
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 2, $models );
		$this->assertSame( 'amodel', $models[0]->getId() );
		$this->assertSame( 'zmodel', $models[1]->getId() );
	}

	/**
	 * Tests that an empty models list returns an empty array.
	 */
	public function test_empty_models_list_returns_empty_array(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response( array() )
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 0, $models );
	}

	/**
	 * Tests that multiple models are all returned.
	 */
	public function test_multiple_models_are_all_returned(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response( array( 'qwen3-14b', 'llama3-8b', 'mistral-7b' ) )
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 3, $models );
	}

	// -----------------------------------------------------------------------
	// Capability tests — all vLLM models are text generation
	// -----------------------------------------------------------------------

	/**
	 * Tests that all vLLM models are returned as text-generation models.
	 */
	public function test_all_models_are_text_generation(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response( array( 'qwen3-14b' ) )
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$capabilities = $models[0]->getSupportedCapabilities();
		$has_text_gen = false;
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				$has_text_gen = true;
				break;
			}
		}
		$this->assertTrue( $has_text_gen, 'Expected text generation capability' );
	}

	/**
	 * Tests that models include standard text-generation supported options.
	 */
	public function test_models_include_text_generation_options(): void {
		$this->transporter->set_response_to_return(
			$this->make_models_response( array( 'qwen3-14b' ) )
		);

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$option_names = array_map(
			static function ( $opt ): string {
				return (string) $opt->getName();
			},
			$models[0]->getSupportedOptions()
		);
		$this->assertContains( 'systemInstruction', $option_names );
		$this->assertContains( 'maxTokens', $option_names );
		$this->assertContains( 'temperature', $option_names );
	}

	// -----------------------------------------------------------------------
	// Error handling tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that a non-2xx response throws a ResponseException.
	 */
	public function test_non_2xx_response_throws_response_exception(): void {
		$this->transporter->set_response_to_return( $this->make_error_response( 500 ) );

		$this->expectException( ResponseException::class );
		$this->directory->listModelMetadata();
	}

	/**
	 * Tests that a 401 response throws a ResponseException.
	 */
	public function test_401_response_throws_response_exception(): void {
		$this->transporter->set_response_to_return( $this->make_error_response( 401 ) );

		$this->expectException( ResponseException::class );
		$this->directory->listModelMetadata();
	}

	/**
	 * Tests that a response without the 'data' key throws a ResponseException.
	 */
	public function test_response_missing_data_key_throws_response_exception(): void {
		$body = (string) json_encode( array( 'object' => 'list' ) );
		$this->transporter->set_response_to_return( new Response( 200, array(), $body ) );

		$this->expectException( ResponseException::class );
		$this->directory->listModelMetadata();
	}

	/**
	 * Tests that model entries with empty or non-string IDs are skipped.
	 */
	public function test_model_entries_with_invalid_ids_are_skipped(): void {
		$body = (string) json_encode(
			array(
				'object' => 'list',
				'data'   => array(
					array( 'id' => 'valid-model', 'object' => 'model' ),
					array( 'id' => '', 'object' => 'model' ),
					array( 'object' => 'model' ), // missing id
				),
			)
		);
		$this->transporter->set_response_to_return( new Response( 200, array(), $body ) );

		$models = $this->directory->listModelMetadata();

		$this->assertCount( 1, $models );
		$this->assertSame( 'valid-model', $models[0]->getId() );
	}
}
