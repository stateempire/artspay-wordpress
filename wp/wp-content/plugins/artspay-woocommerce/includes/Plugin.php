<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * Main plugin class
 *
 * @package ArtsPay
 */

namespace ArtsPay;

use ArtsPay\Admin\ApplePay as ApplePayAdmin;
use ArtsPay\Gateways\CreditCardGateway;

/**
 * Main plugin class.
 */
final class Plugin {
	/**
	 * Plugin instance.
	 *
	 * @var Plugin|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Initialize gateways and admin functionality.
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_filter( 'woocommerce_states', array( $this, 'customize_woocommerce_states' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_gateway_admin_assets' ), 20 );
		add_action( 'admin_init', array( $this, 'init_admin' ) );
		add_action( 'save_post', array( $this, 'set_recurring_payment_method' ) );

		// Register admin-post handlers early (admin-post requests may not run admin_init).
		if ( is_admin() ) {
			ApplePayAdmin::register_hooks();
		}

		add_filter( 'plugin_action_links_' . plugin_basename( ARTSPAY_PLUGIN_FILE ), array( $this, 'plugin_action_links' ), 10, 2 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Get plugin instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Load plugin text domain,
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'artspay',
			false,
			dirname( plugin_basename( ARTSPAY_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Plugin activation.
	 */
	public static function activate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Add our gateways to WooCommerce.
	 *
	 * @param array $gateways Existing gateways.
	 *
	 * @return array Modified gateways array.
	 */
	public function add_gateways( array $gateways ): array {
		$gateways[] = Gateways\CreditCardGateway::class;

		return $gateways;
	}

	/**
	 * Initialize admin functionality.
	 */
	public function init_admin(): void {
		if ( ! is_admin() ) {
			return;
		}

		// Initialize deferred payments admin.
		new Admin\DeferredPayments();
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		REST\ApplePay::register_routes();
	}

	/**
	 * Enqueue ArtsPay gateway admin CSS (and Google Pay preview scripts on the express sub-page).
	 *
	 * Registered on Plugin so assets load even when WooCommerce has not yet constructed payment
	 * gateway instances before admin_enqueue_scripts (otherwise callbacks added in the gateway
	 * constructor never run and gateway-admin.css is never linked).
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function maybe_enqueue_gateway_admin_assets( string $hook_suffix ): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['tab'] ) || 'checkout' !== $_GET['tab'] || empty( $_GET['section'] ) || 'fatzebra' !== $_GET['section'] ) {
			return;
		}

		wp_enqueue_style(
			'artspay-gateway-admin',
			ARTSPAY_PLUGIN_URL . 'assets/css/gateway-admin.css',
			array(),
			ARTSPAY_PLUGIN_VERSION
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$express_page = isset( $_GET['artspay_express'] ) ? sanitize_key( (string) wp_unslash( $_GET['artspay_express'] ) ) : '';
		if ( 'express_checkouts' !== $express_page ) {
			return;
		}

		$wc_gateways = WC()->payment_gateways();
		$gateways    = $wc_gateways->payment_gateways();
		if ( ! isset( $gateways['fatzebra'] ) || ! $gateways['fatzebra'] instanceof CreditCardGateway ) {
			return;
		}

		$gateways['fatzebra']->enqueue_google_pay_admin_preview_scripts();
		$gateways['fatzebra']->enqueue_apple_pay_admin_preview_scripts();
	}

	/**
	 * Customise states list.
	 *
	 * @param array $states States.
	 *
	 * @return array
	 */
	public function customize_woocommerce_states( array $states ): array {
		$states['AU'] = array(
			'ACT' => __( 'ACT', 'artspay' ),
			'NSW' => __( 'NSW', 'artspay' ),
			'NT'  => __( 'NT', 'artspay' ),
			'QLD' => __( 'QLD', 'artspay' ),
			'SA'  => __( 'SA', 'artspay' ),
			'TAS' => __( 'TAS', 'artspay' ),
			'VIC' => __( 'VIC', 'artspay' ),
			'WA'  => __( 'WA', 'artspay' ),
		);

		return $states;
	}

	/**
	 * Only update the recurring payment method if:
	 *  - The method is not set
	 *  - the fatzebra_card_token is set
	 *
	 * @param int $post_id Post ID.
	 */
	public function set_recurring_payment_method( int $post_id ): void {
		$method = get_post_meta( $post_id, '_recurring_payment_method', true );
		$token  = get_post_meta( $post_id, 'fatzebra_card_token', true );
		if ( empty( $method ) && ! empty( $token ) ) {
			update_post_meta( $post_id, '_recurring_payment_method', 'fatzebra' );
			update_post_meta( $post_id, '_recurring_payment_method_title', 'Credit Card (ArtsPay)' );
		}
	}

	/**
	 * Add Settings link on the Plugins list (next to Activate / Deactivate).
	 *
	 * @param array  $links       Existing action links.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 *
	 * @return array
	 */
	public function plugin_action_links( array $links, string $plugin_file ): array {
		if ( plugin_basename( ARTSPAY_PLUGIN_FILE ) !== $plugin_file ) {
			return $links;
		}

		if ( ! class_exists( 'WooCommerce', false ) ) {
			return $links;
		}

		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=fatzebra' );

		array_unshift(
			$links,
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'artspay' ) . '</a>'
		);

		return $links;
	}

	/**
	 * Add `Documentation` link on the `Plugins` page.
	 *
	 * @param array  $plugin_meta Actions array.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 *
	 * @return array
	 */
	public function plugin_row_meta( array $plugin_meta, string $plugin_file ): array {
		if ( plugin_basename( ARTSPAY_PLUGIN_FILE ) !== $plugin_file ) {
			return $plugin_meta;
		}

		$docs_url = apply_filters( 'artspay_docs_url', 'https://artspay.notion.site/Payment-Gateway-Guides-7ef95eee075c4e0f917332e6c1ea10ba' );

		$row_meta = array(
			'docs' => '<a href="' . esc_url( $docs_url ) . '" aria-label="' . esc_attr__( 'View documentation', 'artspay' ) . '" target="_blank">' . esc_html__( 'Documentation', 'artspay' ) . '</a>',
		);

		return array_merge( $plugin_meta, $row_meta );
	}
}
