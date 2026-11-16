<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * Secure hosted card form and 3D Secure.
 *
 * The card form is Fat Zebra's hosted payments page in tokenize-only mode, so card numbers go
 * straight to Fat Zebra. The plugin charges the returned card token itself, the same way
 * ArtsPay's hosted checkout (artspay-pay) does. 3D Secure runs in the browser with fatzebra.js
 * against that token, and its result is sent with the purchase.
 *
 * @package ArtsPay
 */

namespace ArtsPay\Traits;

use ArtsPay\Debug_Logger;
use WC_Order;

/**
 * Hosted card form and 3D Secure trait.
 */
trait HostedCard {
	/**
	 * 3DS result fields Fat Zebra accepts under `extra`, keyed by the name fatzebra.js uses.
	 *
	 * @var array<string, string>
	 */
	private static array $three_ds_fields = array(
		'sli'                     => 'sli',
		'cavv'                    => 'cavv',
		'xid'                     => 'xid',
		'ver'                     => 'ver',
		'par'                     => 'par',
		'directoryServerTxnId'    => 'directory_server_txn_id',
		'directory_server_txn_id' => 'directory_server_txn_id',
		'threedsVersion'          => 'threeds_version',
		'threeds_version'         => 'threeds_version',
	);

	/**
	 * Fat Zebra hosts for the current mode.
	 *
	 * @return array{paynow: string, cdn: string, api: string}
	 */
	public function get_fatzebra_hosts(): array {
		if ( $this->is_sandbox_mode() ) {
			return array(
				'paynow' => 'https://paynow.pmnts-sandbox.io',
				'cdn'    => 'https://cdn.pmnts-sandbox.io',
				'api'    => 'https://api.pmnts-sandbox.io',
			);
		}

		return array(
			'paynow' => 'https://paynow.pmnts.io',
			'cdn'    => 'https://cdn.pmnts.io',
			'api'    => 'https://api.pmnts.io',
		);
	}

	/**
	 * True when customers enter their card in the hosted form.
	 */
	public function is_hosted_card_enabled(): bool {
		$credentials = $this->get_gateway_credentials();

		return 'hosted' === $this->get_option( 'card_form', 'onsite' )
			&& '' !== trim( (string) $credentials['shared_secret'] )
			&& '' !== trim( (string) $credentials['username'] );
	}

	/**
	 * True when 3D Secure is switched on and has everything it needs.
	 */
	public function is_three_ds_enabled(): bool {
		return $this->is_hosted_card_enabled()
			&& 'yes' === $this->get_option( 'three_ds_enabled' )
			&& '' !== trim( (string) $this->get_option( 'oauth_access_key' ) )
			&& '' !== trim( (string) $this->get_option( 'oauth_access_secret' ) );
	}

	/**
	 * Whether 3DS should run for the current cart. Deferred payments charge later, when a 3DS
	 * result would no longer be valid, so they skip it. Subscriptions are never deferred, and a
	 * $0 order only stores the card.
	 */
	public function is_three_ds_required_for_cart(): bool {
		if ( ! $this->is_three_ds_enabled() || $this->get_hosted_card_total()['amount'] <= 0 ) {
			return false;
		}
		if ( 'yes' !== $this->get_option( 'deferred_payments' ) ) {
			return true;
		}

		return class_exists( 'WC_Subscriptions_Cart' ) && \WC_Subscriptions_Cart::cart_contains_subscription();
	}

	/**
	 * Hosted form URL for an amount. A new reference each time keeps the URL single use.
	 *
	 * @param int    $amount   Amount in cents.
	 * @param string $currency Currency code.
	 *
	 * @return string
	 */
	public function get_hosted_card_url( int $amount, string $currency ): string {
		$credentials = $this->get_gateway_credentials();
		$secret      = (string) $credentials['shared_secret'];
		$reference   = 'wc' . strtolower( wp_generate_password( 20, false ) );
		$currency    = strtoupper( $currency );
		$three_ds    = $this->is_three_ds_enabled();

		// Fat Zebra drops surcharge from the hash and URL when 3DS is on and surcharge is off.
		$hash_parts = array( $reference, (string) $amount, $currency );
		if ( ! $three_ds ) {
			$hash_parts[] = 'false';
		}

		$query = array(
			'postmessage'   => 'true',
			'return_target' => '_self',
			'iframe'        => 'true',
			'show_email'    => 'false',
			'show_extras'   => 'false',
			'applepay'      => 'false',
			'googlepay'     => 'false',
			'layout'        => 'v2',
			'tokenize_only' => 'true',
			'button_text'   => __( 'Pay now', 'artspay' ),
		);
		if ( ! $three_ds ) {
			$query['surcharge'] = 'false';
		}

		/**
		 * Filter the stylesheet loaded into the hosted card form. Return an empty string for
		 * Fat Zebra's default look.
		 *
		 * @param string $css_url HTTPS stylesheet URL.
		 */
		$css_url = (string) apply_filters( 'artspay_hosted_card_css_url', 'https://cdn.artspay.com/vendor/v2/fatzebra.css' );
		if ( '' !== $css_url ) {
			$query['css']           = $css_url;
			$query['css_signature'] = hash_hmac( 'md5', $css_url, $secret );
		}

		$path = sprintf(
			'/v2/%s/%s/%s/%d/%s',
			rawurlencode( (string) $credentials['username'] ),
			$reference,
			$currency,
			$amount,
			hash_hmac( 'md5', implode( ':', $hash_parts ), $secret )
		);

		return $this->get_fatzebra_hosts()['paynow'] . $path . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Verification fatzebra.js needs to run 3DS against a card token.
	 *
	 * @param string $card_token Card token.
	 */
	public function get_card_token_verification( string $card_token ): string {
		return hash_hmac( 'md5', trim( $card_token ), (string) $this->get_gateway_credentials()['shared_secret'] );
	}

	/**
	 * Short-lived OAuth token fatzebra.js uses for 3DS. Tokens last 15 minutes, so one is reused
	 * for 10.
	 *
	 * @return string Empty on failure.
	 */
	public function get_three_ds_access_token(): string {
		$access_key    = trim( (string) $this->get_option( 'oauth_access_key' ) );
		$access_secret = trim( (string) $this->get_option( 'oauth_access_secret' ) );
		$cache_key     = 'artspay_fz_oauth_' . md5( ( $this->is_sandbox_mode() ? 'sandbox' : 'live' ) . $access_key );

		$cached = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$response = wp_remote_post(
			$this->get_fatzebra_hosts()['api'] . '/oauth/token',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'access_key'    => $access_key,
						'access_secret' => $access_secret,
					)
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			Debug_Logger::log( '3DS access token request failed: ' . $response->get_error_message(), 'error' );
			return '';
		}

		$body  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token = is_array( $body ) && isset( $body['data']['token'] ) ? (string) $body['data']['token'] : '';
		if ( '' === $token ) {
			Debug_Logger::log( sprintf( '3DS access token request returned HTTP %d without a token.', (int) wp_remote_retrieve_response_code( $response ) ), 'error' );
			return '';
		}

		set_transient( $cache_key, $token, 10 * MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * Read the posted 3DS result and convert it to the fields Fat Zebra expects.
	 *
	 * @return array<string, string>|null Null when no usable result was posted.
	 */
	private function get_posted_three_ds_result(): ?array {
		$raw = $this->posted_field( 'artspay-3ds' );
		if ( '' === $raw ) {
			return null;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$result = array();
		foreach ( self::$three_ds_fields as $from => $to ) {
			if ( isset( $decoded[ $from ] ) && is_scalar( $decoded[ $from ] ) && '' !== (string) $decoded[ $from ] ) {
				$result[ $to ] = sanitize_text_field( (string) $decoded[ $from ] );
			}
		}

		// Same rule as artspay-api's /pay/confirm: any 3DS field counts as a result.
		return array() !== $result ? $result : null;
	}

	/**
	 * Add an order note saying what the 3DS result means for the merchant.
	 *
	 * @param WC_Order              $order  Order.
	 * @param array<string, string> $result 3DS result.
	 */
	private function add_three_ds_order_note( WC_Order $order, array $result ): void {
		$sli = $result['sli'] ?? '';
		$ver = strtoupper( $result['ver'] ?? '' );

		if ( 'N' === $ver ) {
			$outcome = __( 'card not enrolled. No liability shift.', 'artspay' );
		} elseif ( in_array( $sli, array( '05', '02' ), true ) ) {
			$outcome = __( 'cardholder authenticated. Liability shifts to the card issuer.', 'artspay' );
		} elseif ( in_array( $sli, array( '06', '01' ), true ) ) {
			$outcome = __( 'authentication attempted. Liability shifts to the card issuer.', 'artspay' );
		} else {
			$outcome = __( 'not authenticated. No liability shift.', 'artspay' );
		}

		$order->add_order_note(
			sprintf(
				/* translators: 1: outcome sentence, 2: security level indicator, 3: enrolment status */
				__( '3D Secure: %1$s (SLI %2$s, enrolment %3$s)', 'artspay' ),
				$outcome,
				'' !== $sli ? $sli : '-',
				'' !== $ver ? $ver : '-'
			)
		);
		$order->update_meta_data( '_artspay_3ds_result', $result );
		$order->save();
	}

	/**
	 * Amount and currency the hosted form and 3DS should use: the order on pay-for-order,
	 * otherwise the cart.
	 *
	 * @return array{amount: int, currency: string}
	 */
	private function get_hosted_card_total(): array {
		$order_id = absint( get_query_var( 'order-pay' ) );
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				return array(
					'amount'   => (int) $this->convert_to_cents( $order->get_total() ),
					'currency' => $order->get_currency(),
				);
			}
		}

		$total = ( function_exists( 'WC' ) && WC()->cart ) ? (float) WC()->cart->get_total( 'edit' ) : 0.0;

		return array(
			'amount'   => (int) $this->convert_to_cents( $total ),
			'currency' => get_woocommerce_currency(),
		);
	}

	/**
	 * AJAX: signed hosted form URL for the current cart, or for a pay-for-order order.
	 */
	public function ajax_hosted_card_form(): void {
		check_ajax_referer( 'artspay-hosted-card', 'nonce' );

		if ( ! $this->is_hosted_card_enabled() ) {
			wp_send_json_error( null, 400 );
		}

		$total    = null;
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( $_POST['order_id'] ) ) : 0;
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			$key   = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';
			if ( ! $order instanceof WC_Order || ! $order->key_is_valid( $key ) ) {
				wp_send_json_error( null, 403 );
			}
			$total = array(
				'amount'   => (int) $this->convert_to_cents( $order->get_total() ),
				'currency' => $order->get_currency(),
			);
		}

		if ( null === $total ) {
			$cart  = WC()->cart;
			$total = array(
				'amount'   => $cart ? (int) $this->convert_to_cents( (float) $cart->get_total( 'edit' ) ) : 0,
				'currency' => get_woocommerce_currency(),
			);
		}

		wp_send_json_success( array( 'url' => $this->get_hosted_card_url( $total['amount'], $total['currency'] ) ) );
	}

	/**
	 * AJAX: what fatzebra.js needs to run 3DS on a card token.
	 */
	public function ajax_three_ds_prepare(): void {
		check_ajax_referer( 'artspay-hosted-card', 'nonce' );

		if ( ! $this->is_three_ds_enabled() ) {
			wp_send_json_error( null, 400 );
		}

		$card_token = isset( $_POST['card_token'] ) ? sanitize_text_field( wp_unslash( $_POST['card_token'] ) ) : '';
		if ( '' === $card_token ) {
			wp_send_json_error( null, 400 );
		}

		if ( ! $this->record_three_ds_check() ) {
			wp_send_json_error( array( 'message' => $this->get_attempt_limit_message() ), 429 );
		}

		$access_token = $this->get_three_ds_access_token();
		if ( '' === $access_token ) {
			wp_send_json_error( null, 502 );
		}

		wp_send_json_success(
			array(
				'verification' => $this->get_card_token_verification( $card_token ),
				'accessToken'  => $access_token,
			)
		);
	}

	/**
	 * Output the hosted card form container and load its scripts.
	 */
	private function render_hosted_card_fields(): void {
		$total = $this->get_hosted_card_total();

		// Keep this markup stable: WooCommerce only replaces the payment section when its HTML
		// changes, so an unchanged total leaves the card form (and anything typed) in place.
		echo '<div class="artspay-hosted-card" data-total="' . esc_attr( (string) $total['amount'] ) . '" data-currency="' . esc_attr( $total['currency'] ) . '" data-order-id="' . esc_attr( (string) absint( get_query_var( 'order-pay' ) ) ) . '">';
		echo '<div class="artspay-hosted-card__frame" aria-live="polite"><p class="artspay-hosted-card__loading">' . esc_html__( 'Loading secure card form…', 'artspay' ) . '</p></div>';
		echo '<div class="artspay-hosted-card__saved" hidden>';
		echo '<span class="artspay-hosted-card__saved-label"></span> ';
		echo '<button type="button" class="artspay-hosted-card__change button-link">' . esc_html__( 'Use a different card', 'artspay' ) . '</button>';
		echo '</div>';
		echo '<p class="artspay-hosted-card__note">' . esc_html__( 'Card details are entered securely with Fat Zebra and never reach this store.', 'artspay' ) . '</p>';
		echo '<input type="hidden" name="artspay-card-token" class="artspay-hosted-card__token" />';
		echo '<input type="hidden" name="artspay-3ds" class="artspay-hosted-card__3ds" />';
		echo '</div>';

		$this->enqueue_hosted_card_scripts();
	}

	/**
	 * Config for the hosted card form and 3DS scripts, shared by the classic and block checkouts.
	 *
	 * @return array<string, mixed>
	 */
	public function get_hosted_card_config(): array {
		$hosts = $this->get_fatzebra_hosts();

		return array(
			'paynowOrigin' => $hosts['paynow'],
			'sdkUrl'       => $hosts['cdn'] . '/sdk/v1/fatzebra.js',
			'username'     => (string) $this->get_gateway_credentials()['username'],
			'threeDs'      => $this->is_three_ds_required_for_cart(),
			'formUrl'      => \WC_AJAX::get_endpoint( 'artspay_hosted_card_form' ),
			'prepareUrl'   => \WC_AJAX::get_endpoint( 'artspay_3ds_prepare' ),
			'nonce'        => wp_create_nonce( 'artspay-hosted-card' ),
			'strings'      => array(
				/* translators: 1: card scheme, e.g. Visa, 2: last four digits */
				'saved'         => __( '%1$s ending %2$s', 'artspay' ),
				'savedFallback' => __( 'Card saved', 'artspay' ),
				'change'        => __( 'Use a different card', 'artspay' ),
				'loading'       => __( 'Loading secure card form…', 'artspay' ),
				'note'          => __( 'Card details are entered securely with Fat Zebra and never reach this store.', 'artspay' ),
				'enterCard'     => __( 'Enter your card details in the secure card form, then press Pay now.', 'artspay' ),
				'formError'     => __( 'The secure card form could not load. Refresh the page and try again.', 'artspay' ),
				'verifyError'   => __( 'We couldn\'t verify your card with your bank. Try again or use a different card.', 'artspay' ),
				'verifyFailed'  => __( 'Your bank could not verify this card. Try again or use a different card.', 'artspay' ),
				'cancelled'     => __( 'Card entry was cancelled.', 'artspay' ),
			),
		);
	}

	/**
	 * Register the shared hosted card helpers (artspay-fatzebra) and stylesheet.
	 */
	public static function register_hosted_card_assets(): void {
		wp_register_script( 'artspay-fatzebra', ARTSPAY_PLUGIN_URL . 'assets/js/artspay-fatzebra.js', array(), ARTSPAY_PLUGIN_VERSION, true );
		wp_register_style( 'artspay-hosted-card', ARTSPAY_PLUGIN_URL . 'assets/css/hosted-card.css', array(), ARTSPAY_PLUGIN_VERSION );
	}

	/**
	 * Load the classic checkout's hosted card form script. fatzebra.js loads later, only when 3DS runs.
	 */
	private function enqueue_hosted_card_scripts(): void {
		self::register_hosted_card_assets();
		wp_enqueue_style( 'artspay-hosted-card' );

		wp_enqueue_script(
			'artspay-hosted-card',
			ARTSPAY_PLUGIN_URL . 'assets/js/artspay-hosted-card.js',
			array( 'jquery', 'jquery-blockui', 'artspay-fatzebra' ),
			ARTSPAY_PLUGIN_VERSION,
			true
		);

		wp_add_inline_script(
			'artspay-hosted-card',
			'window.ArtsPay=window.ArtsPay||{};window.ArtsPay.hostedCard=' . wp_json_encode( $this->get_hosted_card_config() ) . ';',
			'before'
		);
	}
}
