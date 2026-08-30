<?php
/**
 * Lead Capture module — uses AI function calling to collect and deliver leads.
 *
 * Registers a `save_lead` function declaration with the AI. When the AI detects
 * that a user is providing contact information, it calls this function. The server
 * then delivers the lead via email (wp_mail) and/or webhook (wp_remote_post).
 *
 * No CPT, no database table, no form — the chatbot conversation IS the form.
 *
 * @package AI_Connector_Chatbot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Provides AI-powered lead capture via function calling.
 */
class AICC_Lead_Capture {

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
	 * Returns whether lead capture is enabled and at least one delivery
	 * method is configured.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( ! $this->settings->get( 'lead_capture_enabled', false ) ) {
			return false;
		}

		$email   = (string) $this->settings->get( 'lead_email', '' );
		$webhook = (string) $this->settings->get( 'lead_webhook_url', '' );

		return ! empty( $email ) || ! empty( $webhook );
	}

	/**
	 * Returns the FunctionDeclaration for the save_lead tool.
	 *
	 * @return \WordPress\AiClient\Tools\DTO\FunctionDeclaration|null
	 */
	public function get_function_declaration(): ?\WordPress\AiClient\Tools\DTO\FunctionDeclaration {
		if ( ! $this->is_enabled() ) {
			return null;
		}

		if ( ! class_exists( 'WordPress\AiClient\Tools\DTO\FunctionDeclaration' ) ) {
			return null;
		}

		$fn_class = 'WordPress\AiClient\Tools\DTO\FunctionDeclaration';

		return new $fn_class(
			'save_lead',
			__( 'Save a lead captured during the chat conversation. Call this function when a user provides their contact information (name, email, phone) and expresses interest in being contacted or learning more. Only call this when the user has explicitly shared their details.', 'ai-connector-chatbot' ),
			array(
				'type'       => 'object',
				'properties' => array(
					'name'  => array(
						'type'        => 'string',
						'description' => 'The contact\'s full name.',
					),
					'email' => array(
						'type'        => 'string',
						'description' => 'The contact\'s email address.',
						'format'      => 'email',
					),
					'phone' => array(
						'type'        => 'string',
						'description' => 'The contact\'s phone number, if provided.',
					),
					'notes' => array(
						'type'        => 'string',
						'description' => 'A summary of what the user is interested in or any additional context from the conversation.',
					),
				),
				'required'   => array( 'name', 'email' ),
			)
		);
	}

	/**
	 * Returns instructions to append to the system prompt when lead capture
	 * is enabled.
	 *
	 * @return string
	 */
	public function get_system_prompt_addition(): string {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		return "\n\n## Lead Capture\n\n" .
			__( 'You have the ability to save leads using the save_lead function. When a user shows interest in your services, products, or being contacted, naturally guide the conversation toward collecting their contact information. Ask for their name and email address conversationally — do not use form-like language. For example, instead of "Please provide your name and email," say something like "I\'d be happy to have someone follow up with you about this. Could you share your name and the best email to reach you at?" Only call the save_lead function after the user has provided at least their name and email. Never call save_lead more than once per conversation — once a lead is saved, do not save it again even if the user continues chatting. Never ask for or collect sensitive data like passwords, credit card numbers, or social security numbers. After saving a lead, let the user know their information has been received and someone will be in touch.', 'ai-connector-chatbot' ) .
			"\n\n" .
			__( "## Conversation End Detection\nWhen it is clear the user has no further questions (e.g. they say \"that is all\", \"no more questions\", \"thanks\", \"goodbye\", or similar), wrap up the conversation politely and append the marker [[CHAT_END]] at the very end of your response. This signals the system to close the chat panel. Only use this marker when you are confident the conversation has concluded — never use it if the user might still have follow-up questions.", 'ai-connector-chatbot' );
	}

	/**
	 * Executes the save_lead function — delivers the lead via email
	 * and/or webhook.
	 *
	 * @param string $name  Contact name.
	 * @param string $email Contact email.
	 * @param string $phone Contact phone (optional).
	 * @param string $notes Additional notes.
	 * @return array{success: bool, message: string}
	 */
	public function save_lead( string $name, string $email, string $phone = '', string $notes = '' ): array {
		$lead_data = array(
			'name'      => $name,
			'email'     => $email,
			'phone'     => $phone,
			'notes'     => $notes,
			'timestamp' => gmdate( 'Y-m-d H:i:s' ),
			'source'    => get_bloginfo( 'name' ) . ' — Chatbot',
			'url'       => home_url(),
		);

		$results = array();

		// Deliver via email.
		$email_to = (string) $this->settings->get( 'lead_email', '' );
		if ( ! empty( $email_to ) ) {
			$results[] = $this->send_email( $email_to, $lead_data );
		}

		// Deliver via webhook.
		$webhook_url = (string) $this->settings->get( 'lead_webhook_url', '' );
		if ( ! empty( $webhook_url ) ) {
			$results[] = $this->send_webhook( $webhook_url, $lead_data );
		}

		$all_success = ! empty( $results ) && ! in_array( false, $results, true );

		/**
		 * Fires after a lead has been captured.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string,mixed> $lead_data The lead data.
		 * @param bool                 $success  Whether all delivery methods succeeded.
		 */
		do_action( 'aicc_lead_captured', $lead_data, $all_success );

		return array(
			'success' => $all_success,
			'message' => $all_success
				? __( 'Lead saved successfully.', 'ai-connector-chatbot' )
				: __( 'Lead saved with some delivery errors.', 'ai-connector-chatbot' ),
		);
	}

	/**
	 * Sends lead data via email.
	 *
	 * @param string              $to        Email recipient.
	 * @param array<string,mixed> $lead_data Lead data.
	 * @return bool
	 */
	private function send_email( string $to, array $lead_data ): bool {
		$subject = sprintf(
			/* translators: %s: Lead name. */
			__( '[%s] New lead from chatbot', 'ai-connector-chatbot' ),
			$lead_data['name'] ?? __( 'Unknown', 'ai-connector-chatbot' )
		);

		$body  = __( 'A new lead was captured by the AI Connector Chatbot:', 'ai-connector-chatbot' ) . "\n\n";
		$body .= __( 'Name: ', 'ai-connector-chatbot' ) . $lead_data['name'] . "\n";
		$body .= __( 'Email: ', 'ai-connector-chatbot' ) . $lead_data['email'] . "\n";

		if ( ! empty( $lead_data['phone'] ) ) {
			$body .= __( 'Phone: ', 'ai-connector-chatbot' ) . $lead_data['phone'] . "\n";
		}

		if ( ! empty( $lead_data['notes'] ) ) {
			$body .= "\n" . __( 'Notes:', 'ai-connector-chatbot' ) . "\n" . $lead_data['notes'] . "\n";
		}

		$body .= "\n" . __( 'Captured: ', 'ai-connector-chatbot' ) . $lead_data['timestamp'] . "\n";
		$body .= __( 'Source: ', 'ai-connector-chatbot' ) . $lead_data['source'] . "\n";

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $to, $subject, $body, $headers );
	}

	/**
	 * Sends lead data to a webhook URL via HTTP POST.
	 *
	 * @param string              $url       Webhook URL.
	 * @param array<string,mixed> $lead_data Lead data.
	 * @return bool
	 */
	private function send_webhook( string $url, array $lead_data ): bool {
		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $lead_data ),
				'timeout' => 15,
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Processes a function call from the AI result. If the function is
	 * `save_lead`, executes it and returns the response.
	 *
	 * @param \WordPress\AiClient\Tools\DTO\FunctionCall $call The function call.
	 * @return \WordPress\AiClient\Tools\DTO\FunctionResponse|null
	 */
	public function handle_function_call( $call ): ?\WordPress\AiClient\Tools\DTO\FunctionResponse {
		if ( ! $call || ! method_exists( $call, 'getName' ) ) {
			return null;
		}

		$name = $call->getName();

		if ( 'save_lead' !== $name ) {
			return null;
		}

		if ( ! class_exists( 'WordPress\AiClient\Tools\DTO\FunctionResponse' ) ) {
			return null;
		}

		$args = $call->getArgs();
		$args = is_array( $args ) ? $args : array();

		$result = $this->save_lead(
			sanitize_text_field( $args['name'] ?? '' ),
			sanitize_email( $args['email'] ?? '' ),
			sanitize_text_field( $args['phone'] ?? '' ),
			sanitize_textarea_field( $args['notes'] ?? '' )
		);

		$response_class = 'WordPress\AiClient\Tools\DTO\FunctionResponse';

		return new $response_class(
			$call->getId(),
			$call->getName(),
			$result
		);
	}
}
