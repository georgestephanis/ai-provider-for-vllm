<?php
/**
 * vLLM request options trait.
 *
 * @package GeorgeStephanis\AiProviderForVllm\Models\Traits
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace GeorgeStephanis\AiProviderForVllm\Models\Traits;

use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

/**
 * Trait for preparing request options with configurable timeout defaults.
 *
 * @since 1.0.0
 */
trait VllmRequestOptionsTrait {

	/**
	 * Prepares request options with timeout defaults and custom overrides.
	 *
	 * Supported custom options:
	 *  - vllm.request_timeout (seconds)
	 *  - vllm.connect_timeout (seconds)
	 *
	 * @since 1.0.0
	 *
	 * @param float $default_request_timeout Default request timeout in seconds.
	 * @param float $default_connect_timeout Default connect timeout in seconds.
	 * @return \WordPress\AiClient\Providers\Http\DTO\RequestOptions Prepared request options.
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	protected function prepareRequestOptions(
		float $default_request_timeout,
		float $default_connect_timeout
	): RequestOptions {
		$existing_options = $this->getRequestOptions();
		if ( null !== $existing_options ) {
			$request_options = RequestOptions::fromArray( $existing_options->toArray() );
		} else {
			$request_options = new RequestOptions();
		}

		$custom_options = $this->getConfig()->getCustomOptions();

		$request_timeout = $default_request_timeout;
		if ( isset( $custom_options['vllm.request_timeout'] ) && is_numeric( $custom_options['vllm.request_timeout'] ) ) {
			$request_timeout = (float) $custom_options['vllm.request_timeout'];
		}

		$connect_timeout = $default_connect_timeout;
		if ( isset( $custom_options['vllm.connect_timeout'] ) && is_numeric( $custom_options['vllm.connect_timeout'] ) ) {
			$connect_timeout = (float) $custom_options['vllm.connect_timeout'];
		}

		$request_options->setTimeout( $request_timeout );

		if ( null === $request_options->getConnectTimeout() ) {
			$request_options->setConnectTimeout( $connect_timeout );
		}

		return $request_options;
	}
}
