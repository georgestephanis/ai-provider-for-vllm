<?php

/**
 * Plugin initializer class.
 *
 * @since 1.0.0
 */

declare( strict_types=1 );

namespace GeorgeStephanis\AiProviderForVllm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GeorgeStephanis\AiProviderForVllm\Provider\VllmProvider;
use GeorgeStephanis\AiProviderForVllm\Settings\VllmSettings;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Plugin class.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Initializes the plugin.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_provider' ), 5 );
		add_action( 'init', array( $this, 'register_fallback_auth' ), 15 );
		add_action( 'init', array( $this, 'initialize_settings' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_VLLM_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		add_filter( 'http_request_host_is_external', array( $this, 'allow_localhost_requests' ), 10, 3 );
		add_filter( 'http_allowed_safe_ports', array( $this, 'allow_vllm_ports' ) );
	}

	/**
	 * Gets the vLLM host.
	 *
	 * @since 1.0.0
	 *
	 * @return string The vLLM host.
	 */
	private function get_vllm_host(): string {
		// Get the VLLM_HOST environment variable if set.
		$host = getenv( 'VLLM_HOST' );
		if ( false !== $host && '' !== $host ) {
			return $host;
		}

		// Get the vLLM host from the WordPress option if set.
		$settings = VllmSettings::get_settings();
		if ( isset( $settings['host'] ) && '' !== $settings['host'] ) {
			return $settings['host'];
		}

		return 'http://localhost:8000';
	}

	/**
	 * Sets the VLLM_HOST environment variable.
	 *
	 * @since 1.0.0
	 */
	private function set_vllm_host(): void {
		$host = $this->get_vllm_host();

		if ( '' === $host ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv -- Required to set VLLM_HOST for the provider SDK.
		putenv( 'VLLM_HOST=' . $host );
	}

	/**
	 * Registers the vLLM provider with the AI Client.
	 *
	 * @since 1.0.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$this->set_vllm_host();

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( VllmProvider::class ) ) {
			return;
		}

		$registry->registerProvider( VllmProvider::class );
	}

	/**
	 * Registers fallback authentication for the vLLM provider.
	 *
	 * If no API key was provided via wp-ai-client (which passes credentials at priority 10),
	 * this registers an empty API key so that local vLLM instances work without configuration.
	 *
	 * @since 1.0.0
	 */
	public function register_fallback_auth(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'vllm' ) ) {
			return;
		}

		// Only set fallback if no authentication has been configured yet.
		$auth = $registry->getProviderRequestAuthentication( 'vllm' );
		if ( null !== $auth ) {
			return;
		}

		$registry->setProviderRequestAuthentication(
			'vllm',
			new ApiKeyRequestAuthentication( '' )
		);
	}

	/**
	 * Initializes the vLLM settings.
	 *
	 * @since 1.0.0
	 */
	public function initialize_settings(): void {
		$settings = new VllmSettings();
		$settings->init();
	}

	/**
	 * Adds action links to the plugin list table.
	 *
	 * This adds "Settings" link to the plugin's action links
	 * on the Plugins page.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing action links.
	 * @return array<string> Modified action links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'options-general.php?page=ai-provider-for-vllm' ),
			esc_html__( 'Settings', 'ai-provider-for-vllm' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Allows localhost requests to the vLLM host.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $external Whether the request is external.
	 * @param string $host The host of the request.
	 * @param string $url The URL of the request.
	 * @return bool Whether the request is allowed.
	 */
	public function allow_localhost_requests( $external, $host, $url ): bool {
		if ( strpos( $url, $this->get_vllm_host() ) !== false ) {
			return true;
		}

		return $external;
	}

	/**
	 * Allows vLLM ports.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int> $ports The ports.
	 * @return array<int> The allowed ports.
	 */
	public function allow_vllm_ports( $ports ): array {
		$vllm_host = $this->get_vllm_host();
		$vllm_port = wp_parse_url( $vllm_host, PHP_URL_PORT );

		if ( ! $vllm_port ) {
			return $ports;
		}

		return array_merge( $ports, array( $vllm_port ) );
	}
}
