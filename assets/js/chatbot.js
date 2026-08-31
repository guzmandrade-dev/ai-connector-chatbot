/**
 * Just Another Generic Chatbot — Frontend widget.
 *
 * Vanilla JavaScript (no build step required).
 * Creates a floating chat button and panel that communicates with the
 * WordPress REST API. Optionally integrates with Cloudflare Turnstile.
 */

( function () {
	'use strict';

	if ( typeof window.JAGC_DATA === 'undefined' ) {
		return;
	}

	var data      = window.JAGC_DATA;
	var strings   = data.strings || {};
	var container = document.querySelector( '.jagc-chatbot-container' );

	if ( ! container ) {
		return;
	}

	var position        = container.getAttribute( 'data-jagc-position' ) || data.position || 'bottom-right';
	var captchaToken    = null;
	var captchaWidgetId = null;
	var captchaReady    = false;

	var state = {
		isOpen: false,
		isTyping: false,
		messages: [],
	};

	// ── DOM construction ────────────────────────────────────────────

	function createButton() {
		var btn       = document.createElement( 'button' );
		btn.className = 'jagc-chat-toggle';
		btn.setAttribute( 'type', 'button' );
		btn.setAttribute( 'aria-label', strings.openChat || 'Open chat' );
		btn.setAttribute( 'aria-expanded', 'false' );
		btn.innerHTML =
			'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
			'<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>' +
			'</svg>';
		return btn;
	}

	function createPanel() {
		var panel       = document.createElement( 'div' );
		panel.className = 'jagc-chat-panel';
		panel.setAttribute( 'role', 'dialog' );
		panel.setAttribute( 'aria-label', data.title || 'Chat' );
		panel.setAttribute( 'aria-modal', 'false' );
		panel.setAttribute( 'hidden', '' );

		// Header.
		var header       = document.createElement( 'div' );
		header.className = 'jagc-chat-header';

		var headerText       = document.createElement( 'div' );
		headerText.className = 'jagc-chat-header-text';

		var titleEl         = document.createElement( 'span' );
		titleEl.className   = 'jagc-chat-title';
		titleEl.textContent = data.title || 'Chat';
		headerText.appendChild( titleEl );

		if ( data.subtitle ) {
			var subtitleEl         = document.createElement( 'span' );
			subtitleEl.className   = 'jagc-chat-subtitle';
			subtitleEl.textContent = data.subtitle;
			headerText.appendChild( subtitleEl );
		}
		header.appendChild( headerText );

		var closeBtn       = document.createElement( 'button' );
		closeBtn.className = 'jagc-chat-close';
		closeBtn.setAttribute( 'type', 'button' );
		closeBtn.setAttribute( 'aria-label', strings.closeChat || 'Close chat' );
		closeBtn.innerHTML =
			'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
			'<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>' +
			'</svg>';
		header.appendChild( closeBtn );

		// Messages area.
		var messagesEl       = document.createElement( 'div' );
		messagesEl.className = 'jagc-chat-messages';
		messagesEl.setAttribute( 'role', 'log' );
		messagesEl.setAttribute( 'aria-live', 'polite' );
		messagesEl.setAttribute( 'aria-atomic', 'false' );

		// Captcha area (between messages and input, shown when enabled).
		var captchaEl = null;
		if ( data.captchaEnabled && data.captchaSiteKey ) {
			captchaEl           = document.createElement( 'div' );
			captchaEl.className = 'jagc-chat-captcha';
		}

		// Input area.
		var inputWrap       = document.createElement( 'div' );
		inputWrap.className = 'jagc-chat-input-area';

		var input       = document.createElement( 'textarea' );
		input.className = 'jagc-chat-input';
		input.setAttribute( 'type', 'text' );
		input.setAttribute( 'placeholder', strings.placeholder || 'Type your message…' );
		input.setAttribute( 'rows', '1' );
		input.setAttribute( 'aria-label', strings.placeholder || 'Type your message…' );

		var sendBtn       = document.createElement( 'button' );
		sendBtn.className = 'jagc-chat-send';
		sendBtn.setAttribute( 'type', 'button' );
		sendBtn.setAttribute( 'aria-label', strings.send || 'Send' );
		sendBtn.innerHTML =
			'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
			'<line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>' +
			'</svg>';

		inputWrap.appendChild( input );
		inputWrap.appendChild( sendBtn );

		panel.appendChild( header );
		panel.appendChild( messagesEl );
		if ( captchaEl ) {
			panel.appendChild( captchaEl );
		}
		panel.appendChild( inputWrap );

		return {
			panel: panel,
			messagesEl: messagesEl,
			input: input,
			sendBtn: sendBtn,
			closeBtn: closeBtn,
			captchaEl: captchaEl,
		};
	}

	// ── Captcha ──────────────────────────────────────────────────────

	function renderCaptcha() {
		if ( ! data.captchaEnabled || ! data.captchaSiteKey || ! elements.captchaEl ) {
			return;
		}

		// If Turnstile is already loaded, render the widget.
		if ( typeof window.turnstile !== 'undefined' && ! captchaWidgetId ) {
			captchaWidgetId = window.turnstile.render(
				elements.captchaEl,
				{
					sitekey: data.captchaSiteKey,
					callback: function ( token ) {
						captchaToken = token;
						captchaReady = true;
					},
					'expired-callback': function () {
						captchaToken = null;
						captchaReady = false;
					},
					'error-callback': function () {
						captchaToken = null;
						captchaReady = false;
					},
				}
			);
		}
	}

	function resetCaptcha() {
		captchaToken = null;
		captchaReady = false;
		if ( captchaWidgetId !== null && typeof window.turnstile !== 'undefined' ) {
			window.turnstile.reset( captchaWidgetId );
		}
	}

	// ── Message rendering ──────────────────────────────────────────

	function addMessage( role, content, storeInState ) {
		var msg       = document.createElement( 'div' );
		msg.className = 'jagc-message jagc-message--' + role;

		var avatar       = document.createElement( 'span' );
		avatar.className = 'jagc-message-avatar';
		avatar.setAttribute( 'aria-hidden', 'true' );
		avatar.textContent = role === 'user' ? 'U' : 'AI';

		var bubble       = document.createElement( 'div' );
		bubble.className = 'jagc-message-bubble';

		var text       = document.createElement( 'div' );
		text.className = 'jagc-message-text';
		text.innerHTML = formatMessage( content );

		bubble.appendChild( text );
		msg.appendChild( avatar );
		msg.appendChild( bubble );

		elements.messagesEl.appendChild( msg );
		elements.messagesEl.scrollTop = elements.messagesEl.scrollHeight;

		// storeInState defaults to true; pass false for display-only
		// messages like the welcome greeting that shouldn't be sent
		// as conversation history to the AI API.
		if ( storeInState !== false ) {
			state.messages.push( { role: role, content: content } );
		}
	}

	function formatMessage( text ) {
		var div = document.createElement( 'div' );
		// Basic XSS protection: escape HTML, then restore formatting.
		div.textContent = text;
		var escaped     = div.innerHTML;
		escaped         = escaped.replace( /\*\*(.+?)\*\*/g, '<strong>$1</strong>' );
		escaped         = escaped.replace( /\[(.+?)\]\((https?:\/\/.+?)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>' );
		escaped         = escaped.replace( /\n/g, '<br>' );
		return escaped;
	}

	function showTyping() {
		var msg       = document.createElement( 'div' );
		msg.className = 'jagc-message jagc-message--assistant jagc-typing-indicator';
		msg.setAttribute( 'aria-label', strings.typing || 'Assistant is typing…' );

		var avatar       = document.createElement( 'span' );
		avatar.className = 'jagc-message-avatar';
		avatar.setAttribute( 'aria-hidden', 'true' );
		avatar.textContent = 'AI';

		var dots       = document.createElement( 'div' );
		dots.className = 'jagc-message-bubble';
		dots.innerHTML = '<span class="jagc-typing-dot"></span><span class="jagc-typing-dot"></span><span class="jagc-typing-dot"></span>';

		msg.appendChild( avatar );
		msg.appendChild( dots );
		elements.messagesEl.appendChild( msg );
		elements.messagesEl.scrollTop = elements.messagesEl.scrollHeight;
		return msg;
	}

	function removeTyping( el ) {
		if ( el && el.parentNode ) {
			el.parentNode.removeChild( el );
		}
	}

	// ── API communication ──────────────────────────────────────────

	function sendMessage( text ) {
		if ( state.isTyping ) {
			return;
		}

		// Check captcha if enabled.
		if ( data.captchaEnabled && ! captchaReady ) {
			addMessage( 'assistant', strings.captchaRequired || 'Please complete the captcha challenge first.' );
			return;
		}

		// Capture history before adding the current user message, so the
		// current message isn't duplicated in both `message` and `history`.
		var history = state.messages.slice( -10 ).filter(
			function ( m ) {
				return m.role === 'user' || m.role === 'assistant';
			}
		);

		addMessage( 'user', text );
		elements.input.value = '';
		autoResize();

		state.isTyping            = true;
		elements.sendBtn.disabled = true;
		var typingEl              = showTyping();

		var payload = {
			message: text,
			history: history,
		};

		if ( data.captchaEnabled ) {
			payload.captcha_token = captchaToken;
		}

		var headers = {
			'Content-Type': 'application/json',
		};

		if ( data.nonce ) {
			headers[ 'X-WP-Nonce' ] = data.nonce;
		}

		fetch(
			data.restUrl,
			{
				method: 'POST',
				headers: headers,
				body: JSON.stringify( payload ),
				credentials: 'same-origin',
			}
		)
			.then(
				function ( response ) {
					return response.json().then(
						function ( json ) {
							return { ok: response.ok, json: json };
						}
					);
				}
			)
			.then(
				function ( result ) {
					removeTyping( typingEl );

					if ( result.ok && result.json.reply ) {
							addMessage( 'assistant', result.json.reply );

							// If the AI signaled conversation end, close after a delay.
						if ( result.json.close_chat ) {
							setTimeout(
								function () {
									closeChat();
								},
								3500
							);
						}
					} else {
						var errorMsg = ( result.json && result.json.message ) ? result.json.message : ( strings.error || 'Something went wrong.' );
						if ( result.json && result.json.code === 'jagc_spam_blocked' ) {
							errorMsg = strings.spamBlocked || errorMsg;
						}
						if ( result.json && result.json.code === 'jagc_ai_unavailable' ) {
							errorMsg = strings.aiUnavailable || errorMsg;
						}
						if ( result.json && result.json.code === 'jagc_captcha_failed' ) {
							errorMsg = strings.captchaFailed || errorMsg;
						}
						addMessage( 'assistant', errorMsg );
					}//end if

					// Reset captcha after each message.
					if ( data.captchaEnabled ) {
						resetCaptcha();
					}
				}
			)
			.catch(
				function () {
					removeTyping( typingEl );
					addMessage( 'assistant', strings.error || 'Something went wrong.' );
					if ( data.captchaEnabled ) {
							resetCaptcha();
					}
				}
			)
			.finally(
				function () {
					state.isTyping            = false;
					elements.sendBtn.disabled = false;
					elements.input.focus();
				}
			);
	}

	// ── UI helpers ─────────────────────────────────────────────────

	function openChat() {
		state.isOpen = true;
		elements.panel.removeAttribute( 'hidden' );
		toggleBtn.setAttribute( 'aria-expanded', 'true' );
		toggleBtn.classList.add( 'jagc-chat-toggle--active' );

		// Add welcome message on first open. Don't store it in conversation
		// state — it's a greeting, not part of the chat history, and the AI
		// API requires history to start with a user message.
		if ( state.messages.length === 0 && data.welcomeMessage ) {
			addMessage( 'assistant', data.welcomeMessage, false );
		}

		// Render captcha on first open if enabled.
		if ( data.captchaEnabled && ! captchaWidgetId ) {
			// Turnstile may already be loaded by the Turnstile plugin.
			if ( typeof window.turnstile !== 'undefined' ) {
				renderCaptcha();
			} else {
				// Wait for the Turnstile API to load.
				var checkInterval = setInterval(
					function () {
						if ( typeof window.turnstile !== 'undefined' ) {
								renderCaptcha();
								clearInterval( checkInterval );
						}
					},
					200
				);
				setTimeout(
					function () {
						clearInterval( checkInterval );
					},
					10000
				);
			}
		}//end if

		setTimeout(
			function () {
				elements.input.focus();
			},
			100
		);
	}

	function closeChat() {
		state.isOpen = false;
		elements.panel.setAttribute( 'hidden', '' );
		toggleBtn.setAttribute( 'aria-expanded', 'false' );
		toggleBtn.classList.remove( 'jagc-chat-toggle--active' );
	}

	function autoResize() {
		elements.input.style.height = 'auto';
		elements.input.style.height = Math.min( elements.input.scrollHeight, 120 ) + 'px';
	}

	function handleKeyDown( e ) {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			var text = elements.input.value.trim();
			if ( text ) {
				sendMessage( text );
			}
		}
	}

	// ── Initialization ──────────────────────────────────────────────

	container.classList.add( 'jagc-position-' + position );

	var toggleBtn = createButton();
	var elements  = createPanel();

	container.appendChild( toggleBtn );
	container.appendChild( elements.panel );

	// Event listeners.
	toggleBtn.addEventListener(
		'click',
		function () {
			if ( state.isOpen ) {
				closeChat();
			} else {
				openChat();
			}
		}
	);

	elements.closeBtn.addEventListener( 'click', closeChat );

	elements.sendBtn.addEventListener(
		'click',
		function () {
			var text = elements.input.value.trim();
			if ( text ) {
				sendMessage( text );
			}
		}
	);

	elements.input.addEventListener( 'input', autoResize );
	elements.input.addEventListener( 'keydown', handleKeyDown );

	// Close on Escape.
	document.addEventListener(
		'keydown',
		function ( e ) {
			if ( e.key === 'Escape' && state.isOpen ) {
				closeChat();
			}
		}
	);
} )();