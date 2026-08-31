<?php
/**
 * Main plugin class — bootstraps all modules.
 *
 * @package Just_Another_Generic_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Core orchestrator for the Just Another Generic Chatbot plugin.
 */
final class JAGC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var ?JAGC_Plugin
	 */
	private static ?JAGC_Plugin $instance = null;

	/**
	 * Settings module.
	 *
	 * @var ?JAGC_Settings
	 */
	private ?JAGC_Settings $settings = null;

	/**
	 * Knowledge Base module.
	 *
	 * @var ?JAGC_Knowledge_Base
	 */
	private ?JAGC_Knowledge_Base $knowledge_base = null;

	/**
	 * Spam module.
	 *
	 * @var ?JAGC_Spam
	 */
	private ?JAGC_Spam $spam = null;

	/**
	 * Captcha module.
	 *
	 * @var ?JAGC_Captcha
	 */
	private ?JAGC_Captcha $captcha = null;

	/**
	 * Lead capture module.
	 *
	 * @var ?JAGC_Lead_Capture
	 */
	private ?JAGC_Lead_Capture $lead_capture = null;

	/**
	 * Chatbot module.
	 *
	 * @var ?JAGC_Chatbot
	 */
	private ?JAGC_Chatbot $chatbot = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return JAGC_Plugin
	 */
	public static function get_instance(): JAGC_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — wires up hooks and modules.
	 *
	 * Note: load_plugin_textdomain() is not called since WordPress 4.6
	 * loads translations for plugins automatically.
	 */
	private function __construct() {
		$this->require_files();
		$this->init_modules();
		$this->check_dependencies();
	}

	/**
	 * Includes module class files.
	 */
	private function require_files(): void {
		require_once JAGC_PLUGIN_DIR . 'includes/class-jagc-settings.php';
		require_once JAGC_PLUGIN_DIR . 'includes/class-jagc-knowledge-base.php';
		require_once JAGC_PLUGIN_DIR . 'includes/class-jagc-spam.php';
		require_once JAGC_PLUGIN_DIR . 'includes/class-jagc-captcha.php';
		require_once JAGC_PLUGIN_DIR . 'includes/class-jagc-lead-capture.php';
		require_once JAGC_PLUGIN_DIR . 'includes/class-jagc-chatbot.php';
	}

	/**
	 * Instantiates all modules.
	 */
	private function init_modules(): void {
		$this->settings       = new JAGC_Settings();
		$this->knowledge_base = new JAGC_Knowledge_Base( $this->settings );
		$this->spam           = new JAGC_Spam( $this->settings );
		$this->captcha        = new JAGC_Captcha( $this->settings );
		$this->lead_capture   = new JAGC_Lead_Capture( $this->settings );
		$this->chatbot        = new JAGC_Chatbot( $this, $this->settings, $this->knowledge_base, $this->spam, $this->captcha, $this->lead_capture );
	}

	/**
	 * Checks that the runtime has the AI building blocks we depend on and
	 * surfaces an admin notice when something is missing.
	 */
	private function check_dependencies(): void {
		add_action( 'admin_notices', array( $this, 'render_dependency_notices' ) );
	}

	/**
	 * Renders admin notices for missing dependencies.
	 */
	public function render_dependency_notices(): void {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: %s: Link to the AI plugin. */
						__( 'Just Another Generic Chatbot needs the WordPress AI building blocks to function. Please install and activate the <a href="%s">AI plugin</a> or upgrade to WordPress 7.0+. Then configure an AI provider under <strong>Settings &rsaquo; Connectors</strong>.', 'just-another-generic-chatbot' ),
						'https://wordpress.org/plugins/ai/'
					),
					array(
						'a'      => array( 'href' => array() ),
						'strong' => array(),
					)
				)
			);
		}

		if ( ! class_exists( 'Akismet' ) && $this->settings->get( 'spam_protection', true ) ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: %s: Link to Akismet. */
						__( 'Just Another Generic Chatbot recommends <a href="%s">Akismet</a> for spam protection. Install and activate it to enable spam filtering on chat messages. Rate-limiting fallback is active in the meantime.', 'just-another-generic-chatbot' ),
						'https://wordpress.org/plugins/akismet/'
					),
					array( 'a' => array( 'href' => array() ) )
				)
			);
		}

		if ( ! $this->captcha->is_available() && $this->settings->get( 'captcha_enabled', false ) ) {
			printf(
				'<div class="notice notice-info"><p>%s</p></div>',
				wp_kses(
					sprintf(
						/* translators: %s: Link to the Turnstile plugin. */
						__( 'Just Another Generic Chatbot is configured to use captcha but the <a href="%s">Simple CAPTCHA with Cloudflare Turnstile</a> plugin is not active. Install and configure it, or disable captcha in the chatbot settings.', 'just-another-generic-chatbot' ),
						'https://wordpress.org/plugins/simple-cloudflare-turnstile/'
					),
					array( 'a' => array( 'href' => array() ) )
				)
			);
		}
	}

	/**
	 * Returns whether the AI client SDK is available.
	 *
	 * @return bool
	 */
	public function is_ai_available(): bool {
		return function_exists( 'wp_ai_client_prompt' );
	}

	// ── Module accessors ──────────────────────────────────────────────

	/**
	 * Gets the settings module.
	 *
	 * @return JAGC_Settings
	 */
	public function settings(): JAGC_Settings {
		return $this->settings;
	}

	/**
	 * Gets the knowledge base module.
	 *
	 * @return JAGC_Knowledge_Base
	 */
	public function knowledge_base(): JAGC_Knowledge_Base {
		return $this->knowledge_base;
	}

	/**
	 * Gets the spam module.
	 *
	 * @return JAGC_Spam
	 */
	public function spam(): JAGC_Spam {
		return $this->spam;
	}

	/**
	 * Gets the captcha module.
	 *
	 * @return JAGC_Captcha
	 */
	public function captcha(): JAGC_Captcha {
		return $this->captcha;
	}

	/**
	 * Gets the lead capture module.
	 *
	 * @return JAGC_Lead_Capture
	 */
	public function lead_capture(): JAGC_Lead_Capture {
		return $this->lead_capture;
	}

	// ── Activation / Deactivation ────────────────────────────────────

	/**
	 * Runs on plugin activation.
	 */
	public static function activate(): void {
		require_once JAGC_PLUGIN_DIR . 'includes/class-jagc-settings.php';
		require_once JAGC_PLUGIN_DIR . 'includes/class-jagc-knowledge-base.php';

		// Seed default settings.
		if ( false === get_option( 'jagc_settings' ) ) {
			add_option( 'jagc_settings', JAGC_Settings::get_defaults() );
		}

		// Register the CPT so rewrite rules are set up on activation.
		$kb = new JAGC_Knowledge_Base( new JAGC_Settings() );
		$kb->register_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Runs on plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
