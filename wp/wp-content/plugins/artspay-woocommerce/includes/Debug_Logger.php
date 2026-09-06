<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * WooCommerce debug logging for ArtsPay (when enabled in gateway settings).
 *
 * @package ArtsPay
 */

namespace ArtsPay;

/**
 * Writes to WooCommerce > Status > Logs with source {@see Debug_Logger::SOURCE}
 * (same naming pattern as Stripe's woocommerce-gateway-stripe).
 */
final class Debug_Logger {
	/**
	 * Log file source identifier: log files are named woocommerce-gateway-artspay-{date}-{hash}.log.
	 */
	public const SOURCE = 'woocommerce-gateway-artspay';

	/**
	 * Gateway option key prefix (woocommerce_{id}_settings).
	 */
	private const GATEWAY_ID = 'fatzebra';

	/**
	 * Whether debug logging is enabled for the credit card gateway.
	 */
	public static function is_enabled(): bool {
		$settings = get_option( 'woocommerce_' . self::GATEWAY_ID . '_settings', array() );
		return isset( $settings['debug_mode'] ) && 'yes' === $settings['debug_mode'];
	}

	/**
	 * Log a message when debug mode is on.
	 *
	 * @param string               $message Log message.
	 * @param string               $level   WC log level: emergency|alert|critical|error|warning|notice|info|debug.
	 * @param array<string, mixed> $context Optional context (shown in log details).
	 */
	public static function log( string $message, string $level = 'debug', array $context = array() ): void {
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		wc_get_logger()->log(
			$level,
			$message,
			array_merge( $context, array( 'source' => self::SOURCE ) )
		);
	}

	/**
	 * Strip sensitive fields from API payload before logging.
	 *
	 * @param array<string, mixed> $payload Request payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function redact_payload_for_log( array $payload ): array {
		$out = $payload;
		$keys = array( 'card_number', 'cvv', 'card_token', 'wallet_token', 'token', 'api_token', 'shared_secret' );
		foreach ( $keys as $key ) {
			if ( isset( $out[ $key ] ) ) {
				$out[ $key ] = '[redacted]';
			}
		}
		if ( isset( $out['wallet'] ) && is_array( $out['wallet'] ) && isset( $out['wallet']['token'] ) ) {
			$out['wallet']['token'] = '[redacted]';
		}
		if ( isset( $out['fraud'] ) && is_array( $out['fraud'] ) && isset( $out['fraud']['device_id'] ) && is_string( $out['fraud']['device_id'] ) ) {
			$d = $out['fraud']['device_id'];
			$out['fraud']['device_id'] = strlen( $d ) > 40 ? substr( $d, 0, 24 ) . '…' : '[set]';
		}
		return $out;
	}
}
