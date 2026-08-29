<?php
/**
 * Settings module — manages plugin options and the admin settings page.
 *
 * @package AI_Connector_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles all plugin settings, their registration, and the admin UI.
 */
class AICC_Settings {

	/** Option key used in the options table. */
	const OPTION_KEY = 'aicc_settings';

	/** @var array<string,mixed>|null Cached settings. */
	private ?array $settings = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Returns the default settings array.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults(): array {
		return [
			'enabled'            => false,
			'title'              => __( 'Chat with us', 'ai-connector-chatbot' ),
			'subtitle'           => __( 'We typically reply in a moment', 'ai-connector-chatbot' ),
			'position'           => 'bottom-right',
			'welcome_message'    => __( 'Hello! How can I help you today?', 'ai-connector-chatbot' ),
			'system_prompt'      => __( 'You are a helpful assistant for this website. Answer questions accurately and concisely. If you do not know the answer, say so. Use only the provided knowledge base context to answer questions about this site.', 'ai-connector-chatbot' ),
			'max_context_length' => 4000,
			'kb_post_types'      => [ 'aicc_article', 'post', 'page' ],
			'spam_protection'    => true,
			'rate_limit'         => 10,
			'max_tokens'         => 1000,
			'temperature'        => 0.3,
			'captcha_enabled'    => false,
			'ai_provider'        => 'auto',
			'ai_model'           => '',
			'lead_capture_enabled' => false,
			'lead_email'          => '',
			'lead_webhook_url'    => '',
		];
	}

	/**
	 * Gets a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Fallback value if the key is not set.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		if ( null === $this->settings ) {
			$this->settings = wp_parse_args(
				get_option( self::OPTION_KEY, [] ),
				self::get_defaults()
			);
		}

		return $this->settings[ $key ] ?? $fallback;
	}

	/**
	 * Gets all settings, merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		if ( null === $this->settings ) {
			$this->settings = wp_parse_args(
				get_option( self::OPTION_KEY, [] ),
				self::get_defaults()
			);
		}

		return $this->settings;
	}

	// ── Admin page ────────────────────────────────────────────────────

	/**
	 * Adds the settings sub-menu page.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'AI Connector Chatbot', 'ai-connector-chatbot' ),
			__( 'AI Connector Chatbot', 'ai-connector-chatbot' ),
			'manage_options',
			'ai-connector-chatbot',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Registers the settings, sections, and fields.
	 */
	public function register_settings(): void {
		register_setting(
			'aicc_settings_group',
			self::OPTION_KEY,
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		// General section.
		add_settings_section(
			'aicc_general',
			__( 'Chatbot', 'ai-connector-chatbot' ),
			[ $this, 'render_general_intro' ],
			'ai-connector-chatbot'
		);

		add_settings_field( 'enabled', __( 'Enable chatbot', 'ai-connector-chatbot' ), [ $this, 'render_checkbox' ], 'ai-connector-chatbot', 'aicc_general', [
			'label_for' => 'aicc_enabled',
			'key'       => 'enabled',
		] );

		add_settings_field( 'title', __( 'Title', 'ai-connector-chatbot' ), [ $this, 'render_text_input' ], 'ai-connector-chatbot', 'aicc_general', [
			'label_for' => 'aicc_title',
			'key'       => 'title',
		] );

		add_settings_field( 'subtitle', __( 'Subtitle', 'ai-connector-chatbot' ), [ $this, 'render_text_input' ], 'ai-connector-chatbot', 'aicc_general', [
			'label_for' => 'aicc_subtitle',
			'key'       => 'subtitle',
		] );

		add_settings_field( 'welcome_message', __( 'Welcome message', 'ai-connector-chatbot' ), [ $this, 'render_textarea' ], 'ai-connector-chatbot', 'aicc_general', [
			'label_for' => 'aicc_welcome_message',
			'key'       => 'welcome_message',
		] );

		add_settings_field( 'position', __( 'Position', 'ai-connector-chatbot' ), [ $this, 'render_select' ], 'ai-connector-chatbot', 'aicc_general', [
			'label_for' => 'aicc_position',
			'key'       => 'position',
			'options'   => [
				'bottom-right' => __( 'Bottom right', 'ai-connector-chatbot' ),
				'bottom-left'  => __( 'Bottom left', 'ai-connector-chatbot' ),
			],
		] );

		// AI section.
		add_settings_section(
			'aicc_ai',
			__( 'AI Configuration', 'ai-connector-chatbot' ),
			[ $this, 'render_ai_intro' ],
			'ai-connector-chatbot'
		);

		add_settings_field( 'system_prompt', __( 'System instructions', 'ai-connector-chatbot' ), [ $this, 'render_textarea' ], 'ai-connector-chatbot', 'aicc_ai', [
			'label_for' => 'aicc_system_prompt',
			'key'       => 'system_prompt',
		] );

		add_settings_field( 'ai_provider', __( 'AI provider', 'ai-connector-chatbot' ), [ $this, 'render_provider_select' ], 'ai-connector-chatbot', 'aicc_ai', [
			'label_for' => 'aicc_ai_provider',
			'key'       => 'ai_provider',
		] );

		add_settings_field( 'ai_model', __( 'Model (optional)', 'ai-connector-chatbot' ), [ $this, 'render_text_input' ], 'ai-connector-chatbot', 'aicc_ai', [
			'label_for' => 'aicc_ai_model',
			'key'       => 'ai_model',
		] );

		add_settings_field( 'temperature', __( 'Temperature', 'ai-connector-chatbot' ), [ $this, 'render_number_input' ], 'ai-connector-chatbot', 'aicc_ai', [
			'label_for' => 'aicc_temperature',
			'key'       => 'temperature',
			'min'       => 0,
			'max'       => 2,
			'step'      => 0.1,
		] );

		add_settings_field( 'max_tokens', __( 'Max tokens', 'ai-connector-chatbot' ), [ $this, 'render_number_input' ], 'ai-connector-chatbot', 'aicc_ai', [
			'label_for' => 'aicc_max_tokens',
			'key'       => 'max_tokens',
			'min'       => 100,
			'max'       => 8000,
			'step'      => 100,
		] );

		// Knowledge Base section.
		add_settings_section(
			'aicc_kb',
			__( 'Knowledge Base', 'ai-connector-chatbot' ),
			[ $this, 'render_kb_intro' ],
			'ai-connector-chatbot'
		);

		add_settings_field( 'kb_post_types', __( 'Post types to include', 'ai-connector-chatbot' ), [ $this, 'render_post_types_checkboxes' ], 'ai-connector-chatbot', 'aicc_kb', [
			'key' => 'kb_post_types',
		] );

		add_settings_field( 'max_context_length', __( 'Max context length (chars)', 'ai-connector-chatbot' ), [ $this, 'render_number_input' ], 'ai-connector-chatbot', 'aicc_kb', [
			'label_for' => 'aicc_max_context_length',
			'key'       => 'max_context_length',
			'min'       => 500,
			'max'       => 32000,
			'step'      => 500,
		] );

		// Spam section.
		add_settings_section(
			'aicc_spam',
			__( 'Spam Protection', 'ai-connector-chatbot' ),
			[ $this, 'render_spam_intro' ],
			'ai-connector-chatbot'
		);

		add_settings_field( 'spam_protection', __( 'Enable spam protection', 'ai-connector-chatbot' ), [ $this, 'render_checkbox' ], 'ai-connector-chatbot', 'aicc_spam', [
			'label_for' => 'aicc_spam_protection',
			'key'       => 'spam_protection',
		] );

		add_settings_field( 'rate_limit', __( 'Rate limit (messages per hour per user)', 'ai-connector-chatbot' ), [ $this, 'render_number_input' ], 'ai-connector-chatbot', 'aicc_spam', [
			'label_for' => 'aicc_rate_limit',
			'key'       => 'rate_limit',
			'min'       => 1,
			'max'       => 100,
			'step'      => 1,
		] );

		// Captcha section.
		add_settings_section(
			'aicc_captcha',
			__( 'Captcha Protection', 'ai-connector-chatbot' ),
			[ $this, 'render_captcha_intro' ],
			'ai-connector-chatbot'
		);

		add_settings_field( 'captcha_enabled', __( 'Enable captcha', 'ai-connector-chatbot' ), [ $this, 'render_checkbox' ], 'ai-connector-chatbot', 'aicc_captcha', [
			'label_for' => 'aicc_captcha_enabled',
			'key'       => 'captcha_enabled',
		] );

		// Lead Capture section.
		add_settings_section(
			'aicc_leads',
			__( 'Lead Capture', 'ai-connector-chatbot' ),
			[ $this, 'render_leads_intro' ],
			'ai-connector-chatbot'
		);

		add_settings_field( 'lead_capture_enabled', __( 'Enable lead capture', 'ai-connector-chatbot' ), [ $this, 'render_checkbox' ], 'ai-connector-chatbot', 'aicc_leads', [
			'label_for' => 'aicc_lead_capture_enabled',
			'key'       => 'lead_capture_enabled',
		] );

		add_settings_field( 'lead_email', __( 'Lead notification email', 'ai-connector-chatbot' ), [ $this, 'render_text_input' ], 'ai-connector-chatbot', 'aicc_leads', [
			'label_for' => 'aicc_lead_email',
			'key'       => 'lead_email',
		] );

		add_settings_field( 'lead_webhook_url', __( 'Webhook URL (optional)', 'ai-connector-chatbot' ), [ $this, 'render_text_input' ], 'ai-connector-chatbot', 'aicc_leads', [
			'label_for' => 'aicc_lead_webhook_url',
			'key'       => 'lead_webhook_url',
		] );
	}

	/**
	 * Sanitizes settings before saving.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( array $input ): array {
		$defaults = self::get_defaults();
		$clean    = [];

		$clean['enabled']            = ! empty( $input['enabled'] );
		$clean['title']              = sanitize_text_field( $input['title'] ?? $defaults['title'] );
		$clean['subtitle']           = sanitize_text_field( $input['subtitle'] ?? $defaults['subtitle'] );
		$clean['position']           = in_array( $input['position'] ?? '', [ 'bottom-right', 'bottom-left' ], true ) ? $input['position'] : 'bottom-right';
		$clean['welcome_message']    = sanitize_textarea_field( $input['welcome_message'] ?? $defaults['welcome_message'] );
		$clean['system_prompt']      = sanitize_textarea_field( $input['system_prompt'] ?? $defaults['system_prompt'] );
		$clean['max_context_length'] = absint( $input['max_context_length'] ?? $defaults['max_context_length'] );
		$clean['max_tokens']         = absint( $input['max_tokens'] ?? $defaults['max_tokens'] );
		$clean['temperature']        = max( 0, min( 2, (float) ( $input['temperature'] ?? $defaults['temperature'] ) ) );
		$clean['spam_protection']    = ! empty( $input['spam_protection'] );
		$clean['rate_limit']         = max( 1, absint( $input['rate_limit'] ?? $defaults['rate_limit'] ) );
		$clean['captcha_enabled']    = ! empty( $input['captcha_enabled'] );

		// Lead capture.
		$clean['lead_capture_enabled'] = ! empty( $input['lead_capture_enabled'] );
		$clean['lead_email']           = sanitize_email( $input['lead_email'] ?? '' );
		$clean['lead_webhook_url']     = esc_url_raw( $input['lead_webhook_url'] ?? '' );

		// AI provider and model.
		$valid_providers            = array_keys( $this->get_available_providers() );
		$clean['ai_provider']       = in_array( $input['ai_provider'] ?? '', $valid_providers, true ) ? $input['ai_provider'] : 'auto';
		$clean['ai_model']          = sanitize_text_field( $input['ai_model'] ?? '' );

		// Sanitize post type checkboxes.
		$allowed_types    = $this->get_available_post_types();
		$allowed_type_keys = array_keys( $allowed_types );
		$submitted_types  = $input['kb_post_types'] ?? [];
		$clean['kb_post_types'] = array_values( array_intersect( $allowed_type_keys, (array) $submitted_types ) );
		if ( empty( $clean['kb_post_types'] ) ) {
			$clean['kb_post_types'] = [ 'aicc_article' ];
		}

		return $clean;
	}

	/**
	 * Gets available AI providers from the WordPress Connectors API.
	 *
	 * @return array<string,string> provider_id => provider_name.
	 */
	public function get_available_providers(): array {
		$providers = [
			'auto' => __( 'Automatic (use any available)', 'ai-connector-chatbot' ),
		];

		if ( function_exists( 'wp_get_connectors' ) ) {
			$connectors = wp_get_connectors();
			if ( is_array( $connectors ) ) {
				foreach ( $connectors as $id => $connector ) {
					if ( isset( $connector['type'] ) && 'ai_provider' === $connector['type'] ) {
						$providers[ $id ] = $connector['name'] ?? $id;
					}
				}
			}
		}

		return $providers;
	}

	/**
	 * Gets all public post types suitable for the knowledge base.
	 *
	 * @return array<string,string> slug => label.
	 */
	public function get_available_post_types(): array {
		$types  = get_post_types( [ 'public' => true ], 'objects' );
		$result = [];
		foreach ( $types as $slug => $obj ) {
			$result[ $slug ] = $obj->labels->singular_name;
		}
		// Always include our CPT.
		if ( ! isset( $result['aicc_article'] ) ) {
			$result['aicc_article'] = __( 'Knowledge Base Article', 'ai-connector-chatbot' );
		}
		return $result;
	}

	// ── Render helpers ───────────────────────────────────────────────

	/**
	 * Renders the settings page wrapper.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'ai-connector-chatbot' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'aicc_settings_group' );
				do_settings_sections( 'ai-connector-chatbot' );
				submit_button( __( 'Save Settings', 'ai-connector-chatbot' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Section intro for the general chatbot section.
	 */
	public function render_general_intro(): void {
		echo '<p class="description">' . esc_html__( 'Configure how the chatbot appears on your site.', 'ai-connector-chatbot' ) . '</p>';
	}

	/**
	 * Section intro for the AI section.
	 */
	public function render_ai_intro(): void {
		echo '<p class="description">' . esc_html__( 'AI providers and API keys are managed under Settings › Connectors (WordPress 7.0+). Select a provider and optionally a specific model here.', 'ai-connector-chatbot' ) . '</p>';
	}

	/**
	 * Section intro for the Knowledge Base section.
	 */
	public function render_kb_intro(): void {
		echo '<p class="description">' . esc_html__( 'Select which content types to include as context for the chatbot. Knowledge Base Articles are always available. The article content is where you write the information the chatbot should know.', 'ai-connector-chatbot' ) . '</p>';
	}

	/**
	 * Section intro for the Spam section.
	 */
	public function render_spam_intro(): void {
		echo '<p class="description">' . esc_html__( 'Uses Akismet for spam detection when available, with a rate-limiting fallback.', 'ai-connector-chatbot' ) . '</p>';
	}

	/**
	 * Section intro for the Captcha section.
	 */
	public function render_captcha_intro(): void {
		echo '<p class="description">' . wp_kses(
			sprintf(
				/* translators: %s: Link to the Turnstile plugin. */
				__( 'Requires the <a href="%s">Simple CAPTCHA with Cloudflare Turnstile</a> plugin. Configure your Cloudflare site key and secret in its settings (Settings › Cloudflare Turnstile) before enabling captcha here.', 'ai-connector-chatbot' ),
				'https://wordpress.org/plugins/simple-cloudflare-turnstile/'
			),
			[ 'a' => [ 'href' => [] ] ]
		) . '</p>';
	}

	/**
	 * Section intro for the Lead Capture section.
	 */
	public function render_leads_intro(): void {
		echo '<p class="description">' . esc_html__( 'When enabled, the chatbot uses AI function calling to detect when users share their contact information and automatically saves it. Leads are delivered via email and/or a webhook URL (for connecting to Zapier, Make, n8n, Google Sheets, a CRM, etc.). No forms or database tables needed.', 'ai-connector-chatbot' ) . '</p>';
	}

	/**
	 * Renders a checkbox field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_checkbox( array $args ): void {
		$key   = $args['key'];
		$value = (bool) $this->get( $key );
		$id    = 'aicc_' . $key;
		printf(
			'<input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" %4$s />',
			esc_attr( $id ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			checked( $value, true, false )
		);
	}

	/**
	 * Renders a text input field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_text_input( array $args ): void {
		$key   = $args['key'];
		$value = (string) $this->get( $key );
		$id    = $args['label_for'] ?? 'aicc_' . $key;
		printf(
			'<input type="text" id="%1$s" name="%2$s[%3$s]" value="%4$s" class="regular-text" />',
			esc_attr( $id ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $value )
		);
	}

	/**
	 * Renders a number input field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_number_input( array $args ): void {
		$key   = $args['key'];
		$value = $this->get( $key );
		$id    = $args['label_for'] ?? 'aicc_' . $key;
		$min   = $args['min'] ?? 0;
		$max   = $args['max'] ?? '';
		$step  = $args['step'] ?? 1;
		printf(
			'<input type="number" id="%1$s" name="%2$s[%3$s]" value="%4$s" min="%5$s" max="%6$s" step="%7$s" class="small-text" />',
			esc_attr( $id ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( (string) $value ),
			esc_attr( (string) $min ),
			esc_attr( (string) $max ),
			esc_attr( (string) $step )
		);
	}

	/**
	 * Renders a textarea field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_textarea( array $args ): void {
		$key   = $args['key'];
		$value = (string) $this->get( $key );
		$id    = $args['label_for'] ?? 'aicc_' . $key;
		printf(
			'<textarea id="%1$s" name="%2$s[%3$s]" rows="5" class="large-text">%4$s</textarea>',
			esc_attr( $id ),
			esc_attr( self::OPTION_KEY ),
			esc_attr( $key ),
			esc_textarea( $value )
		);
	}

	/**
	 * Renders a select field.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_select( array $args ): void {
		$key     = $args['key'];
		$value   = (string) $this->get( $key );
		$id      = $args['label_for'] ?? 'aicc_' . $key;
		$options = $args['options'] ?? [];
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']">';
		foreach ( $options as $opt_val => $opt_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $opt_val ),
				selected( $value, $opt_val, false ),
				esc_html( $opt_label )
			);
		}
		echo '</select>';
	}

	/**
	 * Renders the AI provider select field, populated from the
	 * WordPress Connectors API.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_provider_select( array $args ): void {
		$key       = $args['key'];
		$value     = (string) $this->get( $key, 'auto' );
		$id        = $args['label_for'] ?? 'aicc_' . $key;
		$providers = $this->get_available_providers();

		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']">';
		foreach ( $providers as $opt_val => $opt_label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $opt_val ),
				selected( $value, $opt_val, false ),
				esc_html( $opt_label )
			);
		}
		echo '</select>';

		if ( count( $providers ) <= 1 ) {
			echo '<p class="description">' . esc_html__( 'No AI providers found. Install a provider connector plugin and configure it under Settings › Connectors.', 'ai-connector-chatbot' ) . '</p>';
		}
	}

	/**
	 * Renders checkboxes for selecting knowledge-base post types.
	 *
	 * @param array<string,mixed> $args Field arguments.
	 */
	public function render_post_types_checkboxes( array $args ): void {
		$key       = $args['key'];
		$selected  = (array) $this->get( $key, [] );
		$all_types = $this->get_available_post_types();
		echo '<fieldset>';
		foreach ( $all_types as $slug => $label ) {
			$checked = in_array( $slug, $selected, true );
			printf(
				'<label><input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s /> %5$s</label><br>',
				esc_attr( self::OPTION_KEY ),
				esc_attr( $key ),
				esc_attr( $slug ),
				checked( $checked, true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
	}
}