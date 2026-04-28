<?php

declare( strict_types=1 );

namespace GeorgeStephanis\AiProviderForVllm\Tests\Integration\Provider;

use GeorgeStephanis\AiProviderForVllm\Metadata\VllmModelMetadataDirectory;
use GeorgeStephanis\AiProviderForVllm\Models\VllmTextGenerationModel;
use GeorgeStephanis\AiProviderForVllm\Provider\VllmProvider;
use PHPUnit\Framework\TestCase;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\AbstractProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;

/**
 * Tests for VllmProvider.
 *
 * @covers \GeorgeStephanis\AiProviderForVllm\Provider\VllmProvider
 */
class VllmProviderTest extends TestCase {

	/**
	 * The original VLLM_HOST value before each test.
	 *
	 * @var string|false
	 */
	private $original_vllm_host;

	protected function setUp(): void {
		parent::setUp();
		$this->original_vllm_host = getenv( 'VLLM_HOST' );
		$this->clear_provider_caches();
	}

	protected function tearDown(): void {
		$this->restore_vllm_host();
		$this->clear_provider_caches();
		parent::tearDown();
	}

	/**
	 * Clears all static caches on AbstractProvider to ensure test isolation.
	 */
	private function clear_provider_caches(): void {
		$reflection = new \ReflectionClass( AbstractProvider::class );
		foreach ( array( 'metadataCache', 'availabilityCache', 'modelMetadataDirectoryCache' ) as $prop_name ) {
			$prop = $reflection->getProperty( $prop_name );
			$prop->setAccessible( true );
			$prop->setValue( null, array() );
		}
	}

	/**
	 * Restores VLLM_HOST to its pre-test value.
	 */
	private function restore_vllm_host(): void {
		if ( false === $this->original_vllm_host ) {
			putenv( 'VLLM_HOST' );
		} else {
			putenv( 'VLLM_HOST=' . $this->original_vllm_host );
		}
	}

	// -----------------------------------------------------------------------
	// url() / baseUrl() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that url() falls back to localhost when VLLM_HOST is not set.
	 */
	public function test_url_falls_back_to_localhost_when_env_var_not_set(): void {
		putenv( 'VLLM_HOST' ); // Remove env var entirely
		$url = VllmProvider::url( '' );
		$this->assertStringStartsWith( 'http://localhost:8000', $url );
	}

	/**
	 * Tests that url() uses the VLLM_HOST environment variable when set.
	 */
	public function test_url_uses_vllm_host_env_var(): void {
		putenv( 'VLLM_HOST=http://my-server:8000' );
		$url = VllmProvider::url( '' );
		$this->assertStringStartsWith( 'http://my-server:8000', $url );
	}

	/**
	 * Tests that url() strips a trailing slash from the VLLM_HOST env var.
	 */
	public function test_url_strips_trailing_slash_from_env_var(): void {
		putenv( 'VLLM_HOST=http://my-server:8000/' );
		$url = VllmProvider::url( 'path' );
		$this->assertSame( 'http://my-server:8000/path', $url );
	}

	/**
	 * Tests that url() falls back to localhost when VLLM_HOST is an empty string.
	 */
	public function test_url_falls_back_when_env_var_is_empty_string(): void {
		putenv( 'VLLM_HOST=' );
		$url = VllmProvider::url( '' );
		$this->assertStringStartsWith( 'http://localhost:8000', $url );
	}

	// -----------------------------------------------------------------------
	// metadata() tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that the provider metadata has the correct provider ID.
	 */
	public function test_metadata_has_correct_provider_id(): void {
		$metadata = VllmProvider::metadata();
		$this->assertSame( 'vllm', $metadata->getId() );
	}

	/**
	 * Tests that the provider metadata has the correct display name.
	 */
	public function test_metadata_has_correct_name(): void {
		$metadata = VllmProvider::metadata();
		$this->assertSame( 'vLLM', $metadata->getName() );
	}

	/**
	 * Tests that the provider metadata specifies API key as the authentication method.
	 */
	public function test_metadata_auth_method_is_api_key(): void {
		$metadata     = VllmProvider::metadata();
		$auth_method  = $metadata->getAuthenticationMethod();
		$this->assertNotNull( $auth_method );
		$this->assertTrue( $auth_method->isApiKey() );
	}

	// -----------------------------------------------------------------------
	// Factory method tests
	// -----------------------------------------------------------------------

	/**
	 * Tests that availability() returns a ListModelsApiBasedProviderAvailability instance.
	 */
	public function test_availability_returns_list_models_api_based_provider_availability(): void {
		$availability = VllmProvider::availability();
		$this->assertInstanceOf( ListModelsApiBasedProviderAvailability::class, $availability );
	}

	/**
	 * Tests that modelMetadataDirectory() returns a VllmModelMetadataDirectory instance.
	 */
	public function test_model_metadata_directory_returns_correct_type(): void {
		$directory = VllmProvider::modelMetadataDirectory();
		$this->assertInstanceOf( VllmModelMetadataDirectory::class, $directory );
	}

	// -----------------------------------------------------------------------
	// createModel() tests
	// -----------------------------------------------------------------------

	/**
	 * Invokes the protected static createModel() method via reflection.
	 *
	 * @param ModelMetadata $model_metadata
	 * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface
	 */
	private function invoke_create_model( ModelMetadata $model_metadata ): \WordPress\AiClient\Providers\Models\Contracts\ModelInterface {
		$method = new \ReflectionMethod( VllmProvider::class, 'createModel' );
		$method->setAccessible( true );
		return $method->invoke( null, $model_metadata, VllmProvider::metadata() );
	}

	/**
	 * Tests that createModel() returns a VllmTextGenerationModel for a model with textGeneration capability.
	 */
	public function test_create_model_returns_text_generation_model_for_text_generation_capability(): void {
		$model_metadata = new ModelMetadata(
			'qwen3-14b',
			'Qwen3 14B',
			array( CapabilityEnum::textGeneration() ),
			array()
		);

		$model = $this->invoke_create_model( $model_metadata );

		$this->assertInstanceOf( VllmTextGenerationModel::class, $model );
	}

	/**
	 * Tests that createModel() throws a RuntimeException for unsupported capabilities.
	 */
	public function test_create_model_throws_for_unsupported_capabilities(): void {
		$model_metadata = new ModelMetadata(
			'embed-model',
			'Embed Model',
			array( CapabilityEnum::chatHistory() ),
			array()
		);

		$this->expectException( RuntimeException::class );
		$this->invoke_create_model( $model_metadata );
	}
}
