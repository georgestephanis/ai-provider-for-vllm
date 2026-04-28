<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForVllm\Provider;

use Fueled\AiProviderForVllm\Metadata\VllmModelMetadataDirectory;
use Fueled\AiProviderForVllm\Models\VllmTextGenerationModel;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the vLLM provider.
 *
 * @since 1.0.0
 */
class VllmProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		$host = getenv( 'VLLM_HOST' );
		if ( false !== $host && '' !== $host ) {
			return rtrim( $host, '/' );
		}

		return 'http://localhost:8000';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModel(
		ModelMetadata $model_metadata,
		ProviderMetadata $provider_metadata
	): ModelInterface {

		$capabilities = $model_metadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new VllmTextGenerationModel( $model_metadata, $provider_metadata );
			}
		}

		throw new RuntimeException(
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message, not output.
			'Unsupported model capabilities: ' . implode( ', ', $capabilities )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$provider_meta = array(
			'vllm',
			'vLLM',
			ProviderTypeEnum::cloud(),
			'https://docs.vllm.ai/',
			RequestAuthenticationMethod::apiKey(),
		);

		// Provider description support was added in 1.2.0.
		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			if ( function_exists( '__' ) ) {
				$provider_meta[] = __( 'Text generation with vLLM, a fast and easy-to-use LLM inference and serving engine.', 'ai-provider-for-vllm' );
			} else {
				$provider_meta[] = 'Text generation with vLLM, a fast and easy-to-use LLM inference and serving engine.';
			}
		}

		// Provider logo path support was added in 1.3.0.
		if ( version_compare( AiClient::VERSION, '1.3.0', '>=' ) ) {
			$logo = __DIR__ . '/logo.svg';
			if ( file_exists( $logo ) ) {
				$provider_meta[] = $logo;
			}
		}

		return new ProviderMetadata( ...$provider_meta );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		// Check valid API access by attempting to list models.
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new VllmModelMetadataDirectory();
	}
}
