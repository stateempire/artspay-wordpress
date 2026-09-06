<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * ArtsPay Deferred Payments Admin Handler
 *
 * Handles deferred payments functionality in WooCommerce admin.
 *
 * @package ArtsPay
 */

namespace ArtsPay\Admin;

use ArtsPay\API\Client;
use ArtsPay\Gateways\CreditCardGateway;
use WC_Order;

/**
 * DeferredPayments class.
 */
class DeferredPayments {
	/**
	 * API Client instance.
	 *
	 * @var Client
	 */
	private Client $api_client;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_client = new Client();

		// Hook into WooCommerce admin (legacy orders table).
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_deferred_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_deferred_column_contents' ), 10, 2 );

		// Hook into WooCommerce admin.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_deferred_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'add_deferred_column_contents' ), 10, 2 );
	}

	/**
	 * Add deferred payment column to orders list.
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns.
	 */
	public function add_deferred_column( array $columns ): array {
		if ( isset( CreditCardGateway::$global_settings['deferred_payments'] ) && 'yes' === CreditCardGateway::$global_settings['deferred_payments'] ) {
			$columns['deferred_column'] = __( 'Deferred Payment', 'artspay' );
		}

		return $columns;
	}

	/**
	 * Add content to the deferred payment column.
	 *
	 * @param string   $column Column name.
	 * @param WC_Order $order  Order object.
	 */
	public function add_deferred_column_contents( string $column, WC_Order $order ): void {
		if ( 'deferred_column' !== $column ) {
			return;
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! $order ) {
			return;
		}

		// Only show for on-hold orders with stored card token.
		if ( $order->has_status( 'on-hold' ) && $this->has_deferred_payment_data( $order ) ) {
			$this->display_capture_button( $order->get_id() );
		}

		// Handle payment capture if requested.
		if ( $this->should_capture_payment( $order->get_id() ) ) {
			$this->process_deferred_payment_capture( $order );
		}
	}

	/**
	 * Check if order has deferred payment data stored.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return bool True if deferred payment data exists.
	 */
	private function has_deferred_payment_data( WC_Order $order ): bool {
		$card_token = get_post_meta( $order->get_id(), 'Card Token', true );
		return ! empty( $card_token );
	}

	/**
	 * Display the capture payment button.
	 *
	 * @param int $order_id Order ID.
	 */
	private function display_capture_button( int $order_id ): void {
		$url = add_query_arg(
			array(
				'post_type' => 'shop_order',
				'xaction'   => 'takepayment',
				'order_id'  => $order_id,
				'nonce'     => wp_create_nonce( 'artspay_capture_payment_' . $order_id ),
			),
			admin_url( 'edit.php' )
		);

		echo '<p><a class="button wc-action-button artspay-capture-payment" href="' . esc_url( $url ) . '" aria-label="' . esc_attr__( 'Capture Payment', 'artspay' ) . '">' . esc_html__( 'Capture Payment', 'artspay' ) . '</a></p>';
	}

	/**
	 * Check if payment capture should be processed.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return bool True if payment should be captured.
	 */
	private function should_capture_payment( int $order_id ): bool {
		return isset( $_GET['order_id'], $_GET['xaction'], $_GET['nonce'] ) &&
			'takepayment' === $_GET['xaction'] &&
			(int) $_GET['order_id'] === $order_id &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'artspay_capture_payment_' . $order_id );
	}

	/**
	 * Process the deferred payment capture.
	 *
	 * @param WC_Order $order Order object.
	 */
	private function process_deferred_payment_capture( WC_Order $order ): void {
		$cc_gateway = new CreditCardGateway();
		$ip         = $cc_gateway->get_order_ip_address( $order );

		$params = array(
			'card_token'  => get_post_meta( $order->get_id(), 'Card Token', true ),
			'reference'   => get_post_meta( $order->get_id(), 'reference', true ),
			'amount'      => get_post_meta( $order->get_id(), 'amount', true ),
			'currency'    => get_post_meta( $order->get_id(), 'currency', true ),
			'customer_ip' => $ip, // Backwards compatible with existing payloads.
			'ip_address'  => $ip,
			'metadata'    => $cc_gateway->build_fatzebra_metadata_for_order( $order ),
		);

		// Add fraud data if enabled.
		if ( 'true' === get_post_meta( $order->get_id(), 'fraud_data', true ) ) {
			$params['fraud'] = $cc_gateway->get_fraud_payload( $order );
		}

		// Process the tokenized payment.
		$result = $this->api_client->purchase_with_token(
			$params,
			$cc_gateway->get_gateway_credentials(),
			$cc_gateway->is_sandbox_mode()
		);

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( $order, $result->get_error_message() );
			return;
		}

		$this->handle_capture_result( $order, $result );
	}

	/**
	 * Handle the capture result.
	 *
	 * @param WC_Order $order Order object.
	 * @param array    $result Capture result.
	 */
	private function handle_capture_result( WC_Order $order, array $result ): void {
		// Add fraud result notes if available.
		if ( ! empty( $result['fraud_result'] ) ) {
			/* translators: %s - fraud check results */
			$order->add_order_note( sprintf( __( 'Fraud Check Result: %s', 'artspay' ), $result['fraud_result'] ) );

			if ( ! empty( $result['fraud_messages'] ) ) {
				/* translators: %s - fraud message */
				$order->add_order_note( sprintf( __( 'Fraud Messages: %s', 'artspay' ), $result['fraud_messages'] ) );
			}
		}

		if ( 'false' !== $result['successful'] ) {
			$order->payment_complete( $result['transaction_id'] ?? '' );
			$order->add_order_note( __( 'Admin has processed the deferred payment successfully', 'artspay' ) );
			$order->update_status( 'processing', __( 'Admin has processed the deferred payment successfully', 'artspay' ) );

			// Clean up deferred payment meta data.
			$this->cleanup_deferred_payment_meta( $order );

			$this->redirect_with_success();
		} else {
			$error_message = $result['message'] ?? __( 'Unknown error occurred', 'artspay' );
			$this->redirect_with_error( $order, $error_message );
		}
	}

	/**
	 * Clean up deferred payment meta data after successful capture.
	 *
	 * @param WC_Order $order Order object.
	 */
	private function cleanup_deferred_payment_meta( WC_Order $order ): void {
		delete_post_meta( $order->get_id(), 'Card Token' );
		delete_post_meta( $order->get_id(), 'reference' );
		delete_post_meta( $order->get_id(), 'amount' );
		delete_post_meta( $order->get_id(), 'currency' );
		delete_post_meta( $order->get_id(), 'fraud_data' );
	}

	/**
	 * Redirect with success message.
	 */
	private function redirect_with_success(): void {
		wp_safe_redirect( add_query_arg( 'artspay_message', 'payment_captured', admin_url( 'edit.php?post_type=shop_order' ) ) );
		exit;
	}

	/**
	 * Redirect with error message.
	 *
	 * @param WC_Order $order         Order object.
	 * @param string   $error_message Error message.
	 */
	private function redirect_with_error( WC_Order $order, string $error_message ): void {
		/* translators: %s - error message */
		$order->add_order_note( sprintf( __( 'The deferred payment has failed: %s', 'artspay' ), $error_message ) );
		/* translators: %s - error message */
		$order->update_status( 'on-hold', sprintf( __( 'The deferred payment has failed: %s', 'artspay' ), $error_message ) );

		wp_safe_redirect( add_query_arg( 'artspay_error', rawurlencode( $error_message ), admin_url( 'edit.php?post_type=shop_order' ) ) );
		exit;
	}
}
