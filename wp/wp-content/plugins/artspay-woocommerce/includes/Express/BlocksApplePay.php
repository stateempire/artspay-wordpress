<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * WooCommerce Cart / Checkout block: Apple Pay express checkout mounts (footer injection).
 *
 * @package ArtsPay
 */

namespace ArtsPay\Express;

/**
 * Renders Apple Pay mount points when the page uses Cart or Checkout blocks.
 */
final class BlocksApplePay {
	/**
	 * Apple Pay express handler.
	 *
	 * @var ApplePay
	 */
	private ApplePay $apple_pay;

	/**
	 * Constructor.
	 *
	 * @param ApplePay $apple_pay Express instance.
	 */
	public function __construct( ApplePay $apple_pay ) {
		$this->apple_pay = $apple_pay;
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

		$apple_ok = $this->apple_pay->is_express_enabled();
		// Google Pay blocks UI is handled by `Blocks`; this class is a fallback for Apple-only installs.
		$google_ok = false;

		if ( ! $apple_ok ) {
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
				// Google Pay mount is rendered by `Blocks`.
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
				// Google Pay mount is rendered by `Blocks`.
				echo '</div>';
				echo '<p class="artspay-express-divider" role="separator"><span>' . esc_html__( '— OR —', 'artspay' ) . '</span></p>';
				echo '</div>';
			}
			if ( $apple_ok ) {
				echo '<input type="hidden" name="applepay-token" id="applepay-token" value="" autocomplete="off" />';
			}
			// Google Pay token field is rendered by `Blocks`.
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

