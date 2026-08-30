<?php
/**
 * Captcha module — integrates with the Simple Cloudflare Turnstile plugin.
 *
 * @package AI_Connector_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides captcha verification for chatbot messages using Cloudflare Turnstile
 * via the "Simple CAPTCHA with Cloudflare Turnstile" WordPress plugin.
 */
class AICC_Captcha {

	/**
	 * Settings instance.
	 *
	 * @var AICC_Settings
	 */
	private AICC_Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param AICC_Settings $settings Settings instance.
	 */
	public function __construct( AICC_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Returns whether the Turnstile plugin is available and configured.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return function_exists( 'cfturnstile_check' )
			&& function_exists( 'cfturnstile_field_show' )
			&& ! empty( get_option( 'cfturnstile_key' ) )
			&& ! empty( get_option( 'cfturnstile_secret' ) );
	}

	/**
	 * Returns whether captcha is enabled in our settings and the Turnstile
	 * plugin is available.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) $this->settings->get( 'captcha_enabled', false ) && $this->is_available();
	}

	/**
	 * Verifies a Turnstile token server-side.
	 *
	 * @param string $token The cf-turnstile-response token from the frontend.
	 * @return array{valid: bool, reason: string}
	 */
	public function verify( string $token ): array {
		if ( ! $this->is_enabled() ) {
			return array(
				'valid'  => true,
				'reason' => '',
			);
		}

		if ( empty( $token ) ) {
			return array(
				'valid'  => false,
				'reason' => __( 'Captcha verification required. Please complete the challenge.', 'ai-connector-chatbot' ),
			);
		}

		$result = cfturnstile_check( $token );

		if ( is_array( $result ) && ! empty( $result['success'] ) ) {
			return array(
				'valid'  => true,
				'reason' => '',
			);
		}

		return array(
			'valid'  => false,
			'reason' => __( 'Captcha verification failed. Please try again.', 'ai-connector-chatbot' ),
		);
	}

	/**
	 * Returns the Turnstile site key for frontend rendering.
	 *
	 * @return string
	 */
	public function get_site_key(): string {
		return (string) get_option( 'cfturnstile_key' );
	}

	/**
	 * Enqueues the Turnstile scripts for frontend use.
	 */
	public function enqueue_scripts(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// The Turnstile plugin provides this action to load its scripts.
		if ( function_exists( 'cfturnstile_register_api' ) ) {
			cfturnstile_register_api( array( 'in_footer' => true ) );
			wp_enqueue_script( 'cfturnstile' );
		}
	}
}
