<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * Apple Pay express checkout (classic + shared assets).
 *
 * @package ArtsPay
 */

namespace ArtsPay\Express;

use ArtsPay\Assets\Core;
use ArtsPay\Gateways\CreditCardGateway;

/**
 * Apple Pay express integration for the ArtsPay gateway.
 */
final class ApplePay {
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
	 * Whether Apple Pay express UI should be shown.
	 */
	public function is_express_enabled(): bool {
		if ( 'yes' !== (string) $this->gateway->get_option( 'enabled', 'no' ) ) {
			return false;
		}
		if ( 'yes' !== (string) $this->gateway->get_option( 'apple_pay_enabled', 'no' ) ) {
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
			'artspay-express-applepay',
			ARTSPAY_PLUGIN_URL . 'assets/css/express-applepay.css',
			array(),
			ARTSPAY_PLUGIN_VERSION
		);

		wp_register_script(
			'apple-pay-js-sdk',
			'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js',
			array(),
			null,
			true
		);
		wp_enqueue_script( 'apple-pay-js-sdk' );

		wp_enqueue_script(
			'artspay-applepay',
			ARTSPAY_PLUGIN_URL . 'assets/js/artspay-applepay.js',
			array( 'jquery', 'artspay-core', 'apple-pay-js-sdk' ),
			ARTSPAY_PLUGIN_VERSION,
			true
		);

		wp_enqueue_script( 'artspay-core' );

		$cart_total = '0.00';
		if ( WC()->cart && ! WC()->cart->is_empty() ) {
			$cart_total = wc_format_decimal( WC()->cart->get_total( 'edit' ), wc_get_price_decimals() );
		}

		$merchant_name = get_bloginfo( 'name' );
		$base_location = function_exists( 'wc_get_base_location' ) ? wc_get_base_location() : array();
		$country_code  = isset( $base_location['country'] ) ? (string) $base_location['country'] : '';

		$express_btns = self::express_buttons_from_shared_wallet_settings( $this->gateway );
		$button_type  = $express_btns['buttonType'];
		$button_style = $express_btns['buttonStyle'];
		$locale       = str_replace( '_', '-', get_user_locale() );
		if ( '' === $locale ) {
			$locale = 'en';
		}

		$mount_selectors = $on_order_pay ? '' : '#artspay-express-applepay-checkout,#artspay-express-applepay-cart,#artspay-express-applepay-blocks-cart,#artspay-express-applepay-blocks-checkout';

		$apple_pay_config = array(
			'environment'           => $this->gateway->is_sandbox_mode() ? 'TEST' : 'PRODUCTION',
			'currencyCode'          => get_woocommerce_currency(),
			'countryCode'           => $country_code,
			'amount'                => $cart_total,
			'displayName'           => mb_substr( (string) $merchant_name, 0, 64 ),
			'buttonType'            => $button_type,
			'buttonStyle'           => $button_style,
			'locale'                => $locale,
			'checkoutUrl'           => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			'cartUrl'               => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'expressMountSelectors' => $mount_selectors,
			'restUrl'               => esc_url_raw( rest_url( 'artspay/v1/apple-pay/session' ) ),
			'nonce'                 => wp_create_nonce( 'artspay_applepay' ),
			'strings'               => array(
				'orDivider' => __( '— OR —', 'artspay' ),
			),
			'selectors'             => array(
				'paymentMethodFatzebra' => '#payment_method_fatzebra',
				'applePayToken'         => '#applepay-token',
				'expressHostCheckout'   => '.artspay-express-checkout--checkout',
			),
			'blockSelectors'        => array(
				'paymentMethodFatzebra' => '.wc-block-checkout input[value="fatzebra"]',
				'applePayToken'         => '#applepay-token',
			),
		);

		wp_localize_script(
			'artspay-core',
			'artsPayApplePayLocalized',
			array(
				'applePay' => $apple_pay_config,
			)
		);
	}

	/**
	 * Express Apple Pay above billing / order columns (classic checkout).
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
	 * Express Apple Pay in cart totals (after estimated total).
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

		$apple_ok = $this->is_express_enabled();

		$google_ok = ( 'yes' === (string) $this->gateway->get_option( 'enabled', 'no' ) )
			&& ( 'yes' === (string) $this->gateway->get_option( 'google_pay_enabled', 'no' ) );
		if ( $google_ok ) {
			$merchant_id = trim( (string) $this->gateway->get_option( 'google_pay_merchant_id', '' ) );
			if ( '' === $merchant_id ) {
				$google_ok = false;
			}
		}
		if ( $google_ok ) {
			$google_ok = WC()->cart && ! WC()->cart->is_empty();
		}
		if ( $google_ok ) {
			$google_ok = $this->gateway->is_sandbox_mode() || is_ssl();
		}

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

		echo '<input type="hidden" name="applepay-token" id="applepay-token" value="" autocomplete="off" />';
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
	 * Apple Pay button type/style from the shared Google Pay theme and action settings.
	 *
	 * @param CreditCardGateway $gateway Gateway.
	 * @return array{buttonType: string, buttonStyle: string}
	 */
	public static function express_buttons_from_shared_wallet_settings( CreditCardGateway $gateway ): array {
		$raw_action   = (string) $gateway->get_option( 'google_pay_button_action', 'buy' );
		$mapped       = GooglePay::map_button_type_for_api( $raw_action );
		$button_type  = self::normalize_apple_pay_button_type( $mapped );
		$theme        = (string) $gateway->get_option( 'google_pay_theme', 'system' );
		$button_style = self::normalize_apple_pay_button_style( $theme );

		return array(
			'buttonType'  => $button_type,
			'buttonStyle' => $button_style,
		);
	}

	/**
	 * Normalize gateway setting to <apple-pay-button type="…"> (Apple Pay JS).
	 *
	 * @param string $stored Raw option value.
	 * @return string Valid type string for the web component.
	 */
	public static function normalize_apple_pay_button_type( string $stored ): string {
		$stored = strtolower( trim( $stored ) );

		$aliases = array(
			'checkout' => 'check-out',
			'pay'      => 'buy',
		);
		if ( isset( $aliases[ $stored ] ) ) {
			$stored = $aliases[ $stored ];
		}

		$allowed = array(
			'plain',
			'buy',
			'donate',
			'set-up',
			'continue',
			'book',
			'check-out',
			'subscribe',
			'reload',
			'add-money',
			'contribute',
			'order',
			'tip',
		);
		if ( in_array( $stored, $allowed, true ) ) {
			return $stored;
		}

		return 'buy';
	}

	/**
	 * Normalize gateway setting to <apple-pay-button buttonstyle="…">.
	 *
	 * @param string $stored Raw option value.
	 * @return string black|white|white-outline
	 */
	public static function normalize_apple_pay_button_style( string $stored ): string {
		$stored = strtolower( trim( $stored ) );

		$aliases = array(
			'dark'    => 'black',
			'light'   => 'white-outline',
			'default' => 'black',
			'system'  => 'black',
		);
		if ( isset( $aliases[ $stored ] ) ) {
			$stored = $aliases[ $stored ];
		}

		$allowed = array( 'black', 'white', 'white-outline' );
		if ( in_array( $stored, $allowed, true ) ) {
			return $stored;
		}

		return 'black';
	}
}

