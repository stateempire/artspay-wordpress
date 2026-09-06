<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * WooCommerce Cart / Checkout block: express checkout mounts (footer injection).
 *
 * @package ArtsPay
 */

namespace ArtsPay\Express;

/**
 * Renders Google Pay mount points when the page uses Cart or Checkout blocks
 * (classic template hooks do not run for block-based templates).
 */
final class Blocks {
	/**
	 * Google Pay express handler.
	 *
	 * @var GooglePay
	 */
	private GooglePay $google_pay;

	/**
	 * Gateway instance (for checking Apple Pay enablement without reflection).
	 *
	 * @var \ArtsPay\Gateways\CreditCardGateway
	 */
	private \ArtsPay\Gateways\CreditCardGateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param GooglePay                       $google_pay Express instance.
	 * @param \ArtsPay\Gateways\CreditCardGateway $gateway Gateway instance.
	 */
	public function __construct( GooglePay $google_pay, \ArtsPay\Gateways\CreditCardGateway $gateway ) {
		$this->google_pay = $google_pay;
		$this->gateway    = $gateway;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_footer', array( $this, 'maybe_render_block_express_ui' ), 20 );
	}

	/**
	 * Output express mounts + OR divider + wallet token field for block cart/checkout.
	 */
	public function maybe_render_block_express_ui(): void {
		if ( is_admin() || ! function_exists( 'is_cart' ) ) {
			return;
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			return;
		}

		$cart_block     = $this->current_page_has_block( 'woocommerce/cart' );
		$checkout_block = $this->current_page_has_block( 'woocommerce/checkout' );

		if ( ! $cart_block && ! $checkout_block ) {
			return;
		}

		$google_ok = $this->google_pay->is_express_enabled();
		// Mirror ApplePay::is_express_enabled() without taking a dependency on the ApplePay instance.
		$apple_ok = ( 'yes' === (string) $this->gateway->get_option( 'enabled', 'no' ) )
			&& ( 'yes' === (string) $this->gateway->get_option( 'apple_pay_enabled', 'no' ) )
			&& ( WC()->cart && ! WC()->cart->is_empty() )
			&& ( $this->gateway->is_sandbox_mode() || is_ssl() );

		if ( ! $google_ok && ! $apple_ok ) {
			return;
		}

		if ( $cart_block && function_exists( 'is_cart' ) && is_cart() ) {
			$key = 'artspay_express_container_printed_blocks-cart';
			if ( empty( $GLOBALS[ $key ] ) ) {
				$GLOBALS[ $key ] = true;
				echo '<div class="artspay-express-checkout artspay-express-checkout--cart artspay-express-checkout--blocks">';
				echo '<div class="artspay-express-checkout__buttons">';
				if ( $apple_ok ) {
					echo '<div id="artspay-express-applepay-blocks-cart" class="artspay-express-applepay-mount"></div>';
				}
				if ( $google_ok ) {
					echo '<div id="artspay-express-googlepay-blocks-cart" class="artspay-express-googlepay-mount"></div>';
				}
				echo '</div>';
				echo '<p class="artspay-express-divider" role="separator"><span>' . esc_html__( '— OR —', 'artspay' ) . '</span></p>';
				echo '</div>';
			}
		}

		if ( $checkout_block && function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
			if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' ) ) {
				return;
			}
			$key = 'artspay_express_container_printed_blocks-checkout';
			if ( empty( $GLOBALS[ $key ] ) ) {
				$GLOBALS[ $key ] = true;
				echo '<div class="artspay-express-checkout artspay-express-checkout--checkout artspay-express-checkout--blocks">';
				echo '<div class="artspay-express-checkout__buttons">';
				if ( $apple_ok ) {
					echo '<div id="artspay-express-applepay-blocks-checkout" class="artspay-express-applepay-mount"></div>';
				}
				if ( $google_ok ) {
					echo '<div id="artspay-express-googlepay-blocks-checkout" class="artspay-express-googlepay-mount"></div>';
				}
				echo '</div>';
				echo '<p class="artspay-express-divider" role="separator"><span>' . esc_html__( '— OR —', 'artspay' ) . '</span></p>';
				echo '</div>';
			}
			// Wallet token fields for Blocks checkout (classic POST field names; compatibility varies by WC version).
			if ( $google_ok ) {
				echo '<input type="hidden" name="googlepay-token" id="googlepay-token" value="" autocomplete="off" />';
			}
			if ( $apple_ok ) {
				echo '<input type="hidden" name="applepay-token" id="applepay-token" value="" autocomplete="off" />';
			}
		}
	}

	/**
	 * Whether the current singular page contains a given block.
	 *
	 * @param string $block_name Full block name, e.g. woocommerce/cart.
	 */
	private function current_page_has_block( string $block_name ): bool {
		if ( ! function_exists( 'has_block' ) ) {
			return false;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		return has_block( $block_name, $post );
	}
}
