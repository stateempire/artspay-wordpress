<?php
/**
 * Apple Pay REST endpoints (merchant validation session).
 *
 * @package ArtsPay
 */

namespace ArtsPay\REST;

use ArtsPay\Gateways\CreditCardGateway;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Apple Pay REST controller.
 */
final class ApplePay {
	/**
	 * Register routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			'artspay/v1',
			'/apple-pay/session',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_session' ),
				'permission_callback' => array( __CLASS__, 'permission_check' ),
				'args'                => array(
					'validationURL' => array(
						'type'     => 'string',
						'required' => true,
					),
					'displayName'    => array(
						'type'     => 'string',
						'required' => false,
					),
					'nonce'          => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Permission check (guest-safe nonce).
	 */
	public static function permission_check( WP_REST_Request $request ): bool {
		$nonce = (string) $request->get_param( 'nonce' );
		if ( '' !== $nonce && (bool) wp_verify_nonce( $nonce, 'artspay_applepay' ) ) {
			return true;
		}

		/**
		 * Fallback for setups where page caching breaks WP nonces.
		 *
		 * We still keep this endpoint safe by only allowing Apple Pay merchant validation URLs
		 * (otherwise this could become an unauthenticated proxy to the upstream session endpoint).
		 */
		$validation_url = trim( (string) $request->get_param( 'validationURL' ) );
		if ( '' === $validation_url ) {
			return false;
		}

		$host   = (string) wp_parse_url( $validation_url, PHP_URL_HOST );
		$scheme = (string) wp_parse_url( $validation_url, PHP_URL_SCHEME );
		if ( 'https' !== strtolower( $scheme ) || '' === $host ) {
			return false;
		}

		$allowed_hosts = array(
			'apple-pay-gateway.apple.com',
			'apple-pay-gateway-cert.apple.com',
		);

		return in_array( strtolower( $host ), $allowed_hosts, true );
	}

	/**
	 * Create Apple Pay merchant validation session via Fat Zebra.
	 */
	public static function create_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gateway = self::get_gateway();
		if ( ! $gateway ) {
			return new WP_Error( 'artspay_applepay_no_gateway', 'Gateway not available', array( 'status' => 500 ) );
		}

		$validation_url = trim( (string) $request->get_param( 'validationURL' ) );
		if ( '' === $validation_url ) {
			return new WP_Error( 'artspay_applepay_missing_validation_url', 'Missing validationURL', array( 'status' => 400 ) );
		}

		$validation_host = strtolower( (string) wp_parse_url( $validation_url, PHP_URL_HOST ) );

		$domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		if ( '' === $domain ) {
			return new WP_Error( 'artspay_applepay_missing_domain', 'Missing domain', array( 'status' => 400 ) );
		}

		$display_name = trim( (string) $request->get_param( 'displayName' ) );
		if ( '' === $display_name ) {
			$display_name = (string) get_bloginfo( 'name' );
		}
		$display_name = mb_substr( $display_name, 0, 64 );

		// Choose the paynow environment based on the validation URL host.
		// Apple uses `apple-pay-gateway-cert.apple.com` for sandbox validation URLs.
		$is_cert  = ( 'apple-pay-gateway-cert.apple.com' === $validation_host );
		$base_url = $is_cert ? 'https://paynow.pmnts-sandbox.io' : 'https://paynow.pmnts.io';
		$url        = $base_url . '/v2/apple_pay/payment_session';

		$query = array(
			'url'         => $validation_url,
			'domain_name' => $domain,
			'display_name'=> $display_name,
		);

		$credentials = $gateway->get_gateway_credentials();
		$username    = isset( $credentials['username'] ) ? trim( (string) $credentials['username'] ) : '';
		$api_token   = isset( $credentials['api_token'] ) ? trim( (string) $credentials['api_token'] ) : '';

		if ( '' === $username || '' === $api_token ) {
			return new WP_Error(
				'artspay_applepay_missing_credentials',
				'Apple Pay credentials are not configured',
				array( 'status' => 500 )
			);
		}

		// Common misconfiguration: using TEST credentials against the production paynow endpoint.
		if ( ! $is_cert && 0 === strcasecmp( $username, 'TEST' ) ) {
			return new WP_Error(
				'artspay_applepay_bad_credentials_env',
				'Apple Pay session requested with TEST credentials against production. Configure live ArtsPay credentials or use Apple Pay sandbox (gateway-cert) validation URL.',
				array( 'status' => 400 )
			);
		}
		$args        = array(
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $username . ':' . $api_token ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				'User-Agent'    => 'WooCommerce Plugin ' . ARTSPAY_PLUGIN_VERSION,
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		);

		$response = wp_remote_get( add_query_arg( $query, $url ), $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'artspay_applepay_transport', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		$body = isset( $response['body'] ) ? (string) $response['body'] : '';

		if ( 200 !== $code ) {
			return new WP_Error(
				'artspay_applepay_session_failed',
				'Apple Pay session request failed',
				array(
					'status'   => 502,
					'code'     => $code,
					'response' => $body,
				)
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'artspay_applepay_bad_json', 'Invalid response from Apple Pay session endpoint', array( 'status' => 502 ) );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Get the Fat Zebra gateway instance.
	 */
	private static function get_gateway(): ?CreditCardGateway {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}
		$wc_gateways = WC()->payment_gateways();
		$gateways    = $wc_gateways->payment_gateways();
		if ( isset( $gateways['fatzebra'] ) && $gateways['fatzebra'] instanceof CreditCardGateway ) {
			return $gateways['fatzebra'];
		}

		// Fallback: construct with persisted settings.
		return class_exists( CreditCardGateway::class ) ? new CreditCardGateway() : null;
	}
}

