<?php

declare( strict_types=1 );

namespace GeorgeStephanis\AiProviderForVllm\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Error;
use WordPress\AiClient\AiClient;

/**
 * Class for the vLLM settings in the WordPress admin.
 *
 * Provides a settings page under Settings > vLLM for configuring the vLLM
 * host URL.
 *
 * @since 1.0.0
 */
class VllmSettings {

	private const OPTION_GROUP = 'ai-provider-for-vllm-settings';
	private const OPTION_NAME  = 'ai_provider_for_vllm_settings';
	private const PAGE_SLUG    = 'ai-provider-for-vllm';
	private const SECTION_ID   = 'ai_provider_for_vllm_main';
	private const AJAX_ACTION  = 'ai_provider_for_vllm_list_models';
	private const NONCE_ACTION = 'ai_provider_for_vllm_nonce';

	/**
	 * Initializes the settings.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_screen' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_script' ) );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_list_models' ) );
		add_filter( 'wpai_has_ai_credentials', array( $this, 'is_connected' ) );
	}

	/**
	 * Registers the setting and settings fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'default'           => array(),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			self::SECTION_ID,
			'',
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_NAME . '_host',
			__( 'Host URL', 'ai-provider-for-vllm' ),
			array( $this, 'render_host_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-host' )
		);

		add_settings_field(
			self::OPTION_NAME . '_model',
			__( 'Available Models', 'ai-provider-for-vllm' ),
			array( $this, 'render_available_models_field' ),
			self::PAGE_SLUG,
			self::SECTION_ID,
			array( 'label_for' => self::OPTION_NAME . '-model' )
		);
	}

	/**
	 * Registers the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_screen(): void {
		add_options_page(
			__( 'vLLM Settings', 'ai-provider-for-vllm' ),
			__( 'vLLM', 'ai-provider-for-vllm' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_screen' )
		);
	}

	/**
	 * Sanitizes the settings array.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The input value.
	 * @return array<string, string> The sanitized settings.
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$host = isset( $value['host'] ) ? trim( (string) $value['host'] ) : '';
		if ( '' !== $host ) {
			$host = rtrim( esc_url_raw( $host ), '/' );
		}

		return array(
			'host' => $host,
		);
	}

	/**
	 * Renders the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function render_screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>

		<div class="wrap ai-provider-for-vllm-settings-screen">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: 1: link to the AI Credentials screen, 2: closing link tag */
					esc_html__( 'Configure the connection to your vLLM instance. Enter the base URL of your vLLM server and provide your API key on the %1$sSettings > Connectors%2$s screen.', 'ai-provider-for-vllm' ),
					'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">',
					'</a>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: code tag, 2: closing code tag */
					esc_html__( 'Leave the host URL empty to use the default (%1$shttp://localhost:8000%2$s). You can also set the %1$sVLLM_HOST%2$s environment variable to override this setting.', 'ai-provider-for-vllm' ),
					'<code>',
					'</code>'
				);
				?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>

		<?php
	}

	/**
	 * Renders the host URL field.
	 *
	 * @since 1.0.0
	 */
	public function render_host_field(): void {
		$settings = self::get_settings();
		$value    = isset( $settings['host'] ) ? $settings['host'] : '';
		?>

		<input
			type="url"
			id="<?php echo esc_attr( self::OPTION_NAME . '-host' ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME . '[host]' ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
			placeholder="http://localhost:8000"
		/>
		<p class="description">
			<?php
			printf(
				/* translators: 1: code tag, 2: closing code tag */
				esc_html__( 'The base URL of your vLLM instance (without /v1). Example: %1$shttp://localhost:8000%2$s or %1$shttp://192.168.1.100:8000%2$s', 'ai-provider-for-vllm' ),
				'<code>',
				'</code>'
			);
			?>
		</p>

		<?php
	}

	/**
	 * Renders the available models list.
	 *
	 * @since 1.0.0
	 */
	public function render_available_models_field(): void {
		?>

		<div id="vllm-models-container">
			<span id="vllm-model-status"></span>
		</div>
		<p class="description">
			<?php
			echo esc_html__( 'Available models are fetched from your vLLM instance. If a model is not listed, ensure it is loaded in your vLLM server.', 'ai-provider-for-vllm' );
			?>
		</p>

		<?php
	}

	/**
	 * Enqueues the settings page script.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_settings_script( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$plugin_dir = AI_PROVIDER_FOR_VLLM_PLUGIN_DIR;
		$asset_file = $plugin_dir . 'build/admin/settings.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(); // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Asset file path is built from a known constant.

		$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : array();
		$version      = isset( $asset['version'] ) ? $asset['version'] : false;

		wp_enqueue_script(
			'ai-provider-for-vllm-settings',
			plugins_url( 'build/admin/settings.js', $plugin_dir . 'plugin.php' ),
			$dependencies,
			$version,
			true
		);

		wp_enqueue_style(
			'ai-provider-for-vllm-settings',
			plugins_url( 'build/admin/style-settings.css', $plugin_dir . 'plugin.php' ),
			array(),
			$version
		);
		wp_style_add_data( 'ai-provider-for-vllm-settings', 'rtl', 'replace' );

		wp_localize_script(
			'ai-provider-for-vllm-settings',
			'aiProviderForVllmSettings',
			array(
				'ajaxUrl' => esc_url( admin_url( 'admin-ajax.php' ) . '?action=' . self::AJAX_ACTION . '&_wpnonce=' . wp_create_nonce( self::NONCE_ACTION ) ),
			)
		);
	}

	/**
	 * Handles the AJAX request to list available vLLM models.
	 *
	 * @since 1.0.0
	 */
	public function ajax_list_models(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'ai-provider-for-vllm' ), 403 );
		}

		$models = $this->get_models();

		if ( is_wp_error( $models ) ) {
			wp_send_json_error( $models->get_error_message(), $models->get_error_code() );
		}

		wp_send_json_success( $models );
	}

	/**
	 * Checks if the vLLM provider is connected.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the vLLM provider is connected, false otherwise.
	 */
	public function is_connected(): bool {
		return ! is_wp_error( $this->get_models() );
	}

	/**
	 * Gets the models from the vLLM provider.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error|array<string, \GeorgeStephanis\AiProviderForVllm\Settings\ModelMetadata> The models.
	 */
	public function get_models() {
		$provider_id = 'vllm';
		$registry    = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( $provider_id ) ) {
			return new WP_Error( 'ai_provider_not_found', __( 'AI provider not found.', 'ai-provider-for-vllm' ), 404 );
		}

		$provider_classname = $registry->getProviderClassName( $provider_id );

		try {
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$provider_availability = $provider_classname::availability();
			if ( ! $provider_availability->isConfigured() ) {
				return new WP_Error( 'ai_provider_not_configured', __( 'AI provider not configured - ensure vLLM is running and you have valid API credentials.', 'ai-provider-for-vllm' ), 400 );
			}

			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			$model_metadata_directory = $provider_classname::modelMetadataDirectory();
			return $model_metadata_directory->listModelMetadata();
		} catch ( \Throwable $e ) {
			/* translators: %s: Error message. */
			return new WP_Error( 'could_not_list_models', sprintf( __( 'Could not list models for provider - is vLLM running and are the API credentials valid? Error: %s', 'ai-provider-for-vllm' ), $e->getMessage() ), 500 );
		}
	}

	/**
	 * Gets the settings from the WordPress option.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> The settings.
	 */
	public static function get_settings(): array {
		return (array) get_option( self::OPTION_NAME, array() );
	}
}
