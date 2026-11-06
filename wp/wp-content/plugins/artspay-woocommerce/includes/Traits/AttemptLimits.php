<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * Payment attempt limits, the plugin's defence against card testing
 *
 * @package ArtsPay
 */

namespace ArtsPay\Traits;

use ArtsPay\Debug_Logger;
use WC_Order;
use WP_Error;

/**
 * AttemptLimits trait.
 *
 * WooCommerce reuses a pending or failed order while the cart is unchanged, so without a
 * limit one order can be used to try an unlimited number of cards.
 */
trait AttemptLimits {
	/**
	 * Get the attempt limits.
	 *
	 * @return array Limits for each scope, plus the window in seconds.
	 */
	private function get_attempt_limits(): array {
		/**
		 * Filter the payment attempt limits.
		 *
		 * @param array $limits Attempts allowed per order, session and IP, and the window in seconds.
		 */
		return apply_filters(
			'artspay_attempt_limits',
			array(
				'order'   => 5,
				'session' => 5,
				'ip'      => 10,
				'window'  => HOUR_IN_SECONDS,
			)
		);
	}

	/**
	 * Get the transient keys that count attempts for this request.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array Transient keys by scope.
	 */
	private function get_attempt_keys( int $order_id ): array {
		$keys = array( 'order' => 'artspay_attempts_order_' . $order_id );

		$session_id = ( function_exists( 'WC' ) && WC()->session ) ? (string) WC()->session->get_customer_id() : '';
		if ( '' !== $session_id ) {
			$keys['session'] = 'artspay_attempts_session_' . md5( $session_id );
		}

		$ip = $this->get_rate_limit_ip();
		if ( '' !== $ip ) {
			$keys['ip'] = 'artspay_attempts_ip_' . md5( $ip );
		}

		return $keys;
	}

	/**
	 * Find the first scope that has used all of its attempts.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string Scope name, or an empty string when attempts remain.
	 */
	private function get_exceeded_attempt_scope( int $order_id ): string {
		$limits = $this->get_attempt_limits();

		foreach ( $this->get_attempt_keys( $order_id ) as $scope => $key ) {
			$entry = get_transient( $key );
			if ( is_array( $entry ) && $entry['count'] >= (int) $limits[ $scope ] ) {
				return $scope;
			}
		}

		return '';
	}

	/**
	 * Count an attempt. Runs before the gateway call so that parallel requests see it.
	 *
	 * @param int $order_id Order ID.
	 */
	private function record_payment_attempt( int $order_id ): void {
		$window = (int) $this->get_attempt_limits()['window'];

		foreach ( $this->get_attempt_keys( $order_id ) as $key ) {
			$entry = get_transient( $key );
			if ( ! is_array( $entry ) ) {
				$entry = array(
					'count'   => 0,
					'expires' => time() + $window,
				);
			}
			++$entry['count'];
			set_transient( $key, $entry, max( 1, $entry['expires'] - time() ) );
		}
	}

	/**
	 * Check whether a failed payment was about the card, not the gateway. Rejected credentials
	 * and server errors say nothing about the card, so they must not use up attempts.
	 *
	 * @param WP_Error $result Failed payment result.
	 *
	 * @return bool True if the attempt should count.
	 */
	private function is_card_failure( WP_Error $result ): bool {
		$code = $result->get_error_code();

		if ( 1 === $code ) {
			$data = $result->get_error_data();
			$http = is_array( $data ) && isset( $data['response']['code'] ) ? (int) $data['response']['code'] : 0;

			return $http >= 400 && $http < 500 && ! in_array( $http, array( 401, 403 ), true );
		}

		return in_array( $code, array( 2, 3 ), true );
	}

	/**
	 * Take back an attempt that never reached the card, such as a gateway outage.
	 *
	 * @param int $order_id Order ID.
	 */
	private function release_payment_attempt( int $order_id ): void {
		foreach ( $this->get_attempt_keys( $order_id ) as $key ) {
			$this->release_attempt_key( $key );
		}
	}

	/**
	 * Take one attempt off a count.
	 *
	 * @param string $key Transient key.
	 */
	private function release_attempt_key( string $key ): void {
		$entry = get_transient( $key );
		if ( is_array( $entry ) && $entry['count'] > 0 ) {
			--$entry['count'];
			set_transient( $key, $entry, max( 1, $entry['expires'] - time() ) );
		}
	}

	/**
	 * Update the counts after a successful payment. The order and session start again. The IP
	 * only gets this attempt back, so a box office selling from one IP is never limited by its
	 * sales, and a tester can't clear earlier declines with one good card.
	 *
	 * @param int $order_id Order ID.
	 */
	private function clear_payment_attempts( int $order_id ): void {
		foreach ( $this->get_attempt_keys( $order_id ) as $scope => $key ) {
			if ( 'ip' === $scope ) {
				$this->release_attempt_key( $key );
			} else {
				delete_transient( $key );
			}
		}
	}

	/**
	 * Record on the order that a limit was reached. Only the order limit fails the order:
	 * that frees held stock, and a bot rotating orders can't trigger an email for each one.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $scope Scope that reached its limit.
	 */
	private function apply_attempt_limit( WC_Order $order, string $scope ): void {
		Debug_Logger::log( sprintf( 'Payment attempt limit reached for order #%d (scope=%s).', $order->get_id(), $scope ), 'warning' );

		if ( $order->get_meta( '_artspay_attempt_limit_reached' ) ) {
			return;
		}

		$order->update_meta_data( '_artspay_attempt_limit_reached', $scope );
		$order->save();

		/* translators: %s: limit that was reached (order, session or ip) */
		$note = sprintf( __( 'Payment attempts stopped: the %s attempt limit was reached. This can be a sign of card testing.', 'artspay' ), $scope );

		if ( 'order' === $scope && ! $order->has_status( 'failed' ) ) {
			$order->update_status( 'failed', $note );
		} else {
			$order->add_order_note( $note );
		}
	}

	/**
	 * Get the message shown when a limit stops a payment.
	 *
	 * @return string Error message.
	 */
	private function get_attempt_limit_message(): string {
		return __( 'Too many payment attempts. Please try again later or contact us.', 'artspay' );
	}
}
