<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * Google Pay express checkout (classic + shared assets).
 *
 * @package ArtsPay
 */

namespace ArtsPay\Express;

use ArtsPay\Assets\Core;
use ArtsPay\Gateways\CreditCardGateway;

/**
 * Google Pay express integration for the ArtsPay gateway.
 */
final class GooglePay {
	/**
	 * Gateway instance.
	 *
	 * @var CreditCardGateway
	 */
	private CreditCardGateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param CreditCardGateway $gateway Gateway.
	 */
	public function __construct( CreditCardGateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ), 20 );
		add_action( 'woocommerce_checkout_before_customer_details', array( $this, 'render_express_checkout_checkout' ), 5 );
		add_action( 'woocommerce_cart_totals_after_order_total', array( $this, 'render_express_checkout_cart' ), 15 );
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'render_checkout_wallet_token_fields' ), 5 );
	}

	/**
	 * Shared express checkout container renderer (Apple + Google in one row, single divider).
	 *
	 * Both wallet classes call this. We ensure only one prints per context.
	 *
	 * @param string $context checkout|cart|blocks-checkout|blocks-cart
	 * @param bool   $is_blocks Whether the mount ids should be blocks mounts.
	 */
	private function maybe_render_shared_express_container( string $context, bool $is_blocks = false ): void {
		$key = 'artspay_express_container_printed_' . $context;
		if ( ! empty( $GLOBALS[ $key ] ) ) {
			return;
		}
		$GLOBALS[ $key ] = true;

		$google_ok = $this->is_express_enabled();
		$apple_ok  = ( 'yes' === (string) $this->gateway->get_option( 'enabled', 'no' ) )
			&& ( 'yes' === (string) $this->gateway->get_option( 'apple_pay_enabled', 'no' ) )
			&& ( WC()->cart && ! WC()->cart->is_empty() )
			&& ( $this->gateway->is_sandbox_mode() || is_ssl() );

		if ( ! $google_ok && ! $apple_ok ) {
			return;
		}

		$wrap_variant = str_contains( $context, 'cart' ) ? 'cart' : 'checkout';
		$wrap_extra   = $is_blocks ? ' artspay-express-checkout--blocks' : '';

		echo '<div class="artspay-express-checkout artspay-express-checkout--' . esc_attr( $wrap_variant ) . $wrap_extra . '">';
		echo '<div class="artspay-express-checkout__buttons">';

		if ( $apple_ok ) {
			$id = $is_blocks
				? ( 'cart' === $wrap_variant ? 'artspay-express-applepay-blocks-cart' : 'artspay-express-applepay-blocks-checkout' )
				: ( 'cart' === $wrap_variant ? 'artspay-express-applepay-cart' : 'artspay-express-applepay-checkout' );
			echo '<div id="' . esc_attr( $id ) . '" class="artspay-express-applepay-mount"></div>';
		}

		if ( $google_ok ) {
			$id = $is_blocks
				? ( 'cart' === $wrap_variant ? 'artspay-express-googlepay-blocks-cart' : 'artspay-express-googlepay-blocks-checkout' )
				: ( 'cart' === $wrap_variant ? 'artspay-express-googlepay-cart' : 'artspay-express-googlepay-checkout' );
			echo '<div id="' . esc_attr( $id ) . '" class="artspay-express-googlepay-mount"></div>';
		}

		echo '</div>';
		echo '<p class="artspay-express-divider" role="separator"><span>' . esc_html__( '— OR —', 'artspay' ) . '</span></p>';
		echo '</div>';
	}

	/**
	 * Whether Google Pay express UI should be shown.
	 */
	public function is_express_enabled(): bool {
		if ( 'yes' !== (string) $this->gateway->get_option( 'enabled', 'no' ) ) {
			return false;
		}
		if ( 'yes' !== (string) $this->gateway->get_option( 'google_pay_enabled', 'no' ) ) {
			return false;
		}
		$merchant_id = trim( (string) $this->gateway->get_option( 'google_pay_merchant_id', '' ) );
		if ( '' === $merchant_id ) {
			return false;
		}
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}
		if ( ! $this->gateway->is_sandbox_mode() && ! is_ssl() ) {
			return false;
		}

		return true;
	}

	/**
	 * Enqueue express checkout assets on cart/checkout when enabled.
	 */
	public function maybe_enqueue_assets(): void {
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return;
		}
		if ( 'yes' !== (string) $this->gateway->get_option( 'enabled', 'no' ) ) {
			return;
		}

		$on_cart      = function_exists( 'is_cart' ) && is_cart();
		$on_checkout  = function_exists( 'is_checkout' ) && is_checkout();
		$on_order_pay = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' );

		if ( ! $on_cart && ! $on_checkout && ! $on_order_pay ) {
			return;
		}

		if ( ! $this->is_express_enabled() && ! $on_order_pay ) {
			return;
		}

		Core::register_scripts();

		wp_enqueue_style(
			'artspay-express-googlepay',
			ARTSPAY_PLUGIN_URL . 'assets/css/express-googlepay.css',
			array(),
			ARTSPAY_PLUGIN_VERSION
		);

		wp_enqueue_script( 'google-pay-api', 'https://pay.google.com/gp/p/js/pay.js', array( 'jquery' ), ARTSPAY_PLUGIN_VERSION, true );

		wp_enqueue_script(
			'artspay-checkout-autofill',
			ARTSPAY_PLUGIN_URL . 'assets/js/artspay-checkout-autofill.js',
			array( 'jquery', 'artspay-core' ),
			ARTSPAY_PLUGIN_VERSION,
			true
		);

		wp_enqueue_script(
			'artspay-googlepay',
			ARTSPAY_PLUGIN_URL . 'assets/js/artspay-googlepay.js',
			array( 'jquery', 'artspay-core', 'artspay-checkout-autofill', 'google-pay-api' ),
			ARTSPAY_PLUGIN_VERSION,
			true
		);

		wp_enqueue_script( 'artspay-core' );

		$cart_total = '0.00';
		if ( WC()->cart && ! WC()->cart->is_empty() ) {
			$cart_total = wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() );
		}

		$gateway_merchant_id = trim( (string) $this->gateway->get_option( 'google_pay_merchant_id', '' ) );
		$google_merchant_id  = trim( (string) $this->gateway->get_option( 'google_pay_google_merchant_id', '' ) );
		$merchant_name = get_bloginfo( 'name' );
		$button_action = (string) $this->gateway->get_option( 'google_pay_button_action', 'buy' );
		$button_type   = self::map_button_type_for_api( $button_action );
		$theme         = (string) $this->gateway->get_option( 'google_pay_theme', 'system' );
		$button_color  = 'default';
		if ( 'dark' === $theme ) {
			$button_color = 'black';
		} elseif ( 'light' === $theme ) {
			$button_color = 'white';
		}

		$mount_selectors = $on_order_pay ? '' : '#artspay-express-googlepay-checkout,#artspay-express-googlepay-cart,#artspay-express-googlepay-blocks-cart,#artspay-express-googlepay-blocks-checkout';

		$base_location     = function_exists( 'wc_get_base_location' ) ? wc_get_base_location() : array();
		$base_country      = isset( $base_location['country'] ) ? (string) $base_location['country'] : '';
		$allowed_countries = array();
		if ( '' !== $base_country ) {
			$allowed_countries[] = $base_country;
		}

		$is_blocks_checkout = $this->page_has_checkout_block();

		$google_pay_config = array(
			'gatewayMerchantId'             => $gateway_merchant_id,
			'allowedCardNetworks'           => array( 'VISA', 'MASTERCARD', 'AMEX', 'JCB' ),
			'environment'                   => $this->gateway->is_sandbox_mode() ? 'TEST' : 'PRODUCTION',
			'currencyCode'                  => get_woocommerce_currency(),
			'amount'                        => $cart_total,
			'googleMerchantId'              => $google_merchant_id,
			'googleMerchantName'            => $merchant_name,
			'buttonType'                    => $button_type,
			'buttonColor'                   => $button_color,
			'missingGatewayMerchantMessage' => __( 'Google Pay is not configured: add your Fat Zebra Google Pay merchant ID under WooCommerce > Settings > Payments > ArtsPay > Express checkouts.', 'artspay' ),
			'isCart'                        => $on_cart && ! $on_order_pay,
			'isCheckout'                    => $on_checkout && ! $on_order_pay && ! $is_blocks_checkout,
			'isBlocksCheckout'              => $on_checkout && ! $on_order_pay && $is_blocks_checkout,
			'checkoutUrl'                   => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			'cartUrl'                       => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'expressMountSelectors'         => $mount_selectors,
			'allowedCountryCodes'           => $allowed_countries,
			'strings'                       => array(
				'googlePayAuthorised' => __( 'Google Pay authorised. Please complete any required billing/shipping fields and click “Place order”.', 'artspay' ),
				'orDivider'           => __( '— OR —', 'artspay' ),
			),
			'selectors'                     => array(
				'paymentMethodFatzebra' => '#payment_method_fatzebra',
				'googlePayToken'        => '#googlepay-token',
				'expressHostCheckout'   => '.artspay-express-checkout--checkout',
			),
			'blockSelectors'            => array(
				'paymentMethodFatzebra' => '.wc-block-checkout input[value="fatzebra"]',
				'googlePayToken'        => '#googlepay-token',
			),
		);

		$config = array(
			'version'   => ARTSPAY_PLUGIN_VERSION,
			'config'    => array(
				'sandbox' => $this->gateway->is_sandbox_mode(),
			),
			'googlePay' => $google_pay_config,
		);

		wp_localize_script( 'artspay-core', 'artsPayLocalized', $config );
	}

	/**
	 * Whether the current page content includes the Cart block.
	 */
	private function page_has_cart_block(): bool {
		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_block( 'woocommerce/cart', $post );
	}

	/**
	 * Whether the current page content includes the Checkout block.
	 */
	private function page_has_checkout_block(): bool {
		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_block( 'woocommerce/checkout', $post );
	}

	/**
	 * Express Google Pay above billing / order columns (classic checkout).
	 */
	public function render_express_checkout_checkout(): void {
		if ( $this->page_has_checkout_block() ) {
			return;
		}
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}
		$this->maybe_render_shared_express_container( 'checkout', false );
	}

	/**
	 * Express Google Pay in cart totals (after estimated total).
	 */
	public function render_express_checkout_cart(): void {
		if ( $this->page_has_cart_block() ) {
			return;
		}
		if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
			return;
		}
		$this->maybe_render_shared_express_container( 'cart', false );
	}

	/**
	 * Wallet token hidden fields outside #order_review so they survive checkout AJAX updates.
	 */
	public function render_checkout_wallet_token_fields(): void {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}
		if ( $this->page_has_checkout_block() ) {
			return;
		}
		if ( ! $this->is_express_enabled() ) {
			return;
		}

		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;

		echo '<input type="hidden" name="googlepay-token" id="googlepay-token" value="" autocomplete="off" />';
	}

	/**
	 * Map gateway setting to Google Pay PaymentsClient.createButton buttonType.
	 *
	 * @see https://developers.google.com/pay/api/web/reference/object#ButtonOptions
	 *
	 * @param string $stored Raw option value.
	 * @return string Button type accepted by the Google Pay JS API.
	 */
	public static function map_button_type_for_api( string $stored ): string {
		$stored = strtolower( trim( $stored ) );

		$legacy = array(
			'icon_only' => 'plain',
			'buy_with'  => 'buy',
		);
		if ( isset( $legacy[ $stored ] ) ) {
			return $legacy[ $stored ];
		}

		$allowed = array( 'book', 'buy', 'checkout', 'donate', 'order', 'pay', 'plain', 'subscribe' );
		if ( in_array( $stored, $allowed, true ) ) {
			return $stored;
		}

		return 'buy';
	}
}
