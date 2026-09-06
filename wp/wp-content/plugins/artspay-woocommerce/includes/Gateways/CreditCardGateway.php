<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * ArtsPay Credit Card Gateway
 *
 * @package ArtsPay
 */

namespace ArtsPay\Gateways;

use ArtsPay\API\Client;
use ArtsPay\Admin\ApplePay as ApplePayAdmin;
use ArtsPay\Debug_Logger;
use ArtsPay\Express\Blocks;
use ArtsPay\Express\ApplePay;
use ArtsPay\Express\BlocksApplePay;
use ArtsPay\Express\GooglePay;
use ArtsPay\Traits;
use DateTime;
use DateTimeZone;
use WC_Order;
use WC_Payment_Gateway_CC;
use WC_Product;
use WC_Subscriptions_Manager;
use WP_Error;

/**
 * CreditCardGateway class.
 */
class CreditCardGateway extends WC_Payment_Gateway_CC {
	use Traits\Encryption;
	use Traits\Utils;
	use Traits\Captcha;

	/**
	 * Global settings object.
	 *
	 * @var array
	 */
	public static array $global_settings = array();

	/**
	 * Gateway instance.
	 *
	 * @var CreditCardGateway|null
	 */
	private static ?self $instance = null;

	/**
	 * Google Pay express checkout integration.
	 *
	 * @var GooglePay|null
	 */
	private ?GooglePay $express_google_pay = null;

	/**
	 * Apple Pay express checkout integration.
	 *
	 * @var ApplePay|null
	 */
	private ?ApplePay $express_apple_pay = null;

	/**
	 * Params array.
	 *
	 * @var array
	 */
	private array $params = array();

	/**
	 * Get gateway instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id   = 'fatzebra';
		$this->icon = apply_filters( 'woocommerce_fatzebra_icon', ARTSPAY_PLUGIN_URL . 'assets/images/ap.png' );

		$this->supports = array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change',
			'subscription_date_changes',
		);

		// Load the settings form fields.
		$this->init_form_fields();
		// Load the settings.
		$this->init_settings();

		self::$global_settings = $this->settings;

		$this->title              = esc_html__( 'Credit / Debit Card', 'artspay' );
		$this->description        = $this->get_option( 'description' );
		$this->method_title       = esc_html__( 'ArtsPay', 'artspay' );
		$this->method_description = esc_html__( 'Accept credit and debit card payments at checkout, with support for express checkout.', 'artspay' );

		if ( $this->direct_post_enabled() ) {
			$this->supports[] = 'tokenization';
		}

		$this->has_fields = true;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 3 );
		add_filter( 'woocommerce_gateway_icon', array( $this, 'custom_icon' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_checkout_sandbox_notice_styles' ) );

		// Fraud device ID setup.
		if ( $this->fraud_detection_enabled() && 'yes' === $this->settings['fraud_device_id'] ) {
			add_action( 'woocommerce_after_order_notes', array( $this, 'add_device_id_hidden_field' ) );
			add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_device_id_hidden_field' ) );
		}

		add_action( 'admin_notices', array( $this, 'maybe_fraud_settings_notice' ) );

		$this->express_google_pay = new GooglePay( $this );
		$this->express_google_pay->register_hooks();
		( new Blocks( $this->express_google_pay, $this ) )->register_hooks();

		$this->express_apple_pay = new ApplePay( $this );
		$this->express_apple_pay->register_hooks();
		( new BlocksApplePay( $this->express_apple_pay ) )->register_hooks();
	}

	/**
	 * Adjust gateway icon.
	 *
	 * WooCommerce builds $icon from the gateway icon URL (ArtsPay mark). In wp-admin (e.g. Payments list) we keep that.
	 * On checkout and order-pay we show only scheme logos in the method label so the ArtsPay asset does not sit
	 * before Visa/Mastercard; elsewhere (e.g. emails) we keep the mark and append schemes.
	 *
	 * @param string $icon Gateway icon HTML from WC.
	 * @param string $id   Gateway ID.
	 *
	 * @return string
	 */
	public function custom_icon( string $icon, string $id ): string {
		if ( 'fatzebra' !== $id ) {
			return $icon;
		}

		if ( is_admin() ) {
			return $icon;
		}

		if ( $this->is_storefront_payment_method_icon_context() ) {
			return $this->get_card_logos_html();
		}

		return $icon . $this->get_card_logos_html();
	}

	/**
	 * True when the payment method label is shown on checkout or pay-for-order (not admin Payments settings).
	 */
	private function is_storefront_payment_method_icon_context(): bool {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return true;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return true;
		}
		if ( wp_doing_ajax() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WC core AJAX, no nonce in request name.
			$wc_ajax = isset( $_REQUEST['wc-ajax'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wc-ajax'] ) ) : '';
			if ( in_array( $wc_ajax, array( 'update_order_review', 'checkout' ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get gateway credentials.
	 *
	 * @return array|string[]
	 */
	public function get_gateway_credentials(): array {
		return array(
			'username'      => $this->get_option( 'username', 'TEST' ),
			'api_token'     => $this->get_option( 'token', 'TEST' ),
			'shared_secret' => $this->get_option( 'shared_secret', '033bd94b11' ),
		);
	}

	/**
	 * Add hidden anti-fraud field.
	 */
	public function add_device_id_hidden_field(): void {
		echo "<input type='hidden' name='pmnts_id' id='pmnts_id' />";

		$credentials = $this->get_gateway_credentials();

		$device_id_url = sprintf(
			'%s/fraud/fingerprint/%s.js',
			$this->is_sandbox_mode() ? 'https://gateway-sandbox.pmnts.io' : 'https://gateway.pmnts.io',
			rawurlencode( $credentials['username'] ?? '' )
		);

		wp_register_script( 'fz-fraud-deviceid', $device_id_url, array(), ARTSPAY_PLUGIN_VERSION, false );
		wp_enqueue_script( 'fz-fraud-deviceid' );
	}

	/**
	 * Saving the hidden field value in the order metadata
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_device_id_hidden_field( int $order_id ): void {
		$pmnts_id = filter_input( INPUT_POST, 'pmnts_id', FILTER_UNSAFE_RAW );
		if ( ! empty( $pmnts_id ) ) {
			update_post_meta( $order_id, 'device_id', sanitize_text_field( $pmnts_id ) );
		}
	}

	/**
	 * Indicates if direct post is enabled/configured or not.
	 *
	 * @return bool
	 */
	private function direct_post_enabled(): bool {
		return 'yes' === $this->get_option( 'use_direct_post' ) && ! empty( $this->get_option( 'shared_secret' ) );
	}

	/**
	 * Indicates if we should send fraud data.
	 *
	 * @return bool
	 */
	private function fraud_detection_enabled(): bool {
		return 'yes' === $this->get_option( 'fraud_data' );
	}

	/**
	 * Whether to email the site admin when Forter returns Deny.
	 *
	 * @return bool
	 */
	private function forter_decline_notifications_enabled(): bool {
		return 'yes' === $this->get_option( 'fraud_decline_notify' );
	}

	/**
	 * Recommend device fingerprinting when Forter is enabled but device ID is off.
	 */
	public function maybe_fraud_settings_notice(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin UI context
		if ( empty( $_GET['tab'] ) || 'checkout' !== $_GET['tab'] || empty( $_GET['section'] ) || 'fatzebra' !== $_GET['section'] ) {
			return;
		}
		if ( ! $this->fraud_detection_enabled() || 'yes' === $this->get_option( 'fraud_device_id' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'ArtsPay: Forter fraud screening is enabled without device fingerprinting. For stronger results, enable “Enable device fingerprinting” below (and consider loading the fingerprint script site-wide per Fat Zebra’s documentation).', 'artspay' );
		echo '</p></div>';
	}

	/**
	 * Check if sandbox mode is enabled.
	 *
	 * @return bool
	 */
	public function is_sandbox_mode(): bool {
		return 'yes' === $this->get_option( 'sandbox_mode' );
	}

	/**
	 * Load styles for the sandbox notice on checkout / order pay (when sandbox is on).
	 */
	public function maybe_enqueue_checkout_sandbox_notice_styles(): void {
		if ( ! $this->is_sandbox_mode() ) {
			return;
		}
		if ( ! $this->is_checkout_or_pay_page() ) {
			return;
		}

		wp_enqueue_style(
			'artspay-checkout-sandbox',
			ARTSPAY_PLUGIN_URL . 'assets/css/checkout-sandbox.css',
			array(),
			ARTSPAY_PLUGIN_VERSION
		);
	}

	/**
	 * Checkout or pay-for-order screen (not admin).
	 */
	private function is_checkout_or_pay_page(): bool {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
			return true;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Stripe-style test mode copy when sandbox is enabled (Fat Zebra test Visa + docs link).
	 */
	private function render_sandbox_checkout_notice(): void {
		if ( ! $this->is_sandbox_mode() ) {
			return;
		}

		$docs_url     = apply_filters( 'artspay_sandbox_test_docs_url', 'https://www.artspay.com/docs/developer/testing/test-card-numbers' );
		$example_card = apply_filters( 'artspay_sandbox_example_visa', '4005 5500 0000 0001' );

		$message = sprintf(
			/* translators: 1: example Visa number (sandbox), 2: Fat Zebra test card documentation URL */
			__( '<strong>Test mode:</strong> use the test Visa card <code class="artspay-test-card">%1$s</code> with any expiry date and CVC. More test card numbers are listed <a href="%2$s" target="_blank" rel="noopener noreferrer">here</a>.', 'artspay' ),
			esc_html( $example_card ),
			esc_url( $docs_url )
		);

		echo '<div class="artspay-test-mode-notice" role="status">';
		echo wp_kses(
			$message,
			array(
				'strong' => array(),
				'code'   => array( 'class' => true ),
				'a'      => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
			)
		);
		echo '</div>';
	}

	/**
	 * Returns the direct post URL.
	 *
	 * @return string
	 */
	private function get_direct_post_url(): string {
		$cc_client = new Client();

		$url = $this->is_sandbox_mode() ? $cc_client->sandbox_url : $cc_client->live_url;

		// Replace the URL with the tokenize method and re-create the order text (json payload).
		$url = str_replace( 'purchases', 'credit_cards', $url );
		$url = str_replace( 'v1.0', 'v2', $url );

		return $url . '/direct/' . $this->get_gateway_credentials()['username'] . '.json';
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields(): void {
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => esc_html__( 'Enable ArtsPay', 'artspay' ),
				'description' => esc_html__( 'When enabled, ArtsPay will be available as a payment method on the checkout page.', 'artspay' ),
				'type'    => 'checkbox',
				'label'   => esc_html__( 'Enable ', 'artspay' ),
				'default' => 'yes',
			),
			'sandbox_mode'       => array(
				'title'       => esc_html__( 'Enable Test Mode', 'artspay' ),
				'type'        => 'checkbox',
				'description' => sprintf(
					wp_kses(
						/* translators: %s: Fat Zebra test card numbers documentation URL */
						__( 'Switches the gateway URL to the sandbox URL. Use <a href="%s" target="_blank" rel="noopener noreferrer">test card numbers</a> to simulate transactions.', 'artspay' ),
						array(
							'a' => array(
								'href'   => true,
								'target' => true,
								'rel'    => true,
							),
						)
					),
					esc_url( 'https://artspay.notion.site/Test-Card-Numbers-92e018932f684000b36f7c322489a1d3' )
				),
				'default'     => 'yes',
			),
			'show_card_logos'    => array(
				'title'       => esc_html__( 'Show credit card logos', 'artspay' ),
				'type'        => 'multiselect',
				'description' => esc_html__( 'Shows or hides the credit card icons (AMEX, Visa, Discover, JCB etc).', 'artspay' ),
				'default'     => array( 'visa', 'mastercard' ),
				'options'     => array(
					'visa'             => 'VISA',
					'mastercard'       => 'MasterCard',
					'american_express' => 'AMEX',
					'diners'           => 'Diners',
					'jcb'              => 'JCB',
				),
			),
			'username'           => array(
				'title'       => esc_html__( 'Gateway Username', 'artspay' ),
				'type'        => 'text',
				'description' => esc_html__( 'The Gateway Authentication Username', 'artspay' ),
				'default'     => 'TEST',
				// Not a WP login; reduces Safari/iCloud Keychain treating the settings form as a credential form.
				'custom_attributes' => array(
					'autocomplete'  => 'off',
					'data-lpignore' => 'true',
				),
			),
			'token'              => array(
				'title'       => esc_html__( 'Gateway Token', 'artspay' ),
				'type'        => 'text',
				'description' => esc_html__( 'The Gateway Authentication Token', 'artspay' ),
				'default'     => 'TEST',
			),
			'shared_secret'      => array(
				'title'       => esc_html__( 'Gateway Shared Secret', 'artspay' ),
				'type'        => 'text',
				'description' => esc_html__( 'The Gateway Shared Secret. Required for Direct Post.', 'artspay' ),
				'default'     => '033bd94b11',
			),
			'use_direct_post'    => array(
				'title'       => esc_html__( 'Use Direct Post', 'artspay' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Uses the Direct Post method for payments which prevents card data from being sent to your web server.', 'artspay' ),
				'default'     => 'no',
			),
			'deferred_payments'  => array(
				'title'       => esc_html__( 'Enable Deferred Payments', 'artspay' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Deferred payments enable you to capture the customers card details and process them at a later date (for example, once you have reviewed the order for high-risk products). Note: Deferred Payments cannot be used with WooCommerce Subscription - any subscriptions will be processed in Real Time.', 'artspay' ),
				'default'     => 'no',
			),
			'google_pay_enabled' => array(
				'title'       => esc_html__( 'Enable Google Pay', 'artspay' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Enable', 'artspay' ),
				'description' => esc_html__( 'Shows Google Pay on cart and checkout where supported. Live stores require HTTPS and a supported browser with Google Pay. Label and colors follow the Button appearance section below.', 'artspay' ),
				'default'     => 'no',
			),
			'google_pay_merchant_id'         => array(
				'title'       => esc_html__( 'Google Pay gateway merchant ID', 'artspay' ),
				'type'        => 'text',
				'description' => sprintf(
					wp_kses(
						/* translators: %s: Fat Zebra Google Pay merchant ID documentation URL */
						__( 'Fat Zebra assigns this value for Google Pay, it is derived from your gateway username and API token. If you haven\'t already received this, get in touch with your ArtsPay representative.', 'artspay' ),
						array(
							'a'    => array(
								'href'   => true,
								'target' => true,
								'rel'    => true,
							),
							'code' => array(),
						)
					),
					esc_url( 'https://www.artspay.com/docs/developer/wallets/google-pay/google-pay-merchant-id' )
				),
				'default'     => '',
			),
			'google_pay_google_merchant_id'   => array(
				'title'       => esc_html__( 'Google merchant ID (production)', 'artspay' ),
				'type'        => 'text',
				'description' => esc_html__(
					'Optional in test mode. In production, Google Pay may require your Google Merchant ID (from Google Pay/Payments). This is separate from the Fat Zebra gateway merchant ID.',
					'artspay'
				),
				'default'     => '',
			),
			'google_pay_button_action' => array(
				'title'       => esc_html__( 'Button action', 'artspay' ),
				'type'        => 'select',
				'description' => esc_html__( 'Label shown on both wallet buttons (Google Pay API buttonType and Apple Pay web button type). You may comment out rows in the options array below to hide choices.', 'artspay' ),
				'default'     => 'buy',
				'options'     => array(
					'plain'    => esc_html__( 'Plain (logo only)', 'artspay' ),
					'buy'      => esc_html__( 'Buy with', 'artspay' ),
					'book'     => esc_html__( 'Book with', 'artspay' ),
					'checkout' => esc_html__( 'Checkout with', 'artspay' ),
					'donate'   => esc_html__( 'Donate with', 'artspay' ),
					'order'    => esc_html__( 'Order with', 'artspay' ),
					'pay'      => esc_html__( 'Pay with', 'artspay' ),
					// 'subscribe' => esc_html__( 'Subscribe with', 'artspay' ),
				),
			),
			'google_pay_theme' => array(
				'title'       => esc_html__( 'Theme', 'artspay' ),
				'type'        => 'select',
				'description' => esc_html__( 'Color style for both wallets. Light is solid white on Google Pay and white with a black border on Apple Pay (Apple’s white-outline style).', 'artspay' ),
				'default'     => 'system',
				'options'     => array(
					'system' => esc_html__( 'System (default)', 'artspay' ),
					'dark'   => esc_html__( 'Dark', 'artspay' ),
					'light'  => esc_html__( 'Light', 'artspay' ),
				),
			),
			'apple_pay_enabled' => array(
				'title'       => esc_html__( 'Enable Apple Pay', 'artspay' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Enable', 'artspay' ),
				'description' => esc_html__( 'Live stores require HTTPS and Apple Pay domain verification. Label and colors follow the Button appearance section below.', 'artspay' ),
				'default'     => 'no',
			),
			'fraud_data'         => array(
				'title'       => esc_html__( 'Enable Forter fraud screening', 'artspay' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Send order and customer data for Forter evaluation', 'artspay' ),
				'description' => esc_html__( 'When enabled, each charge includes a fraud payload (customer, items, shipping, website, optional device fingerprint). The gateway returns Accept, Challenge, Deny, or Error.', 'artspay' ),
				'default'     => 'no',
			),
			'fraud_device_id'    => array(
				'title'       => esc_html__( 'Enable device fingerprinting', 'artspay' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Load Fat Zebra device ID script on checkout', 'artspay' ),
				'description' => esc_html__( 'Strongly recommended with Forter: sends a browser fingerprint (pmnts_id) with the fraud payload. For best results the same script should be present site-wide; at minimum it runs on checkout when this is enabled.', 'artspay' ),
				'default'     => 'no',
			),
			'fraud_decline_notify' => array(
				'title'       => esc_html__( 'Email on Forter decline', 'artspay' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Notify the site administrator when a transaction is declined with a Forter result of Deny', 'artspay' ),
				'description' => esc_html__( 'Sends one email to the WordPress admin address.', 'artspay' ),
				'default'     => 'no',
			),
			'captcha_enabled'    => array(
				'title'       => esc_html__( 'Enable Captcha Protection', 'artspay' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Enable captcha verification before payment processing for additional security.', 'artspay' ),
				'default'     => 'no',
			),
			'captcha_provider'   => array(
				'title'       => esc_html__( 'Captcha Provider', 'artspay' ),
				'type'        => 'select',
				'description' => esc_html__( 'Choose your captcha provider.', 'artspay' ),
				'default'     => 'google',
				'options'     => array(
					'google'     => esc_html__( 'Google reCAPTCHA v3', 'artspay' ),
					'cloudflare' => esc_html__( 'Cloudflare Turnstile', 'artspay' ),
				),
			),
			'captcha_site_key'   => array(
				'title'       => esc_html__( 'Captcha Site Key', 'artspay' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter your captcha site key (public key) from your provider.', 'artspay' ),
				'default'     => '',
			),
			'captcha_secret_key' => array(
				'title'       => esc_html__( 'Captcha Secret Key', 'artspay' ),
				// Use text (not password): Safari ignores autocomplete hints on password fields and pairs with "username".
				'type'        => 'text',
				'description' => esc_html__( 'Enter your captcha secret key (private key) from your provider. Shown as plain text in wp-admin so browsers do not offer to save it as a password.', 'artspay' ),
				'default'     => '',
				'custom_attributes' => array(
					'autocomplete'  => 'off',
					'spellcheck'    => 'false',
					'data-lpignore' => 'true',
				),
			),
			'debug_mode'         => array(
				'title'       => esc_html__( 'Debug mode', 'artspay' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Log debug messages', 'artspay' ),
				'description' => esc_html__( 'When enabled, payment debug logs will be saved to WooCommerce > Status > Logs.', 'artspay' ),
				'default'     => 'no',
			),
		);
	}

	/**
	 * Enqueue Google Pay admin preview script (called from Plugin::maybe_enqueue_gateway_admin_assets).
	 */
	public function enqueue_google_pay_admin_preview_scripts(): void {
		wp_register_script(
			'google-pay-api',
			'https://pay.google.com/gp/p/js/pay.js',
			array(),
			ARTSPAY_PLUGIN_VERSION,
			true
		);
		wp_enqueue_script( 'google-pay-api' );
		wp_enqueue_script(
			'artspay-googlepay-admin-preview',
			ARTSPAY_PLUGIN_URL . 'assets/js/artspay-googlepay-admin-preview.js',
			array( 'jquery', 'google-pay-api' ),
			ARTSPAY_PLUGIN_VERSION,
			true
		);

		$merchant_id = trim( (string) $this->get_option( 'google_pay_merchant_id', '' ) );
		$theme       = (string) $this->get_option( 'google_pay_theme', 'system' );
		$sandbox     = $this->is_sandbox_mode();

		wp_localize_script(
			'artspay-googlepay-admin-preview',
			'artsPayGpayPreview',
			array(
				'environment'       => $sandbox ? 'TEST' : 'PRODUCTION',
				'gatewayMerchantId' => $merchant_id,
				'buttonType'        => GooglePay::map_button_type_for_api( (string) $this->get_option( 'google_pay_button_action', 'buy' ) ),
				'buttonColor'       => $this->google_pay_theme_to_button_color( $theme ),
				'requiresHttps'     => ! $sandbox && ! is_ssl(),
				'selectors'         => array(
					'buttonAction' => '#' . $this->get_field_key( 'google_pay_button_action' ),
					'theme'        => '#' . $this->get_field_key( 'google_pay_theme' ),
					'merchantId'   => '#' . $this->get_field_key( 'google_pay_merchant_id' ),
				),
				'strings'           => array(
					'previewInline' => __( 'We could not load a Google Pay preview. Enter your Google Pay merchant ID in the Google Pay section if it is not set. Use a secure (HTTPS) site in live mode, a supported browser such as Chrome, and a Google account with Google Pay.', 'artspay' ),
				),
			)
		);
	}

	/**
	 * Enqueue Apple Pay admin preview (called from Plugin::maybe_enqueue_gateway_admin_assets).
	 */
	public function enqueue_apple_pay_admin_preview_scripts(): void {
		wp_register_script(
			'apple-pay-js-sdk',
			'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js',
			array(),
			null,
			true
		);
		wp_enqueue_script( 'apple-pay-js-sdk' );

		wp_enqueue_script(
			'artspay-applepay-admin-preview',
			ARTSPAY_PLUGIN_URL . 'assets/js/artspay-applepay-admin-preview.js',
			array( 'jquery', 'apple-pay-js-sdk' ),
			ARTSPAY_PLUGIN_VERSION,
			true
		);

		$sandbox = $this->is_sandbox_mode();

		$express_btns = ApplePay::express_buttons_from_shared_wallet_settings( $this );

		wp_localize_script(
			'artspay-applepay-admin-preview',
			'artsPayApplePayPreview',
			array(
				'buttonType'    => $express_btns['buttonType'],
				'buttonStyle'   => $express_btns['buttonStyle'],
				'locale'        => str_replace( '_', '-', get_user_locale() ) ?: 'en',
				'requiresHttps' => ! $sandbox && ! is_ssl(),
				'selectors'     => array(
					'buttonAction' => '#' . $this->get_field_key( 'google_pay_button_action' ),
					'theme'        => '#' . $this->get_field_key( 'google_pay_theme' ),
				),
				'strings'       => array(
					'previewInline' => __( 'We could not load an Apple Pay button preview. Use Safari on macOS or iOS, or ensure the Apple Pay JS SDK can load. Live mode requires HTTPS. The button follows the shared theme and action settings on this page.', 'artspay' ),
				),
			)
		);
	}

	/**
	 * Side-by-side Google Pay and Apple Pay admin previews (Express checkouts page).
	 */
	private function render_express_checkouts_dual_preview_section(): void {
		echo '<div class="artspay-express-previews-block">';
		echo '<h3 class="artspay-gpay-preview__heading">' . esc_html__( 'Preview', 'artspay' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Non-interactive previews. Storefront buttons use the same size and the shared theme and action.', 'artspay' ) . '</p>';
		echo '<div class="artspay-express-previews" role="group" aria-label="' . esc_attr__( 'Express checkout button previews', 'artspay' ) . '">';
		echo '<div class="artspay-express-previews__col">';
		echo '<span class="artspay-express-previews__label">' . esc_html__( 'Google Pay', 'artspay' ) . '</span>';
		echo '<div id="artspay-gpay-preview-mount" class="artspay-gpay-preview__mount" role="region" aria-label="' . esc_attr__( 'Google Pay button preview', 'artspay' ) . '"></div>';
		echo '</div>';
		echo '<div class="artspay-express-previews__col">';
		echo '<span class="artspay-express-previews__label">' . esc_html__( 'Apple Pay', 'artspay' ) . '</span>';
		echo '<div id="artspay-applepay-preview-mount" class="artspay-gpay-preview__mount" role="region" aria-label="' . esc_attr__( 'Apple Pay button preview', 'artspay' ) . '"></div>';
		echo '</div>';
		echo '</div></div>';
	}

	/**
	 * Map Google Pay theme setting to createButton buttonColor.
	 *
	 * @param string $theme Setting: system|dark|light.
	 */
	private function google_pay_theme_to_button_color( string $theme ): string {
		if ( 'dark' === $theme ) {
			return 'black';
		}
		if ( 'light' === $theme ) {
			return 'white';
		}

		return 'default';
	}

	/**
	 * Section definitions: left column intro + links, right column field keys.
	 *
	 * @return array<string, array{title: string, description: string, fields: string[], links?: array<int, array{label: string, url: string}>}>
	 */
	private function get_gateway_settings_sections(): array {
		$docs_url    = apply_filters( 'artspay_docs_url', 'https://artspay.notion.site/Payment-Gateway-Guides-7ef95eee075c4e0f917332e6c1ea10ba' );
		$support_url = apply_filters( 'artspay_support_url', 'https://artspay.com' );

		$general_links = array(
			array(
				'label' => esc_html__( 'View ArtsPay documentation', 'artspay' ),
				'url'   => $docs_url,
			),
		);

		if ( is_string( $support_url ) && '' !== $support_url ) {
			$general_links[] = array(
				'label' => esc_html__( 'Contact ArtsPay', 'artspay' ),
				'url'   => $support_url,
			);
		}

		return array(
			'general' => array(
				'title'       => esc_html__( 'General', 'artspay' ),
				'description' => esc_html__( 'Turn ArtsPay on or off for checkout, and use sandbox mode to run test transactions without charging real cards.', 'artspay' ),
				'fields'      => array(
					'enabled',
					'sandbox_mode',
				),
				'links'       => $general_links,
			),
			'account' => array(
				'title'       => esc_html__( 'Account details', 'artspay' ),
				'description' => esc_html__( 'Gateway credentials from Fat Zebra, optional card logos, Direct Post, and deferred capture settings.', 'artspay' ),
				'fields'      => array(
					'show_card_logos',
					'username',
					'token',
					'shared_secret',
					'use_direct_post',
					'deferred_payments',
				),
				'links'       => $general_links,
			),
			'express' => array(
				'title'       => esc_html__( 'Express checkouts', 'artspay' ),
				'description' => esc_html__( 'Offer Google Pay and Apple Pay on cart and checkout. Open Express checkouts to enable each wallet and set a shared button theme and action.', 'artspay' ),
				'fields'      => array(),
			),
			'fraud'   => array(
				'title'       => esc_html__( 'Fraud screening', 'artspay' ),
				'description' => esc_html__( 'Fat Zebra routes fraud screening through Forter. Enable only after ArtsPay or Fat Zebra has activated Forter on your merchant account.', 'artspay' ),
				'fields'      => array(
					'fraud_data',
					'fraud_device_id',
					'fraud_decline_notify',
				),
			),
			'captcha' => array(
				'title'       => esc_html__( 'Captcha', 'artspay' ),
				'description' => esc_html__( 'Optional bot protection on checkout using Google reCAPTCHA v3 or Cloudflare Turnstile before the payment is submitted.', 'artspay' ),
				'fields'      => array(
					'captcha_enabled',
					'captcha_provider',
					'captcha_site_key',
					'captcha_secret_key',
				),
				'links'       => array(
					array(
						'label' => esc_html__( 'Google reCAPTCHA v3', 'artspay' ),
						'url'   => 'https://developers.google.com/recaptcha/docs/v3',
					),
					array(
						'label' => esc_html__( 'Cloudflare Turnstile', 'artspay' ),
						'url'   => 'https://developers.cloudflare.com/turnstile/',
					),
				),
			),
			'advanced' => array(
				'title'       => esc_html__( 'Advanced settings', 'artspay' ),
				'description' => esc_html__( 'Enable and configure advanced features for your store.', 'artspay' ),
				'fields'      => array(
					'debug_mode',
				),
			),
		);
	}

	/**
	 * Persist settings; preserve values when saving Express sub-pages.
	 *
	 * WooCommerce settings API assumes a full settings POST. Our Express checkouts detail pages intentionally
	 * render only a subset of fields; without protection, missing fields (including `enabled`) are cleared.
	 */
	public function process_admin_options(): void {
		$existing   = get_option( $this->get_option_key(), array() );
		$legacy_gw  = isset( $existing['google_pay_gateway_merchant_id'] ) ? trim( (string) $existing['google_pay_gateway_merchant_id'] ) : '';
		$prev_merch = isset( $existing['google_pay_merchant_id'] ) ? trim( (string) $existing['google_pay_merchant_id'] ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$express_page = isset( $_GET['artspay_express'] ) ? sanitize_key( (string) wp_unslash( $_GET['artspay_express'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WC gateway settings POST.
		if ( isset( $_POST['artspay_express_save_context'] ) ) {
			$express_page = sanitize_key( (string) wp_unslash( $_POST['artspay_express_save_context'] ) );
		}
		if ( in_array( $express_page, array( 'google_pay', 'apple_pay' ), true ) ) {
			$express_page = 'express_checkouts';
		}
		$express_keys = array();
		if ( 'express_checkouts' === $express_page ) {
			$express_keys = array(
				'google_pay_enabled',
				'google_pay_merchant_id',
				'google_pay_google_merchant_id',
				'google_pay_button_action',
				'google_pay_theme',
				'apple_pay_enabled',
			);
		}

		parent::process_admin_options();

		$this->init_settings();

		$changed = false;

		// If saving from an express sub-page, merge back any settings not present on that sub-page.
		if ( array() !== $express_keys ) {
			foreach ( $existing as $key => $value ) {
				if ( in_array( (string) $key, $express_keys, true ) ) {
					continue;
				}
				$this->settings[ (string) $key ] = $value;
				$changed                         = true;
			}
		} else {
			// Saving from the main ArtsPay settings page: preserve wallet detail fields not rendered there.
			$detail_only_keys = array(
				'google_pay_enabled',
				'google_pay_button_action',
				'google_pay_theme',
				'google_pay_merchant_id',
				'google_pay_google_merchant_id',
				'apple_pay_enabled',
			);
			foreach ( $detail_only_keys as $detail_key ) {
				$post_key = $this->get_field_key( $detail_key );
				// If the field wasn't posted (not on this screen), keep the existing value.
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce settings screen.
				if ( ! isset( $_POST[ $post_key ] ) && isset( $existing[ $detail_key ] ) ) {
					$this->settings[ $detail_key ] = $existing[ $detail_key ];
					$changed                       = true;
				}
			}
		}

		$mid     = isset( $this->settings['google_pay_merchant_id'] ) ? trim( (string) $this->settings['google_pay_merchant_id'] ) : '';
		if ( '' === $mid && '' !== $legacy_gw && '' === $prev_merch ) {
			$this->settings['google_pay_merchant_id'] = $legacy_gw;
			$changed                                  = true;
		}
		if ( isset( $this->settings['google_pay_gateway_merchant_id'] ) ) {
			unset( $this->settings['google_pay_gateway_merchant_id'] );
			$changed = true;
		}
		if ( $changed ) {
			update_option( $this->get_option_key(), $this->settings );
		}
	}

	/**
	 * Build settings table rows for a subset of field keys.
	 *
	 * @param string[] $keys Field keys in order.
	 *
	 * @return string
	 */
	private function get_settings_html_for_field_keys( array $keys ): string {
		$all    = $this->get_form_fields();
		$subset = array();
		foreach ( $keys as $key ) {
			if ( isset( $all[ $key ] ) ) {
				$subset[ $key ] = $all[ $key ];
			}
		}
		if ( array() === $subset ) {
			return '';
		}
		return $this->generate_settings_html( $subset, false );
	}

	/**
	 * Output the gateway settings screen (split layout: intro left, card right).
	 */
	public function admin_options(): void {
		$payments_list_url = admin_url( 'admin.php?page=wc-settings&tab=checkout' );
		$artspay_settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fatzebra' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$express_page = isset( $_GET['artspay_express'] ) ? sanitize_key( (string) wp_unslash( $_GET['artspay_express'] ) ) : '';
		if ( in_array( $express_page, array( 'google_pay', 'apple_pay' ), true ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'            => 'wc-settings',
						'tab'             => 'checkout',
						'section'         => 'fatzebra',
						'artspay_express' => 'express_checkouts',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}
		if ( '' !== $express_page && 'express_checkouts' !== $express_page ) {
			wp_safe_redirect( $artspay_settings_url );
			exit;
		}

		$header_title      = esc_html__( 'ArtsPay', 'artspay' );
		$header_back_url   = $payments_list_url;
		$header_back_label = esc_attr__( 'Back to payment methods', 'artspay' );
		if ( '' !== $express_page ) {
			$header_title      = esc_html__( 'Express checkouts', 'artspay' );
			$header_back_url   = $artspay_settings_url . '#artspay-express-checkouts';
			$header_back_label = esc_attr__( 'Back to ArtsPay settings', 'artspay' );
		}
		echo '<div class="artspay-gateway-settings">';
		echo '<h2 class="wc-admin-header">';
		echo '  <small>';
		echo '    <a class="" href="' . esc_url( $header_back_url ) . '" aria-label="' . $header_back_label . '">';
		echo '      <span aria-hidden="true">&larr;</span> ';
		echo '    </a>';
		echo '  </small>';
		echo $header_title;
		echo '</h2>';

		if ( $this->is_sandbox_mode() ) {
			echo '<div class="notice notice-warning artspay-admin-test-mode-notice"><p>';
			echo '<strong>' . esc_html__( 'Test mode active:', 'artspay' ) . '</strong> ';
			echo esc_html__( 'All transactions are simulated, customers can\'t make live purchases through ArtsPay.', 'artspay' );
			echo '</p></div>';
		}

		if ( 'express_checkouts' === $express_page ) {
			$this->render_wallet_environment_notice();
		}

		// $desc = $this->get_method_description();
		// if ( is_string( $desc ) && '' !== trim( $desc ) ) {
		// 	echo '<p class="artspay-gateway-settings__lead">' . wp_kses_post( wpautop( wptexturize( $desc ) ) ) . '</p>';
		// }

		if ( 'express_checkouts' === $express_page ) {
			$this->render_express_checkout_detail_page( $express_page );
			echo '</div>';
			return;
		}

		foreach ( $this->get_gateway_settings_sections() as $section_key => $section ) {
			$section_id_attr = ( 'express' === (string) $section_key ) ? ' id="artspay-express-checkouts"' : '';
			echo '<div class="artspay-gateway-settings__section"' . $section_id_attr . '>';
			echo '<div class="artspay-gateway-settings__aside">';
			echo '<h2 class="artspay-gateway-settings__heading">' . esc_html( $section['title'] ) . '</h2>';
			echo '<p class="artspay-gateway-settings__description">' . esc_html( $section['description'] ) . '</p>';
			if ( ! empty( $section['links'] ) && is_array( $section['links'] ) ) {
				echo '<ul class="artspay-gateway-settings__links" role="list">';
				foreach ( $section['links'] as $link ) {
					echo '<li><a class="artspay-gateway-settings__link" href="' . esc_url( $link['url'] ) . '" target="_blank" rel="noopener noreferrer">';
					echo esc_html( $link['label'] );
					echo '<span class="artspay-gateway-settings__link-icon" aria-hidden="true"></span></a></li>';
				}
				echo '</ul>';
			}
			echo '</div>';
			echo '<div class="artspay-gateway-settings__main"><div class="artspay-gateway-settings__card">';
			if ( 'express' === (string) $section_key ) {
				$this->render_express_checkout_list_section();
				echo '</div></div></div>';
				continue;
			}

			echo '<table class="form-table">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce core field markup.
			echo $this->get_settings_html_for_field_keys( $section['fields'] );
			echo '</table></div></div></div>';
		}
		echo '</div>';
	}

	/**
	 * Render the Express checkouts section list (Google Pay + Apple Pay).
	 */
	private function render_express_checkout_list_section(): void {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fatzebra' );

		$customize_link = add_query_arg(
			array( 'artspay_express' => 'express_checkouts' ),
			$settings_url
		);

		$google_icon_url = ARTSPAY_PLUGIN_URL . 'assets/images/google-logo.png';
		$apple_icon_url  = ARTSPAY_PLUGIN_URL . 'assets/images/apple-logo.png';

		echo '<div class="artspay-express-settings">';
		echo '<div class="artspay-express-settings__row artspay-express-settings__row--summary">';
		echo '<div class="artspay-express-settings__left">';
		echo '<span class="artspay-express-settings__wallet-icon" aria-hidden="true"><img src="' . esc_url( $google_icon_url ) . '" alt="" /></span>';
		echo '<span class="artspay-express-settings__wallet-icon" aria-hidden="true"><img src="' . esc_url( $apple_icon_url ) . '" alt="" /></span>';
		echo '<div class="artspay-express-settings__text">';
		echo '<div class="artspay-express-settings__label">' . esc_html__( 'Google Pay & Apple Pay', 'artspay' ) . '</div>';
		echo '<div class="artspay-express-settings__meta">' . esc_html__( 'Enable wallets, set a shared button theme and action, and preview both buttons together.', 'artspay' ) . '</div>';
		echo '</div>';
		echo '</div>';
		echo '<div class="artspay-express-settings__right">';
		echo '<a class="button button-secondary" href="' . esc_url( $customize_link ) . '">' . esc_html__( 'Customize', 'artspay' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render the unified Express checkouts detail “page” within the settings screen.
	 *
	 * @param string $express_page Expected: express_checkouts.
	 */
	private function render_express_checkout_detail_page( string $express_page ): void {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fatzebra' );
		$back_url     = $settings_url . '#artspay-express-checkouts';

		if ( 'express_checkouts' !== $express_page ) {
			return;
		}

		echo '<div class="artspay-express-checkouts-page" id="artspay-express-checkouts">';
		echo '<input type="hidden" name="artspay_express_save_context" value="express_checkouts" />';

		// Apple Pay — aside + card (matches main gateway section layout).
		echo '<div class="artspay-gateway-settings__section artspay-gateway-settings__section--express">';
		echo '<div class="artspay-gateway-settings__aside">';
		echo '<h2 class="artspay-gateway-settings__heading">' . esc_html__( 'Apple Pay', 'artspay' ) . '</h2>';
		echo '<p class="artspay-gateway-settings__description">' . esc_html__( 'Offer Apple Pay on cart and checkout where the browser supports it. Live stores need HTTPS and Apple Pay domain verification.', 'artspay' ) . '</p>';
		echo '</div>';
		echo '<div class="artspay-gateway-settings__main"><div class="artspay-gateway-settings__card">';
		echo '<table class="form-table">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce core field markup.
		echo $this->get_settings_html_for_field_keys( array( 'apple_pay_enabled' ) );
		echo '</table>';
		$this->render_apple_pay_admin_help_section();
		echo '</div></div></div>';

		// Google Pay.
		echo '<div class="artspay-gateway-settings__section artspay-gateway-settings__section--express">';
		echo '<div class="artspay-gateway-settings__aside">';
		echo '<h2 class="artspay-gateway-settings__heading">' . esc_html__( 'Google Pay', 'artspay' ) . '</h2>';
		echo '<p class="artspay-gateway-settings__description">' . esc_html__( 'Enable Google Pay and add your Fat Zebra Google Pay merchant ID so the wallet can appear on cart and checkout.', 'artspay' ) . '</p>';
		echo '</div>';
		echo '<div class="artspay-gateway-settings__main"><div class="artspay-gateway-settings__card">';
		echo '<table class="form-table">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce core field markup.
		echo $this->get_settings_html_for_field_keys( array( 'google_pay_enabled', 'google_pay_merchant_id', 'google_pay_google_merchant_id' ) );
		echo '</table>';
		echo '</div></div></div>';

		// Shared button appearance + previews.
		echo '<div class="artspay-gateway-settings__section artspay-gateway-settings__section--express">';
		echo '<div class="artspay-gateway-settings__aside">';
		echo '<h2 class="artspay-gateway-settings__heading">' . esc_html__( 'Button appearance', 'artspay' ) . '</h2>';
		echo '<p class="artspay-gateway-settings__description">' . esc_html__( 'Choose the label and theme used by both Google Pay and Apple Pay express buttons on your storefront.', 'artspay' ) . '</p>';
		echo '</div>';
		echo '<div class="artspay-gateway-settings__main"><div class="artspay-gateway-settings__card">';
		echo '<table class="form-table">';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce core field markup.
		echo $this->get_settings_html_for_field_keys( array( 'google_pay_button_action', 'google_pay_theme' ) );
		echo '</table>';
		$this->render_express_checkouts_dual_preview_section();
		echo '</div></div></div>';

		echo '</div>';
	}

	/**
	 * Apple Pay onboarding checklist (Apple Pay on the Web).
	 */
	private function render_apple_pay_admin_help_section(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- admin settings UI.
		$result_key = isset( $_GET['artspay_applepay_result'] ) ? sanitize_key( (string) wp_unslash( $_GET['artspay_applepay_result'] ) ) : '';
		$result     = ApplePayAdmin::get_result_for_current_user();

		$canonical_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$canonical_host = strtolower( trim( $canonical_host ) );
		$domain_display  = '' !== $canonical_host ? $canonical_host : __( 'your domain', 'artspay' );

		$prod_file    = 'https://paynow.pmnts.io/apple_pay/domain_verification/production.txt';
		$sandbox_file = 'https://paynow.pmnts-sandbox.io/apple_pay/domain_verification/sandbox.txt';

		$https_ok      = ApplePayAdmin::is_home_https();
		$verified_url  = ApplePayAdmin::is_association_file_ok_for_status( $this );
		$registered_ok = ApplePayAdmin::is_domain_registered_ok_for_current_user();

		echo '<hr />';
		echo '<h3>' . esc_html__( 'Installing Apple Pay', 'artspay' ) . '</h3>';

		echo '<ul class="artspay-applepay-status" style="list-style:none;margin:12px 0;padding:0;max-width:640px;">';
		echo '<li style="display:flex;align-items:center;gap:10px;margin:8px 0;">';
		$this->render_apple_pay_status_icon( $https_ok );
		echo '<span>' . esc_html__( 'HTTPS certificate available', 'artspay' ) . '</span></li>';
		echo '<li style="display:flex;align-items:center;gap:10px;margin:8px 0;">';
		$this->render_apple_pay_status_icon( $verified_url );
		echo '<span>' . esc_html__( 'Apple Pay verification URL verified', 'artspay' ) . '</span></li>';
		echo '<li style="display:flex;align-items:center;gap:10px;margin:8px 0;">';
		$this->render_apple_pay_status_icon( $registered_ok );
		echo '<span>';
		echo esc_html(
			sprintf(
				/* translators: %s: domain hostname, e.g. example.com */
				__( 'Register %s with ArtsPay', 'artspay' ),
				$domain_display
			)
		);
		echo '</span></li>';
		echo '</ul>';

		$is_redirected = ( '' !== $result_key ) && is_array( $result );
		if ( $is_redirected ) {
			$ok    = ! empty( $result['ok'] );
			$act   = (string) ( $result['action'] ?? '' );
			$class = $ok ? 'notice-success' : 'notice-error';
			$msg   = '';
			if ( 'check' === $act ) {
				$msg = $ok
					? __( 'Verification file looks correct on your server.', 'artspay' )
					: __( 'Verification check did not pass. Fix the file or hosting, then run the check again.', 'artspay' );
				if ( ! $ok && ! empty( $result['error'] ) ) {
					$msg .= ' ' . (string) $result['error'];
				}
			} elseif ( 'register' === $act ) {
				$msg = $ok
					? __( 'Domain registration request succeeded.', 'artspay' )
					: __( 'Domain registration did not succeed. Try again or contact support if it persists.', 'artspay' );
				if ( ! $ok && ! empty( $result['error'] ) ) {
					$msg .= ' ' . (string) $result['error'];
				}
			}
			if ( '' !== $msg ) {
				echo '<div class="notice ' . esc_attr( $class ) . ' inline"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}

		echo '<h4 style="margin-top:1.25em;">' . esc_html__( 'Steps', 'artspay' ) . '</h4>';
		echo '<ol class="artspay-applepay-steps" style="max-width:720px;line-height:1.5;">';

		echo '<li>' . esc_html__( 'Download the correct verification file for your environment (sandbox vs production) and upload it so it is served at the Apple verification URL', 'artspay' );
		echo ' <code>/.well-known/apple-developer-merchantid-domain-association</code>. ';
		echo esc_html__( 'Files:', 'artspay' ) . ' ';
		echo '<a href="' . esc_url( $sandbox_file ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sandbox', 'artspay' ) . '</a>';
		echo ' · ';
		echo '<a href="' . esc_url( $prod_file ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Production', 'artspay' ) . '</a>';
		echo '</li>';

		echo '<li>' . esc_html__( 'Once the file has been uploaded to the above location, run a verification check.', 'artspay' ) . ' ';
		echo '<button type="submit" form="artspay-applepay-check-form" class="button button-link">' . esc_html__( 'Run verification check', 'artspay' ) . '</button>';
		echo '</li>';

		echo '<li>' . esc_html__( 'When the file is successfully verified on your system, register your domain with ArtsPay to finalise API domain registration.', 'artspay' ) . ' ';
		echo '<button type="submit" form="artspay-applepay-register-form" class="button button-primary">' . esc_html__( 'Register domain with ArtsPay', 'artspay' ) . '</button>';
		echo ' ' . esc_html__( 'You should see a green tick when this step succeeds.', 'artspay' );
		echo '</li>';

		echo '</ol>';

		echo '<p class="description" style="max-width:720px;margin-top:1em;">' . esc_html__( 'You are all set. Click “Enable Apple Pay” above to enable it as a payment method on checkout.', 'artspay' ) . '</p>';
	}

	/**
	 * Green tick or red cross for Apple Pay status rows (admin has dashicons).
	 *
	 * @param bool $ok Whether the item is satisfied.
	 */
	private function render_apple_pay_status_icon( bool $ok ): void {
		if ( $ok ) {
			echo '<span class="dashicons dashicons-yes-alt" style="color:#007017;font-size:20px;width:22px;height:22px;" aria-hidden="true"></span>';
			echo '<span class="screen-reader-text">' . esc_html__( 'Done', 'artspay' ) . '</span>';
		} else {
			echo '<span class="dashicons dashicons-dismiss" style="color:#d63638;font-size:20px;width:22px;height:22px;" aria-hidden="true"></span>';
			echo '<span class="screen-reader-text">' . esc_html__( 'Not done', 'artspay' ) . '</span>';
		}
	}

	/**
	 * Wallet-related environment guidance (keep wizard copy focused; details live in docs).
	 */
	private function render_wallet_environment_notice(): void {
		// Only show this warning when the store is not on HTTPS (actionable).
		if ( is_ssl() ) {
			return;
		}

		$docs_url = plugins_url( 'docs/APPLE-PAY-WEB.md', ARTSPAY_PLUGIN_FILE );

		echo '<div class="notice notice-error inline"><p>';
		echo esc_html__( 'Live Google Pay and Apple Pay require HTTPS on your store. Apple Pay domain verification usually still expects a real HTTPS hostname even in sandbox.', 'artspay' );
		echo ' ';
		echo wp_kses_post(
			sprintf(
				/* translators: %s: documentation URL */
				__( 'Read more: <a href="%s" target="_blank" rel="noopener noreferrer">Apple Pay on the Web (WooCommerce)</a>.', 'artspay' ),
				esc_url( $docs_url )
			)
		);
		echo '</p></div>';
	}

	/**
	 * Builds our payment fields area - including tokenization fields for logged-in users,
	 * and the actual payment fields.
	 */
	public function payment_fields(): void {
		if ( $this->direct_post_enabled() ) {
			// Register and enqueue direct post handling script.
			$url = $this->get_direct_post_url();

			$return_path        = uniqid( 'fatzebra-nonce-' );
			$verification_value = hash_hmac( 'md5', $return_path, $this->get_gateway_credentials()['shared_secret'] );

			wp_register_script( 'fz-direct-post-handler', ARTSPAY_PLUGIN_URL . '/images/fatzebra.js', array( 'jquery' ), ARTSPAY_PLUGIN_VERSION, true );
			wp_localize_script(
				'fz-direct-post-handler',
				'fatzebra',
				array(
					'url'                => $url,
					'return_path'        => $return_path,
					'verification_value' => $verification_value,
				)
			);
			wp_enqueue_script( 'fz-direct-post-handler' );
		}

		$this->render_sandbox_checkout_notice();

		echo wp_kses_post( $this->get_card_logos_html() );
		echo "<input type='hidden' name='fatzebra-token' id='fatzebra-token' /><span class='payment-errors required'></span>";

		// Add captcha field if enabled.
		if ( $this->is_captcha_enabled() ) {
			$this->render_captcha_field();
			$this->enqueue_captcha_scripts();
		}

		$this->form();
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array|void
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;

		if ( $this->is_captcha_enabled() ) {
			$captcha_token = filter_input( INPUT_POST, 'captcha_token', FILTER_UNSAFE_RAW );

			if ( empty( $captcha_token ) ) {
				wc_add_notice( $this->get_captcha_error_message(), 'error' );
				return;
			}

			if ( ! $this->verify_captcha_token( $captcha_token ) ) {
				wc_add_notice( $this->get_captcha_error_message(), 'error' );
				return;
			}
		}

		$order = new WC_Order( $order_id );
		$this->params['currency'] = $order->get_currency();
		$ip                        = $this->get_order_ip_address( $order );
		$this->params['customer_ip'] = $ip; // Backwards compatible with existing payloads.
		$this->params['ip_address']  = $ip;
		$this->params['metadata']    = $this->build_fatzebra_metadata_for_order( $order );

		// Wallet checkout: token is set by our express buttons.
		$googlepay_token = filter_input( INPUT_POST, 'googlepay-token', FILTER_UNSAFE_RAW );
		if ( ! empty( $googlepay_token ) ) {
			$defer_payment = 'no';

			$this->params['amount']      = $this->convert_to_cents( $order->get_total() );
			$this->params['reference']   = (string) $order_id;
			$this->params['test']        = $this->is_sandbox_mode();
			$this->params['deferred']    = false;

			$this->params['card_holder'] = trim( (string) $order->get_formatted_billing_full_name() );
			if ( '' === $this->params['card_holder'] ) {
				$this->params['card_holder'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			}

			$decoded = $this->base64_url_decode( (string) $googlepay_token );
			if ( false === $decoded || '' === $decoded ) {
				wc_add_notice( __( 'Google Pay did not return a valid payment token. Please try again.', 'artspay' ), 'error' );
				return;
			}
			// Google Pay tokens are often JSON strings; Fat Zebra expects the JSON object form.
			$decoded_json = json_decode( (string) $decoded );
			if ( null !== $decoded_json && JSON_ERROR_NONE === json_last_error() ) {
				$this->params['wallet_token'] = $decoded_json;
			} else {
				$this->params['wallet_token'] = $decoded;
			}

			if ( $this->fraud_detection_enabled() ) {
				$this->params['fraud'] = $this->get_fraud_payload( $order );
			}

			$result = $this->do_wallet_payment( $this->params, 'GOOGLE' );
		} else {
			$applepay_token = filter_input( INPUT_POST, 'applepay-token', FILTER_UNSAFE_RAW );
			if ( ! empty( $applepay_token ) ) {
				$defer_payment = 'no';

				$this->params['amount']    = $this->convert_to_cents( $order->get_total() );
				$this->params['reference'] = (string) $order_id;
				$this->params['test']      = $this->is_sandbox_mode();
				$this->params['deferred']  = false;

				$this->params['card_holder'] = trim( (string) $order->get_formatted_billing_full_name() );
				if ( '' === $this->params['card_holder'] ) {
					$this->params['card_holder'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
				}

				$decoded = $this->base64_url_decode( (string) $applepay_token );
				if ( false === $decoded || '' === $decoded ) {
					wc_add_notice( __( 'Apple Pay did not return a valid payment token. Please try again.', 'artspay' ), 'error' );
					return;
				}

				$decoded_json = json_decode( (string) $decoded );
				if ( null !== $decoded_json && JSON_ERROR_NONE === json_last_error() ) {
					$this->params['wallet_token'] = $decoded_json;
				} else {
					$this->params['wallet_token'] = $decoded;
				}

				if ( $this->fraud_detection_enabled() ) {
					$this->params['fraud'] = $this->get_fraud_payload( $order );
				}

				// Fat Zebra distinguishes Apple Pay in-app ('APPLE') vs Apple Pay on the Web ('APPLEPAYWEB').
				$result = $this->do_wallet_payment( $this->params, 'APPLEPAYWEB' );
			} else {
			if ( $this->direct_post_enabled() ) {
				$this->params['card_token'] = $_POST['fatzebra-token'];
			} else {
				$this->params['card_number'] = str_replace( ' ', '', $_POST['fatzebra-card-number'] );
				if ( ! isset( $_POST['fatzebra-card-number'] ) ) {
					$this->params['card_number'] = $_POST['cardnumber'];
				}

				$this->params['cvv'] = $_POST['fatzebra-card-cvc'];
				if ( ! isset( $_POST['fatzebra-card-cvc'] ) ) {
					$this->params['cvv'] = $_POST['card_cvv'];
				}

				if ( isset( $_POST['fatzebra-card-expiry'] ) && ! empty( $_POST['fatzebra-card-expiry'] ) ) {
					list($exp_month, $exp_year) = explode( '/', $_POST['fatzebra-card-expiry'] );
				} else {
					$exp_month = $_POST['card_expiry_month'];
					$exp_year  = $_POST['card_expiry_year'];
				}
				$this->params['card_expiry'] = trim( $exp_month ) . '/' . ( 2000 + intval( $exp_year ) );

				$this->params['card_holder'] = $_POST['billing_first_name'] . ' ' . $_POST['billing_last_name'];
			}

			$defer_payment = $this->settings['deferred_payments'];

			if ( class_exists( 'WC_Subscriptions_Order' ) && wcs_order_contains_subscription( $order ) ) {
				// No deferred payments for subscriptions.
				$defer_payment = 'no';
			}

			Debug_Logger::log(
				sprintf(
					/* translators: 1: order id, 2: sandbox yes/no, 3: deferred yes/no */
					__( 'Checkout payment started for order #%1$s (sandbox=%2$s, deferred=%3$s).', 'artspay' ),
					(string) $order_id,
					$this->is_sandbox_mode() ? 'yes' : 'no',
					( 'yes' === $defer_payment ) ? 'yes' : 'no'
				),
				'debug'
			);

			// Charge sign up fee + first period here...
			// Periodic charging should happen via scheduled_subscription_payment_fatzebra.
			$this->params['amount'] = $this->convert_to_cents( $order->get_total() );

			$this->params['reference'] = (string) $order_id;
			$this->params['test']      = $this->is_sandbox_mode();
			$this->params['deferred']  = 'yes' === $defer_payment;

			// If the customer is updating their details the $_POST values for name will be missing, so fetch from the order.
			if ( '' === trim( $this->params['card_holder'] ?? '' ) ) {
				$this->params['card_holder'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
			}

			if ( $this->fraud_detection_enabled() ) {
				// Add in the fraud data payload.
				$fraud_data            = $this->get_fraud_payload( $order );
				$this->params['fraud'] = $fraud_data;
			}

			if ( 'yes' === $defer_payment ) {
				$result = $this->tokenize_card( $this->params );
			} else {
				$result = $this->do_payment( $this->params );
			}
			}
		}

		if ( is_wp_error( $result ) ) {
			$failure_code = (int) $result->get_error_code();
			if ( 3 === $failure_code ) {
				$this->log_payment_decline_for_debug( (int) $order_id, $result, 'checkout' );
			} else {
				Debug_Logger::log(
					sprintf(
						/* translators: 1: order id, 2: error code */
						__( 'Checkout payment failed for order #%1$s (error code %2$s).', 'artspay' ),
						(string) $order_id,
						(string) $failure_code
					),
					'debug'
				);
			}
			switch ( $result->get_error_code() ) {
				case 1: // Non-200 response, so failed... (e.g. 401, 403, 500 etc).
					$order->add_order_note( $result->get_error_message() );
					wc_add_notice( $result->get_error_message(), 'error' );
					break;

				case 2: // Gateway error (data etc).
					$errors = $result->get_error_data();
					foreach ( $errors as $error ) {
						$order->add_order_note( 'Gateway Error: ' . $error );
					}

					wc_add_notice( 'Payment Failed: ' . implode( ', ', $errors ), 'error' );
					break;

				case 3: // Declined - error data may include response_data from the gateway.
					$err_data    = $result->get_error_data();
					$resp_wrap   = ( is_array( $err_data ) && isset( $err_data['response_data'] ) ) ? $err_data['response_data'] : null;
					$decline_msg = $result->get_error_message();
					if ( $resp_wrap && isset( $resp_wrap->response->message ) ) {
						$decline_msg = (string) $resp_wrap->response->message;
					}
					if ( $resp_wrap && isset( $resp_wrap->response->fraud_result ) && 'Error' === $resp_wrap->response->fraud_result ) {
						wc_add_notice( esc_html__( 'Fraud screening could not be completed. Please try again or use another payment method.', 'artspay' ), 'error' );
					} else {
						/* translators: %s: gateway decline message */
						wc_add_notice( sprintf( esc_html__( 'Payment declined: %s', 'artspay' ), wp_strip_all_tags( $decline_msg ) ), 'error' );
					}
					if ( $resp_wrap && isset( $resp_wrap->response ) ) {
						$this->persist_fraud_order_meta( $order, $resp_wrap );
						$this->add_forter_order_notes( $order, $resp_wrap->response );
						if ( isset( $resp_wrap->response->fraud_result ) && 'Deny' === $resp_wrap->response->fraud_result ) {
							$this->notify_forter_decline( $order, $resp_wrap->response );
						}
					}
					break;

				case 4: // Exception caught, something bad happened. Data is exception.
				default:
					wc_add_notice( 'Unknown error.', 'error' );
					$order->add_order_note(
						sprintf( /* translators: %s is the error data */
							__( 'Unknown Error (exception): %s', 'artspay' ),
							print_r( $result->get_error_data(), true ) // phpcs:ignore WordPress.PHP.DevelopmentFunctions
						)
					);
					break;
			}
		} else { // Success! Returned is an array with the transaction ID etc
			$tx_label = isset( $result['transaction_id'] ) ? (string) $result['transaction_id'] : __( 'deferred/tokenize', 'artspay' );
			Debug_Logger::log(
				sprintf(
					/* translators: 1: order id, 2: transaction id or label */
					__( 'Checkout payment completed for order #%1$s (transaction=%2$s).', 'artspay' ),
					(string) $order_id,
					$tx_label
				),
				'debug'
			);
			// For a deferred payment we set the status to on-hold and then add a detailed note for review.
			if ( isset( $defer_payment ) && 'yes' === $defer_payment ) {
				$date = new DateTime( $result['card_expiry'], new DateTimeZone( 'Australia/Sydney' ) );
				$note = 'Deferred Payment:<ul><li>Card Token: ' . $result['card_token'] . '</li><li>Cardholder: ' . $result['card_holder'] . '</li><li>Card Number: ' . $result['card_number'] . '</li><li>Expiry: ' . $date->format( 'm/Y' ) . '</li></ul>';
				$order->update_status( 'on-hold', $note );
				update_post_meta( $order->get_id(), '_fatzebra_card_token', $result['card_token'] );
				update_post_meta( $order->get_id(), 'Card Token', $result['card_token'] );
				update_post_meta( $order->get_id(), 'reference', $this->params['reference'] );
				update_post_meta( $order->get_id(), 'amount', $this->params['amount'] );
				update_post_meta( $order->get_id(), 'currency', get_option( 'woocommerce_currency' ) );
				update_post_meta( $order->get_id(), 'fraud_data', 'yes' === $this->settings['fraud_data'] ? 'true' : 'false' );
			} else {
				if ( 0 === $this->params['amount'] ) {
					$order->add_order_note(
						sprintf( /* translators: %s is the card token */
							__( 'Payment complete - $0 initial amount, card tokenized. Card token: %s', 'artspay' ),
							$result['card_token']
						)
					);
				}

				if ( ! empty( $result['response_data'] ) && isset( $result['response_data']->response ) ) {
					$this->persist_fraud_order_meta( $order, $result['response_data'] );
					$this->add_forter_order_notes( $order, $result['response_data']->response );
				}

				$order->payment_complete( $result['transaction_id'] );

				// Store the card token as post meta.
				update_post_meta( $order_id, '_fatzebra_card_token', $result['card_token'] );
				update_post_meta( $order_id, 'Card Token', $result['card_token'] );
				update_post_meta( $order_id, 'Transaction ID', $result['transaction_id'] );
			}
			$woocommerce->cart->empty_cart();

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}
	}

	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param int        $order_id Order ID.
	 * @param float|null $amount Refund amount.
	 * @param string     $reason Refund reason.
	 *
	 * @return bool|WP_Error True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ): WP_Error|bool {
		$order = new WC_Order( $order_id );

		$params = array(
			'amount'         => $this->convert_to_cents( $amount ),
			'reference'      => $order_id . '-' . time(),
			'transaction_id' => $order->get_transaction_id(),
			'customer_ip'    => $this->get_customer_real_ip(),
			'currency'       => $order->get_currency(),
			'test'           => $this->is_sandbox_mode(),
		);

		$client      = new Client();
		$credentials = $this->get_gateway_credentials();

		$result = $client->refund( $params, $credentials, $this->is_sandbox_mode() );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			return false;
		}

		if ( $result['successful'] ) {
			wc_add_notice( $result['message'] );
			$order->add_order_note( 'Refund for ' . $amount . ' successful. Refund ID: ' . $result['refund_id'] );
			return true;
		}

		return false;
	}

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page(): void {
		if ( $this->description ) {
			echo wp_kses_post( wpautop( wptexturize( $this->description ) ) );
		}
	}

	/**
	 * Process the subscription payment (manually... well via wp_cron)
	 *
	 * @param float    $amount_to_charge The amount for this payment.
	 * @param WC_Order $order            The order object.
	 * @param int      $product_id       The product ID.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order, $product_id ): void {
		$this->params              = array();
		$this->params['amount']    = $this->convert_to_cents( $amount_to_charge );
		$this->params['test']      = $this->is_sandbox_mode();
		$this->params['reference'] = $order->get_id() . '-' . gmdate( 'dmY' ); // Reference for order ID 123 will become 123-01022012.

		$token = get_post_meta( $order->get_id(), '_fatzebra_card_token', true );
		if ( empty( $token ) ) {
			$token = get_post_meta( $order->get_id(), 'fatzebra_card_token', true );
		}

		$this->params['card_token']  = $token;
		$ip                          = $this->get_order_ip_address( $order );
		$this->params['customer_ip'] = $ip; // Backwards compatible with existing payloads.
		$this->params['ip_address']  = $ip;
		$this->params['metadata']    = $this->build_fatzebra_metadata_for_order( $order );
		$this->params['deferred']    = false;
		$result                      = $this->do_payment( $this->params );

		if ( is_wp_error( $result ) ) {
			$txn_id = 'None';
			switch ( $result->get_error_code() ) {
				case 1: // Non-200 response, so failed... (e.g. 401, 403, 500 etc).
					$error = $result->get_error_message();
					break;

				case 2: // Gateway error (data etc).
					$errors = $result->get_error_data();
					$error  = implode( ', ', $errors );
					break;

				case 3: // Declined - may include full gateway response for Forter notes.
					$this->log_payment_decline_for_debug( $order->get_id(), $result, 'subscription_renewal' );
					$err_sub   = $result->get_error_data();
					$resp_sub  = ( is_array( $err_sub ) && isset( $err_sub['response_data'] ) ) ? $err_sub['response_data'] : null;
					$error     = $result->get_error_message();
					$txn_id    = 'None';
					if ( $resp_sub && isset( $resp_sub->response->message ) ) {
						$error = (string) $resp_sub->response->message;
					}
					if ( $resp_sub && isset( $resp_sub->response->id ) ) {
						$txn_id = (string) $resp_sub->response->id;
					}
					if ( $resp_sub && isset( $resp_sub->response ) ) {
						$this->persist_fraud_order_meta( $order, $resp_sub );
						$this->add_forter_order_notes( $order, $resp_sub->response );
						if ( isset( $resp_sub->response->fraud_result ) && 'Deny' === $resp_sub->response->fraud_result ) {
							$this->notify_forter_decline( $order, $resp_sub->response );
						}
					}
					break;

				case 4: // Exception caught, something bad happened. Data is exception.
				default:
					$error = 'Unknown - Error - See error log';
					break;
			}

			// Add the error details and return.
			$order->add_order_note(
				sprintf( /* translators: %1$s - error, %2$s - transaction ID */
					esc_html__( 'Subscription Payment Failed: %1$s. Transaction ID: %2$s', 'artspay' ),
					$error,
					$txn_id
				)
			);
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
		} else { // Success! Returned is an array with the transaction ID etc
			if ( ! empty( $result['response_data'] ) && isset( $result['response_data']->response ) ) {
				$this->persist_fraud_order_meta( $order, $result['response_data'] );
				$this->add_forter_order_notes( $order, $result['response_data']->response );
			}
			$order->add_order_note(
				sprintf( /* translators: %s - transaction ID */
					__( 'Subscription Payment Successful. Transaction ID: %s', 'artspay' ),
					$result['transaction_id']
				)
			);
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		}
	}

	/**
	 * Process payment.
	 *
	 * @param array $params Parameters array.
	 *
	 * @return array|WP_Error
	 */
	public function do_payment( array $params ): WP_Error|array {
		$client      = new Client();
		$credentials = $this->get_gateway_credentials();

		if ( $this->fraud_detection_enabled() ) {
			$order = $this->get_order_for_fraud_reference( isset( $params['reference'] ) ? (string) $params['reference'] : '' );
			if ( $order ) {
				$params['fraud'] = $this->get_fraud_payload( $order );
			}
		}

		return $client->purchase( $params, $credentials, $this->is_sandbox_mode() );
	}

	/**
	 * Process a wallet purchase (Google Pay / Apple Pay) using Fat Zebra wallet payload.
	 *
	 * @param array  $params      Parameters (must include wallet_token).
	 * @param string $wallet_type Wallet type, e.g. GOOGLE or APPLE.
	 *
	 * @return array|WP_Error
	 */
	public function do_wallet_payment( array $params, string $wallet_type = 'GOOGLE' ): WP_Error|array {
		$client      = new Client();
		$credentials = $this->get_gateway_credentials();

		$params['wallet_type'] = $wallet_type;

		return $client->purchase_with_wallet( $params, $credentials, $this->is_sandbox_mode() );
	}

	/**
	 * Perform tokenized payment.
	 *
	 * @param array $params Parameters.
	 *
	 * @return array|WP_Error
	 */
	public function do_tokenized_payment( array $params ): array|WP_Error {
		$client      = new Client();
		$credentials = $this->get_gateway_credentials();

		return $client->purchase_with_token( $params, $credentials, $this->is_sandbox_mode(), $this->fraud_detection_enabled() );
	}

	/**
	 * Tokenize card.
	 *
	 * @param array $params Parameters.
	 *
	 * @return array|WP_Error
	 */
	public function tokenize_card( array $params ): WP_Error|array {
		$order = new WC_Order( $params['reference'] );

		if ( ! isset( $params['customer_ip'] ) ) {
			$params['customer_ip'] = $order->get_customer_ip_address() ?? '127.0.0.1';
		}

		$payload = array(
			'card_holder' => $params['card_holder'],
			'card_number' => $params['card_number'],
			'card_expiry' => $params['card_expiry'],
			'cvv'         => $params['cvv'],
			'customer_ip' => $params['customer_ip'],
		);

		$client      = new Client();
		$credentials = $this->get_gateway_credentials();

		$result = $client->tokenize( $payload, $credentials, $this->is_sandbox_mode() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_post_meta( $order->get_id(), 'Card Token', $result['card_token'] );
		update_post_meta( $order->get_id(), 'reference', $params['reference'] );
		update_post_meta( $order->get_id(), 'amount', $params['amount'] );
		update_post_meta( $order->get_id(), 'currency', $params['currency'] );
		update_post_meta( $order->get_id(), 'fraud_data', isset( $params['fraud'] ) );

		return $result;
	}

	/**
	 * Resolve a WC order from a gateway reference (order ID or subscription renewal format "123-ddmmyyyy").
	 *
	 * @param string $reference Reference string.
	 *
	 * @return WC_Order|null
	 */
	private function get_order_for_fraud_reference( string $reference ): ?WC_Order {
		$reference = trim( $reference );
		if ( '' === $reference ) {
			return null;
		}
		if ( is_numeric( $reference ) ) {
			$order = wc_get_order( (int) $reference );
			return $order instanceof WC_Order ? $order : null;
		}
		if ( preg_match( '/^(\d+)-/', $reference, $m ) ) {
			$order = wc_get_order( (int) $m[1] );
			return $order instanceof WC_Order ? $order : null;
		}
		$order = wc_get_order( $reference );
		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Map WooCommerce country code to ISO 3166 alpha-3 for Fat Zebra / Forter.
	 *
	 * @param string $wc_country Two-letter code.
	 *
	 * @return string
	 */
	private function iso3_country_or_default( string $wc_country ): string {
		$code = strtoupper( trim( $wc_country ) );
		if ( isset( $this->country_map[ $code ] ) ) {
			return $this->country_map[ $code ];
		}
		if ( strlen( $code ) === 3 && ctype_alpha( $code ) ) {
			return $code;
		}
		return 'AUS';
	}

	/**
	 * Build Fat Zebra metadata for a WooCommerce order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return array{id:string,description:string,service:string,order_link:string}
	 */
	public function build_fatzebra_metadata_for_order( WC_Order $order ): array {
		$order_link = '';
		if ( method_exists( $order, 'get_edit_order_url' ) ) {
			$order_link = trim( (string) $order->get_edit_order_url() );
		}
		if ( '' === $order_link ) {
			$order_link = (string) admin_url( 'edit.php?post_type=shop_order' );
		}

		return array(
			'id'          => (string) $order->get_order_number(),
			'description' => $this->build_order_items_summary( $order, 128 ),
			'service'     => 'woocommerce',
			'order_link'  => $order_link,
		);
	}

	/**
	 * Get the best available customer IP address for an order.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	public function get_order_ip_address( WC_Order $order ): string {
		$ip = $order->get_customer_ip_address();
		$ip = is_string( $ip ) ? trim( $ip ) : '';
		if ( '' !== $ip ) {
			return $ip;
		}

		$ip = trim( (string) $this->get_customer_real_ip() );
		return '' !== $ip ? $ip : '127.0.0.1';
	}

	/**
	 * Build a short order item description, max length enforced.
	 *
	 * @param WC_Order $order   Order.
	 * @param int      $max_len Max length in characters.
	 *
	 * @return string
	 */
	private function build_order_items_summary( WC_Order $order, int $max_len = 128 ): string {
		$parts = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! is_object( $item ) || ! method_exists( $item, 'get_name' ) ) {
				continue;
			}
			$name = trim( wp_strip_all_tags( (string) $item->get_name() ) );
			if ( '' === $name ) {
				continue;
			}
			$qty = 1;
			if ( method_exists( $item, 'get_quantity' ) ) {
				$qty = (int) $item->get_quantity();
			}
			$parts[] = $qty > 1 ? ( $name . ' x' . $qty ) : $name;
		}

		$summary = implode( ', ', $parts );
		$summary = preg_replace( '/\s+/', ' ', (string) $summary );
		$summary = trim( (string) $summary );
		if ( '' === $summary ) {
			$summary = sprintf( __( 'Order %s', 'artspay' ), (string) $order->get_order_number() );
		}

		return $this->truncate_string( $summary, $max_len );
	}

	/**
	 * Truncate a string to max length (characters), adding an ellipsis when needed.
	 *
	 * @param string $value   Value.
	 * @param int    $max_len Max length.
	 *
	 * @return string
	 */
	private function truncate_string( string $value, int $max_len ): string {
		$value = trim( $value );
		if ( $max_len <= 0 ) {
			return '';
		}

		$len_fn = function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen';
		$sub_fn = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';

		if ( $len_fn( $value ) <= $max_len ) {
			return $value;
		}

		$ellipsis = '...';
		if ( $max_len <= $len_fn( $ellipsis ) ) {
			return $sub_fn( $value, 0, $max_len );
		}

		return rtrim( $sub_fn( $value, 0, $max_len - $len_fn( $ellipsis ) ) ) . $ellipsis;
	}

	/**
	 * Phone for Forter (required field); prefers billing, then shipping.
	 *
	 * @param WC_Order $order Order.
	 *
	 * @return string
	 */
	private function fraud_phone_for_order( WC_Order $order ): string {
		$phone = $order->get_billing_phone();
		if ( '' === trim( (string) $phone ) ) {
			$phone = $order->get_shipping_phone();
		}
		$phone = trim( (string) $phone );
		return '' !== $phone ? $phone : '0';
	}

	/**
	 * Builds the fraud payload for the request.
	 *
	 * @param WC_Order $order the order to build the payload against.
	 */
	public function get_fraud_payload( WC_Order $order ): array {
		$fraud_data = array(
			'customer'         => $this->get_fraud_customer( $order ),
			'items'            => $this->get_fraud_items( $order ),
			'shipping_address' => $this->get_fraud_shipping( $order ),
			'website'          => get_site_url(),
		);

		if ( 'yes' === $this->settings['fraud_device_id'] ) {
			$fraud_data['device_id'] = get_post_meta( $order->get_id(), 'device_id', true );
		}

		/**
		 * Optional extra recipients for Forter (e.g. gift orders). Return a non-empty array to include.
		 *
		 * @param array    $recipients Default empty.
		 * @param WC_Order $order      Order.
		 */
		$recipients = apply_filters( 'artspay_fraud_recipients', array(), $order );
		if ( is_array( $recipients ) && array() !== $recipients ) {
			$fraud_data['recipients'] = $recipients;
		}

		return $fraud_data;
	}

	/**
	 * Fetches the customer details for the fraud check request.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return array
	 */
	private function get_fraud_customer( WC_Order $order ): array {
		return array(
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'country'    => $this->iso3_country_or_default( $order->get_billing_country() ),
			'email'      => $order->get_billing_email(),
			'first_name' => $order->get_billing_first_name(),
			'home_phone' => $this->fraud_phone_for_order( $order ),
			'last_name'  => $order->get_billing_last_name(),
			'post_code'  => $order->get_billing_postcode(),
		);
	}

	/**
	 * Fetches the item details from the order for the fraud check request.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return array
	 */
	private function get_fraud_items( WC_Order $order ): array {
		$data  = array();
		$items = $order->get_items();

		foreach ( $items as $item ) {
			// Fix - do not get product by variation_id.
			$product = new WC_Product( $item['product_id'] );
			$name    = $product->get_title();

			$data[] = array(
				'product_code' => (string) $product->get_id(),
				'sku'          => $product->get_sku(),
				'description'  => $name,
				'qty'          => $item['qty'],
				'cost'         => $product->get_price(),
				'line_total'   => $order->get_line_subtotal( $item ),
			);
		}

		return $data;
	}

	/**
	 * Fetches the shipping details from the order for the fraud check request
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return array
	 */
	private function get_fraud_shipping( WC_Order $order ): array {
		$ship_cc = $order->get_shipping_country();
		if ( '' === trim( $ship_cc ) ) {
			$ship_cc = $order->get_billing_country();
		}

		$data = array(
			'address_1'       => $order->get_shipping_address_1(),
			'address_2'       => $order->get_shipping_address_2(),
			'city'            => $order->get_shipping_city(),
			'state'           => $order->get_shipping_state(),
			'country'         => $this->iso3_country_or_default( $ship_cc ),
			'email'           => $order->get_billing_email(),
			'first_name'      => $order->get_shipping_first_name(),
			'home_phone'      => $this->fraud_phone_for_order( $order ),
			'last_name'       => $order->get_shipping_last_name(),
			'post_code'       => $order->get_shipping_postcode(),
			'shipping_method' => 'low_cost', // TODO: Shipping Method Map.
		);

		if ( empty( $data['email'] ) ) {
			$data['email'] = $order->get_billing_email();
		}

		return $data;
	}

	/**
	 * Log gateway decline (WP_Error code 3) with safe Forter / response fields when debug mode is on.
	 * Forter **Deny** is logged at level `error`; other declines at `debug`.
	 *
	 * @param int      $order_id Order ID.
	 * @param WP_Error $result   Decline error from {@see Client::purchase()}.
	 * @param string   $flow     `checkout` or `subscription_renewal`.
	 */
	private function log_payment_decline_for_debug( int $order_id, WP_Error $result, string $flow = 'checkout' ): void {
		if ( ! Debug_Logger::is_enabled() ) {
			return;
		}

		$payload         = $this->build_safe_decline_log_context( $result );
		$payload['flow'] = $flow;

		$level = ( isset( $payload['fraud_result'] ) && 'Deny' === $payload['fraud_result'] ) ? 'error' : 'debug';

		// Short message line only; full payload appears under "Additional context" in WC log viewer.
		Debug_Logger::log(
			sprintf(
				/* translators: %s: order ID */
				__( 'Payment declined (order #%s).', 'artspay' ),
				(string) $order_id
			),
			$level,
			array( 'decline' => $payload )
		);
	}

	/**
	 * Sanitized decline payload for logs (no card numbers or tokens).
	 *
	 * @param WP_Error $result Decline error.
	 *
	 * @return array<string, mixed> Filtered by `artspay_debug_decline_log_context`.
	 */
	private function build_safe_decline_log_context( WP_Error $result ): array {
		$data = $result->get_error_data();
		$ctx  = array();

		if ( ! is_array( $data ) ) {
			$ctx['note'] = 'invalid_error_data';
			return $ctx;
		}

		if ( isset( $data['message'] ) && is_scalar( $data['message'] ) ) {
			$ctx['gateway_message'] = (string) $data['message'];
		}
		if ( isset( $data['id'] ) && is_scalar( $data['id'] ) ) {
			$ctx['gateway_transaction_id'] = (string) $data['id'];
		}

		$resp_wrap = isset( $data['response_data'] ) ? $data['response_data'] : null;
		if ( $resp_wrap && isset( $resp_wrap->successful ) ) {
			$ctx['api_top_level_successful'] = (bool) $resp_wrap->successful;
		}

		if ( $resp_wrap && isset( $resp_wrap->response ) && is_object( $resp_wrap->response ) ) {
			$r = $resp_wrap->response;
			foreach ( array( 'fraud_result', 'message', 'id', 'authorization', 'card_type', 'reference' ) as $key ) {
				if ( ! isset( $r->$key ) ) {
					continue;
				}
				if ( is_scalar( $r->$key ) ) {
					$ctx[ $key ] = (string) $r->$key;
				}
			}
			if ( isset( $r->fraud_messages ) && is_array( $r->fraud_messages ) ) {
				$ctx['fraud_messages'] = array_map( 'strval', $r->fraud_messages );
			}
		}

		return apply_filters( 'artspay_debug_decline_log_context', $ctx, $result );
	}

	/**
	 * Store Forter/fraud outcome on the order for reporting.
	 *
	 * @param WC_Order     $order       Order.
	 * @param object|null  $response_data Full gateway JSON wrapper (successful top-level shape).
	 */
	private function persist_fraud_order_meta( WC_Order $order, ?object $response_data ): void {
		if ( ! $response_data || ! isset( $response_data->response ) ) {
			return;
		}
		$r = $response_data->response;
		if ( isset( $r->fraud_result ) ) {
			update_post_meta( $order->get_id(), '_artspay_fraud_result', sanitize_text_field( (string) $r->fraud_result ) );
		}
		if ( isset( $r->fraud_messages ) && is_array( $r->fraud_messages ) ) {
			update_post_meta( $order->get_id(), '_artspay_fraud_messages', sanitize_text_field( implode( '; ', $r->fraud_messages ) ) );
		}
	}

	/**
	 * Order notes for Forter / fraud outcome.
	 *
	 * @param WC_Order $order    Order.
	 * @param object   $response Inner gateway response object.
	 */
	private function add_forter_order_notes( WC_Order $order, object $response ): void {
		if ( empty( $response->fraud_result ) ) {
			return;
		}
		if ( 'Accept' === $response->fraud_result ) {
			$order->add_order_note( esc_html__( 'Forter fraud check: Accept', 'artspay' ) );
		} else {
			/* translators: %s: fraud result label */
			$order->add_order_note( sprintf( esc_html__( 'Forter fraud check: %s', 'artspay' ), sanitize_text_field( (string) $response->fraud_result ) ) );
		}
		if ( ! empty( $response->fraud_messages ) && is_array( $response->fraud_messages ) ) {
			$order->add_order_note( esc_html__( 'Forter messages: ', 'artspay' ) . implode( ', ', array_map( 'sanitize_text_field', $response->fraud_messages ) ) );
		}
	}

	/**
	 * Email site admin when Forter returns Deny.
	 *
	 * @param WC_Order $order    Order.
	 * @param object   $response Inner gateway response.
	 */
	private function notify_forter_decline( WC_Order $order, object $response ): void {
		if ( ! $this->forter_decline_notifications_enabled() ) {
			return;
		}
		$admin = get_option( 'admin_email' );
		if ( ! is_email( $admin ) ) {
			return;
		}
		$msgs = '';
		if ( ! empty( $response->fraud_messages ) && is_array( $response->fraud_messages ) ) {
			$msgs = implode( '; ', $response->fraud_messages );
		}
		/* translators: 1: site name, 2: order number */
		$subject = sprintf( esc_html__( '[%1$s] Forter decline — order %2$s', 'artspay' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $order->get_order_number() );
		$body    = sprintf(
			// translators: 1: newline, 2: order number, 3: messages, 4: admin order URL.
			esc_html__( 'A payment was declined with Forter result Deny.%1$sOrder: #%2$s%1$sMessages: %3$s%1$s%4$s', 'artspay' ),
			"\n",
			$order->get_order_number(),
			$msgs,
			$order->get_edit_order_url()
		);
		wp_mail( $admin, $subject, $body );
	}

	/**
	 * Generate card logos HTML.
	 *
	 * Logo height defaults to 24px (clamped 16–64). Override with the `artspay_card_logo_height` filter.
	 *
	 * @return string
	 */
	private function get_card_logos_html(): string {
		$logos = array(
			'visa'             => 'visa.png',
			'mastercard'       => 'mc.png',
			'american_express' => 'amex.png',
			'diners'           => 'diners.png',
			'jcb'              => 'jcb.png',
		);

		$height_px = (int) apply_filters( 'artspay_card_logo_height', 24 );
		$height_px = max( 16, min( 64, $height_px ) );

		$html = '';

		$selected = $this->settings['show_card_logos'] ?? array();
		if ( ! is_array( $selected ) ) {
			$selected = array();
		}
		foreach ( $selected as $value ) {
			if ( isset( $logos[ $value ] ) ) {
				$html .= sprintf(
					'<img class="artspay-card-logo" src="%s" alt="%s" height="%3$d" style="height:%3$dpx;width:auto;max-width:100%%;vertical-align:middle;margin-inline-end:6px;border-radius:3px;" loading="lazy" decoding="async" />',
					esc_url( ARTSPAY_PLUGIN_URL . 'assets/images/' . $logos[ $value ] ),
					esc_attr( $value ),
					$height_px
				);
			}
		}

		return $html;
	}
}
