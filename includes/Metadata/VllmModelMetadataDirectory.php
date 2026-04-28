<?php

declare( strict_types=1 );

namespace Fueled\AiProviderForVllm\Metadata;

use Fueled\AiProviderForVllm\Provider\VllmProvider;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;

/**
 * Class for the vLLM model metadata directory.
 *
 * Uses the OpenAI-compatible /v1/models endpoint to list available models.
 *
 * @since 1.0.0
 *
 * @phpstan-type ModelsResponseData array{
 *     object: string,
 *     data: list<array{id: string, object: string, created?: int, owned_by?: string}>
 * }
 */
class VllmModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function sendListModelsRequest(): array {
		$request  = $this->createRequest( HttpMethodEnum::GET(), 'v1/models' );
		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		/** @var ModelsResponseData $models_data */
		$models_data = $response->getData();
		if ( ! isset( $models_data['data'] ) ) {
			throw ResponseException::fromMissingData( 'vLLM', 'data' );
		}

		$models_map = array();
		foreach ( $models_data['data'] as $model_entry ) {
			$model_id = $model_entry['id'];
			if ( ! is_string( $model_id ) || '' === $model_id ) {
				continue;
			}

			$metadata = $this->buildModelMetadata( $model_id );
			if ( null === $metadata ) {
				continue;
			}

			$models_map[ $model_id ] = $metadata;
		}

		ksort( $models_map );

		return $models_map;
	}

	/**
	 * Builds a ModelMetadata object for a vLLM model.
	 *
	 * All vLLM models are treated as text generation models with full chat capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id The model identifier.
	 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata|null The model metadata, or null if the model should be excluded.
	 */
	private function buildModelMetadata( string $model_id ): ?ModelMetadata {
		$input_modalities_option = new SupportedOption(
			OptionEnum::inputModalities(),
			array( array( ModalityEnum::text() ) )
		);

		$options = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::candidateCount() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::frequencyPenalty() ),
			new SupportedOption( OptionEnum::presencePenalty() ),
			new SupportedOption( OptionEnum::outputMimeType(), array( 'text/plain', 'application/json' ) ),
			new SupportedOption( OptionEnum::outputSchema() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
			$input_modalities_option,
		);

		return new ModelMetadata(
			$model_id,
			$model_id,
			array(
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			),
			$options
		);
	}

	/**
	 * Creates a request object for the vLLM API.
	 *
	 * @since 1.0.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum                     $method  The HTTP method.
	 * @param string                             $path    The API endpoint path, relative to the base URI.
	 * @param array<string, string|list<string>> $headers The request headers.
	 * @param string|array<string, mixed>|null   $data    The request data.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Request The request object.
	 */
	private function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request(
			$method,
			VllmProvider::url( $path ),
			$headers,
			$data
		);
	}
}
