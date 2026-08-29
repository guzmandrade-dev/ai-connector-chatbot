<?php
/**
 * Spam protection module — integrates with Akismet and provides rate-limiting.
 *
 * @package AI_Connector_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides spam detection for chatbot messages.
 */
class AICC_Spam {

	/** @var AICC_Settings Settings instance. */
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
	 * Checks whether a message is spam.
	 *
	 * Tries Akismet first. Falls back to rate-limiting when Akismet is
	 * unavailable or the check fails.
	 *
	 * @param string $message   The chat message.
	 * @param array  $user_data Optional user data [ip, user_agent, email, name].
	 * @return array{spam: bool, reason: string}
	 */
	public function check( string $message, array $user_data = [] ): array {
		if ( ! $this->settings->get( 'spam_protection', true ) ) {
			return [ 'spam' => false, 'reason' => '' ];
		}

		// Always apply rate limiting.
		$rate = $this->check_rate_limit( $user_data );
		if ( $rate['spam'] ) {
			return $rate;
		}

		// Try Akismet if available.
		if ( $this->is_akismet_active() ) {
			$akismet = $this->check_akismet( $message, $user_data );
			if ( null !== $akismet ) {
				return $akismet;
			}
		}

		return [ 'spam' => false, 'reason' => '' ];
	}

	/**
	 * Returns whether Akismet is active and connected.
	 *
	 * @return bool
	 */
	public function is_akismet_active(): bool {
		if ( ! class_exists( 'Akismet' ) ) {
			return false;
		}

		if ( method_exists( 'Akismet', 'get_api_key' ) ) {
			return (bool) Akismet::get_api_key();
		}

		return (bool) get_option( 'wordpress_api_key' );
	}

	/**
	 * Checks a message against the Akismet API.
	 *
	 * @param string $message   Chat message.
	 * @param array  $user_data User data.
	 * @return array{spam: bool, reason: string}|null Result, or null if the check could not be performed.
	 */
	private function check_akismet( string $message, array $user_data ): ?array {
		if ( ! class_exists( 'Akismet' ) || ! method_exists( 'Akismet', 'http_post' ) ) {
			return null;
		}

		$ip    = $user_data['ip'] ?? $this->get_client_ip();
		$ua    = $user_data['user_agent'] ?? ( $_SERVER['HTTP_USER_AGENT'] ?? '' );
		$email = $user_data['email'] ?? '';
		$name  = $user_data['name'] ?? '';

		$comment = [
			'blog'                 => get_option( 'home' ),
			'blog_lang'            => get_bloginfo( 'language' ),
			'blog_charset'         => get_bloginfo( 'charset' ),
			'user_ip'              => $ip,
			'user_agent'           => $ua,
			'comment_type'         => 'chat-message',
			'comment_author'       => $name,
			'comment_author_email' => $email,
			'comment_content'      => $message,
			'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s' ),
		];

		/**
		 * Filters the Akismet comment-check payload before it is sent.
		 *
		 * @since 0.1.0
		 *
		 * @param array  $comment  The Akismet comment data.
		 * @param string $message  The original chat message.
		 */
		$comment = (array) apply_filters( 'aicc_akismet_payload', $comment, $message );

		try {
			$response = Akismet::http_post( build_query( $comment ), 'comment-check' );
		} catch ( Throwable $e ) {
			return null;
		}

		if ( ! is_array( $response ) || ! isset( $response[1] ) ) {
			return null;
		}

		$is_spam = ( 'true' === trim( $response[1] ) );

		if ( $is_spam ) {
			return [
				'spam'   => true,
				'reason' => __( 'Message flagged as spam by Akismet.', 'ai-connector-chatbot' ),
			];
		}

		return [ 'spam' => false, 'reason' => '' ];
	}

	/**
	 * Applies rate-limiting per IP address.
	 *
	 * @param array $user_data User data containing IP.
	 * @return array{spam: bool, reason: string}
	 */
	private function check_rate_limit( array $user_data ): array {
		$ip            = $user_data['ip'] ?? $this->get_client_ip();
		$limit         = (int) $this->settings->get( 'rate_limit', 10 );
		$transient_key = 'aicc_rate_' . md5( (string) $ip );

		$count = (int) get_transient( $transient_key );
		if ( $count >= $limit ) {
			return [
				'spam'   => true,
				'reason' => __( 'Rate limit exceeded. Please try again later.', 'ai-connector-chatbot' ),
			];
		}

		set_transient( $transient_key, $count + 1, HOUR_IN_SECONDS );
		return [ 'spam' => false, 'reason' => '' ];
	}

	/**
	 * Returns the client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			$ip        = trim( $forwarded[0] );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '127.0.0.1';
	}
}