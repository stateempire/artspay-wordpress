<?php
/**
 * Apple Pay admin actions (health checks / registration).
 *
 * @package ArtsPay
 */

namespace ArtsPay\Admin;

use ArtsPay\Gateways\CreditCardGateway;

/**
 * Apple Pay admin actions.
 */
final class ApplePay {
	/**
	 * User meta key for storing last admin action result.
	 */
	private const RESULT_USER_META_KEY = '_artspay_applepay_admin_last_result';

	/**
	 * User meta: last verification file check succeeded (persists across other actions).
	 */
	private const META_VERIFICATION_FILE_OK = '_artspay_applepay_verification_file_ok';

	/**
	 * User meta: last domain registration API call succeeded (HTTP 2xx).
	 */
	private const META_DOMAIN_REGISTERED_OK = '_artspay_applepay_domain_registered_ok';

	/**
	 * Transient: cached association-file check (avoid hammering merchant origin on every settings paint).
	 */
	private const TRANSIENT_ASSOC_PREFIX = 'artspay_applepay_assoc_';

	/**
	 * Register admin-post handlers.
	 */
	public static function register_hooks(): void {
		add_action( 'admin_post_artspay_applepay_check', array( __CLASS__, 'handle_check' ) );
		add_action( 'admin_post_artspay_applepay_register_domain', array( __CLASS__, 'handle_register_domain' ) );
		// POST forms must not nest inside WooCommerce’s #mainform (breaks Save + layout). Render here instead.
		add_action( 'admin_footer', array( __CLASS__, 'print_express_checkouts_footer_forms' ), 5 );
	}

	/**
	 * Print Apple Pay admin-post forms outside #mainform (Express checkouts page only).
	 *
	 * Buttons in the gateway UI use the HTML5 `form="…"` attribute to submit these.
	 */
	public static function print_express_checkouts_footer_forms(): void {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['tab'] ) || 'checkout' !== $_GET['tab'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['section'] ) || 'fatzebra' !== $_GET['section'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$express = isset( $_GET['artspay_express'] ) ? sanitize_key( (string) wp_unslash( $_GET['artspay_express'] ) ) : '';
		if ( 'express_checkouts' !== $express ) {
			return;
		}

		echo '<div class="artspay-applepay-admin-post-forms" hidden>';
		echo '<form id="artspay-applepay-check-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'artspay_applepay_admin', 'artspay_applepay_check_nonce' );
		echo '<input type="hidden" name="action" value="artspay_applepay_check" />';
		echo '</form>';
		echo '<form id="artspay-applepay-register-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'artspay_applepay_admin', 'artspay_applepay_register_nonce' );
		echo '<input type="hidden" name="action" value="artspay_applepay_register_domain" />';
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle verification file check.
	 */
	public static function handle_check(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Access denied.', 'artspay' ), 403 );
		}
		check_admin_referer( 'artspay_applepay_admin', 'artspay_applepay_check_nonce' );

		$gateway = self::get_gateway();
		delete_transient( self::association_transient_key( $gateway ) );

		$result            = self::run_association_file_check( $gateway );
		$result['action'] = 'check';
		$result['sandbox'] = $gateway ? (bool) $gateway->is_sandbox_mode() : null;

		self::store_result_for_current_user( $result );
		self::set_verification_file_ok_for_current_user( ! empty( $result['ok'] ) );
		set_transient( self::association_transient_key( $gateway ), ! empty( $result['ok'] ) ? '1' : '0', 300 );
		wp_safe_redirect( self::back_url( 'check' ) );
		exit;
	}

	/**
	 * Same remote checks as the admin-post handler (no nonce / capability here).
	 *
	 * @return array{ action?: string, ok: bool, host: string, url: string, error?: string, http_code?: int, content_type?: string, snippet?: string, looks_html?: bool, body_length?: int, expected_url?: string }
	 */
	public static function run_association_file_check( ?CreditCardGateway $gateway ): array {
		$host = strtolower( trim( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		$path = '/.well-known/apple-developer-merchantid-domain-association';
		$url  = ( '' !== $host ) ? 'https://' . $host . $path : $path;

		$result = array(
			'host' => $host,
			'url'  => $url,
			'ok'   => false,
		);

		if ( '' === $host ) {
			$result['error'] = 'Could not detect canonical host from home_url().';
			return $result;
		}

		$args     = array(
			'timeout'     => 15,
			'redirection' => 3,
			'headers'     => array(
				'User-Agent' => 'WooCommerce Plugin ' . ARTSPAY_PLUGIN_VERSION,
			),
		);
		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			return $result;
		}

		$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		$ct   = isset( $response['headers']['content-type'] ) ? (string) $response['headers']['content-type'] : '';
		$body = isset( $response['body'] ) ? (string) $response['body'] : '';

		$snippet    = mb_substr( $body, 0, 200 );
		$looks_html = (bool) preg_match( '/<\\s*html|<!doctype\\s+html|<\\s*body|<\\s*head/i', $snippet );
		if ( ! $looks_html && '' !== $ct && false !== stripos( $ct, 'text/html' ) ) {
			$looks_html = true;
		}

		$result['http_code']    = $code;
		$result['content_type'] = $ct;
		$result['snippet']      = $snippet;
		$result['looks_html']   = $looks_html;
		$result['body_length']  = strlen( $body );

		$ok = ( 200 === $code ) && ! $looks_html;
		if ( $ok ) {
			$len = strlen( $body );
			if ( $len < 50 ) {
				$ok              = false;
				$result['error'] = 'Response too small to be a valid Apple domain association file.';
			}
		}
		if ( $ok && '' !== $ct && false !== stripos( $ct, 'text/html' ) ) {
			$ok              = false;
			$result['error'] = 'Response is HTML (expected plain text).';
		}
		if ( $ok && $looks_html && ( false !== stripos( $body, '404' ) || false !== stripos( $body, 'not found' ) ) ) {
			$ok              = false;
			$result['error'] = 'Looks like a generic error page.';
		}

		if ( $ok && $gateway ) {
			$is_sandbox   = (bool) $gateway->is_sandbox_mode();
			$expected_url = $is_sandbox
				? 'https://paynow.pmnts-sandbox.io/apple_pay/domain_verification/sandbox.txt'
				: 'https://paynow.pmnts.io/apple_pay/domain_verification/production.txt';
			$expected_resp = wp_remote_get(
				$expected_url,
				array(
					'timeout'     => 20,
					'redirection' => 3,
					'headers'     => array(
						'User-Agent' => 'WooCommerce Plugin ' . ARTSPAY_PLUGIN_VERSION,
					),
				)
			);
			if ( ! is_wp_error( $expected_resp ) ) {
				$expected_code = isset( $expected_resp['response']['code'] ) ? (int) $expected_resp['response']['code'] : 0;
				$expected_body = isset( $expected_resp['body'] ) ? (string) $expected_resp['body'] : '';
				if ( 200 === $expected_code && '' !== trim( $expected_body ) ) {
					$result['expected_url'] = $expected_url;
					if ( trim( $expected_body ) !== trim( $body ) ) {
						$ok              = false;
						$result['error'] = 'The hosted file does not match the Fat Zebra verification file for this environment (wrong file or extra whitespace).';
					}
				}
			}
		}

		$result['ok'] = $ok;
		return $result;
	}

	/**
	 * Transient key for association URL probe (gateway mode affects byte comparison).
	 */
	private static function association_transient_key( ?CreditCardGateway $gateway ): string {
		$host = strtolower( trim( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		$mode = ( $gateway && $gateway->is_sandbox_mode() ) ? 's' : 'p';
		return self::TRANSIENT_ASSOC_PREFIX . md5( $host . '|' . $mode );
	}

	/**
	 * Whether the association file appears valid for status UI (transient cache or live probe).
	 */
	public static function is_association_file_ok_for_status( ?CreditCardGateway $gateway ): bool {
		$key     = self::association_transient_key( $gateway );
		$cached  = get_transient( $key );
		if ( false !== $cached ) {
			return (string) $cached === '1';
		}
		$payload = self::run_association_file_check( $gateway );
		$ok      = ! empty( $payload['ok'] );
		set_transient( $key, $ok ? '1' : '0', 120 );
		return $ok;
	}

	/**
	 * Handle domain registration with Fat Zebra.
	 */
	public static function handle_register_domain(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Access denied.', 'artspay' ), 403 );
		}
		check_admin_referer( 'artspay_applepay_admin', 'artspay_applepay_register_nonce' );

		$gateway = self::get_gateway();
		if ( ! $gateway ) {
			self::store_result_for_current_user(
				array(
					'action' => 'register',
					'ok'     => false,
					'error'  => 'Gateway not available.',
				)
			);
			self::set_domain_registered_ok_for_current_user( false );
			wp_safe_redirect( self::back_url( 'register' ) );
			exit;
		}

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host = strtolower( trim( $host ) );

		$is_sandbox = (bool) $gateway->is_sandbox_mode();
		$base_url   = $is_sandbox ? 'https://gateway.pmnts-sandbox.io' : 'https://gateway.pmnts.io';
		$endpoint   = $base_url . '/v1.0/utilities/apple_pay/domains/' . rawurlencode( $host );

		$credentials = $gateway->get_gateway_credentials();
		$args        = array(
			'method'  => 'POST',
			'timeout' => 45,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( (string) $credentials['username'] . ':' . (string) $credentials['api_token'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				'User-Agent'    => 'WooCommerce Plugin ' . ARTSPAY_PLUGIN_VERSION,
				'Accept'        => 'application/json',
			),
		);

		$result = array(
			'action'   => 'register',
			'host'     => $host,
			'endpoint' => $endpoint,
			'sandbox'  => $is_sandbox,
			'ok'       => false,
		);

		if ( '' === $host ) {
			$result['error'] = 'Could not detect canonical host from home_url().';
			self::store_result_for_current_user( $result );
			self::set_domain_registered_ok_for_current_user( false );
			wp_safe_redirect( self::back_url( 'register' ) );
			exit;
		}

		$response = wp_remote_request( $endpoint, $args );
		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			self::store_result_for_current_user( $result );
			self::set_domain_registered_ok_for_current_user( false );
			wp_safe_redirect( self::back_url( 'register' ) );
			exit;
		}

		$code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		$body = isset( $response['body'] ) ? (string) $response['body'] : '';

		$result['http_code'] = $code;
		$result['response']  = mb_substr( $body, 0, 1500 );
		$result['ok']        = in_array( $code, array( 200, 201, 202 ), true );

		self::store_result_for_current_user( $result );
		self::set_domain_registered_ok_for_current_user( ! empty( $result['ok'] ) );
		wp_safe_redirect( self::back_url( 'register' ) );
		exit;
	}

	/**
	 * Store latest result for the current user.
	 *
	 * @param array $result Result payload.
	 */
	private static function store_result_for_current_user( array $result ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		$result['ts'] = time();
		update_user_meta( $user_id, self::RESULT_USER_META_KEY, $result );
	}

	/**
	 * Get latest result for the current user.
	 */
	public static function get_result_for_current_user(): ?array {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return null;
		}
		$result = get_user_meta( $user_id, self::RESULT_USER_META_KEY, true );
		return is_array( $result ) ? $result : null;
	}

	/**
	 * Whether HTTPS is available for the store origin (WordPress URLs or live probe).
	 *
	 * WordPress may still report http:// in Site Address when WP_HOME/WP_SITEURL are wrong,
	 * while the site already serves TLS — probe https://{host}/ in that case.
	 */
	public static function is_home_https(): bool {
		$home = home_url();
		if ( is_string( $home ) && str_starts_with( strtolower( $home ), 'https://' ) ) {
			return true;
		}
		$site = site_url();
		if ( is_string( $site ) && str_starts_with( strtolower( $site ), 'https://' ) ) {
			return true;
		}
		$host = strtolower( trim( (string) wp_parse_url( is_string( $home ) ? $home : '', PHP_URL_HOST ) ) );
		if ( '' === $host ) {
			return false;
		}
		$https_probe_key = 'artspay_applepay_https_' . md5( $host );
		$https_cached    = get_transient( $https_probe_key );
		if ( false !== $https_cached ) {
			return (string) $https_cached === '1';
		}
		$probe = wp_remote_get(
			'https://' . $host . '/',
			array(
				'timeout'     => 5,
				'redirection' => 3,
				'sslverify'   => true,
				'headers'     => array(
					'User-Agent' => 'WooCommerce Plugin ' . ARTSPAY_PLUGIN_VERSION,
				),
			)
		);
		if ( is_wp_error( $probe ) ) {
			set_transient( $https_probe_key, '0', 300 );
			return false;
		}
		$code = wp_remote_retrieve_response_code( $probe );
		$ok   = $code >= 200 && $code < 400;
		set_transient( $https_probe_key, $ok ? '1' : '0', 300 );
		return $ok;
	}

	/**
	 * Whether the last domain registration request succeeded for the current user.
	 */
	public static function is_domain_registered_ok_for_current_user(): bool {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return false;
		}
		$v = get_user_meta( $user_id, self::META_DOMAIN_REGISTERED_OK, true );
		return $v === '1' || true === $v || 1 === $v || 'true' === $v;
	}

	/**
	 * @param bool $ok Last verification outcome.
	 */
	private static function set_verification_file_ok_for_current_user( bool $ok ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		update_user_meta( $user_id, self::META_VERIFICATION_FILE_OK, $ok ? '1' : '0' );
	}

	/**
	 * @param bool $ok Last registration outcome.
	 */
	private static function set_domain_registered_ok_for_current_user( bool $ok ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		update_user_meta( $user_id, self::META_DOMAIN_REGISTERED_OK, $ok ? '1' : '0' );
	}

	/**
	 * Back URL to the Apple Pay settings sub-page.
	 */
	private static function back_url( string $result_key = '' ): string {
		$args = array(
			'page'            => 'wc-settings',
			'tab'             => 'checkout',
			'section'         => 'fatzebra',
			'artspay_express' => 'express_checkouts',
		);
		if ( '' !== $result_key ) {
			$args['artspay_applepay_result'] = $result_key;
		}
		return (string) add_query_arg(
			$args,
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Resolve the gateway instance.
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
		return null;
	}
}

