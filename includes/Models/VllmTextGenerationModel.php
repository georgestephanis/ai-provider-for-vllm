<?php

declare( strict_types=1 );

namespace GeorgeStephanis\AiProviderForVllm\Models;

use GeorgeStephanis\AiProviderForVllm\Models\Traits\VllmRequestOptionsTrait;
use GeorgeStephanis\AiProviderForVllm\Provider\VllmProvider;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Class for a vLLM text generation model using the OpenAI-compatible chat completions API.
 *
 * @since 1.0.0
 */
class VllmTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {
	use VllmRequestOptionsTrait;

	/**
	 * Prepares the response format parameter for vLLM's OpenAI-compatible API.
	 *
	 * vLLM's OpenAI-compatible API uses the same response_format key as OpenAI,
	 * with the schema nested at json_schema.schema for structured output.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $output_schema The output schema.
	 * @return array<string, mixed> The prepared response format parameter.
	 */
	protected function prepareResponseFormatParam( ?array $output_schema ): array {
		if ( is_array( $output_schema ) ) {
			return array(
				'type'        => 'json_schema',
				'json_schema' => array(
					'name'   => 'response_schema',
					'schema' => $output_schema,
				),
			);
		}

		return array(
			'type' => 'json_object',
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function createRequest(
		HttpMethodEnum $method,
		string $path,
		array $headers = array(),
		$data = null
	): Request {
		$request_options = $this->prepareRequestOptionsForTextGeneration();

		// Keep transport-only timeout options out of the OpenAI-compatible payload.
		if ( is_array( $data ) ) {
			unset( $data['vllm.request_timeout'], $data['vllm.connect_timeout'] );
		}

		// Ensure the path uses the /v1/ prefix for OpenAI-compatible endpoints.
		$path = ltrim( (string) preg_replace( '#^v1/?#', '', ltrim( $path, '/' ) ), '/' );
		$path = '/v1/' . $path;

		return new Request(
			$method,
			VllmProvider::url( $path ),
			$headers,
			$data,
			$request_options
		);
	}

	/**
	 * Prepares request options for text generation with a longer default timeout.
	 *
	 * Supported custom options:
	 *  - vllm.request_timeout (seconds)
	 *  - vllm.connect_timeout (seconds)
	 *
	 * @since 1.0.0
	 *
	 * @return \WordPress\AiClient\Providers\Http\DTO\RequestOptions Prepared request options.
	 */
	private function prepareRequestOptionsForTextGeneration(): RequestOptions {
		return $this->prepareRequestOptions( 60.0, 10.0 );
	}
}
