<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * Fat Zebra API Client
 *
 * @package ArtsPay
 */

namespace ArtsPay\API;

use ArtsPay\Debug_Logger;
use Exception;
use WP_Error;

/**
 * Fat Zebra API Client for handling all API communications.
 */
class Client {
	/**
	 * API version.
	 *
	 * @var string
	 */
	private string $api_version = '1.0';

	/**
	 * Live API URL.
	 *
	 * @var string
	 */
	public string $live_url;

	/**
	 * Sandbox API URL.
	 *
	 * @var string
	 */
	public string $sandbox_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->live_url    = "https://gateway.fatzebra.com.au/v$this->api_version/purchases";
		$this->sandbox_url = "https://gateway.sandbox.fatzebra.com.au/v$this->api_version/purchases";
	}

	/**
	 * Make a generic API request.
	 *
	 * @param string $endpoint    API endpoint (e.g., 'purchases', 'refunds', 'credit_cards').
	 * @param array  $payload     Request payload - array will be JSON encoded, string used as-is.
	 * @param array  $credentials API credentials (username, api_token).
	 * @param bool   $is_sandbox  Whether to use sandbox URL.
	 *
	 * @return array|WP_Error
	 */
	public function make_request( string $endpoint, array $payload, array $credentials, bool $is_sandbox ): array|WP_Error {
		$base_url = $is_sandbox ? $this->sandbox_url : $this->live_url;
		$url      = str_replace( 'purchases', $endpoint, $base_url );

		$args = array(
			'method'  => 'POST',
			'body'    => wp_json_encode( $payload ),
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $credentials['username'] . ':' . $credentials['api_token'] ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				'X-Test-Mode'   => $is_sandbox,
				'User-Agent'    => 'WooCommerce Plugin ' . ARTSPAY_PLUGIN_VERSION,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( Debug_Logger::is_enabled() ) {
			Debug_Logger::log(
				sprintf(
					'API request: %s (sandbox=%s)',
					$endpoint,
					$is_sandbox ? 'yes' : 'no'
				),
				'debug',
				array(
					'url'     => $url,
					'payload' => Debug_Logger::redact_payload_for_log( $payload ),
				)
			);
		}

		try {
			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				if ( Debug_Logger::is_enabled() ) {
					Debug_Logger::log( 'API transport error: ' . $response->get_error_message(), 'error' );
				}
				return $response;
			}

			$response = (array) $response;

			$http_code = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
			if ( 200 !== $http_code && 201 !== $http_code ) {
				$msg = isset( $response['response']['message'] ) ? (string) $response['response']['message'] : '';
				$body = isset( $response['body'] ) ? (string) $response['body'] : '';

				$body_summary = '';
				if ( '' !== $body ) {
					$decoded = json_decode( $body, true );
					if ( is_array( $decoded ) ) {
						if ( isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
							$errs = array_map( 'strval', $decoded['errors'] );
							$body_summary = implode( ', ', $errs );
						} elseif ( isset( $decoded['error'] ) ) {
							$body_summary = (string) $decoded['error'];
						} elseif ( isset( $decoded['message'] ) ) {
							$body_summary = (string) $decoded['message'];
						}
					}
					if ( '' === $body_summary ) {
						// Fallback: keep it short and readable.
						$body_summary = mb_substr( trim( wp_strip_all_tags( $body ) ), 0, 280 );
					}
				}

				if ( Debug_Logger::is_enabled() ) {
					Debug_Logger::log( sprintf( 'API HTTP %d: %s', $http_code, $msg ), 'debug' );
				}
				$full_msg = 'API request failed: ' . $msg;
				if ( '' !== $body_summary ) {
					$full_msg .= ' — ' . $body_summary;
				}

				return new WP_Error( 1, $full_msg, $response );
			}

			$response_data = json_decode( $response['body'] );

			if ( ! $response_data->successful ) {
				if ( Debug_Logger::is_enabled() ) {
					$err_summary = '';
					if ( isset( $response_data->errors ) && is_array( $response_data->errors ) ) {
						$err_summary = implode( ', ', array_map( 'strval', $response_data->errors ) );
					}
					Debug_Logger::log( 'API gateway unsuccessful: ' . ( $err_summary !== '' ? $err_summary : 'no error list' ), 'debug' );
				}
				return new WP_Error( 2, 'Gateway Error', $response_data->errors );
			}

			if ( Debug_Logger::is_enabled() ) {
				$rid = '';
				if ( isset( $response_data->response ) && is_object( $response_data->response ) && isset( $response_data->response->id ) ) {
					$rid = (string) $response_data->response->id;
				}
				Debug_Logger::log( sprintf( 'API %s success (response id=%s)', $endpoint, $rid !== '' ? $rid : 'n/a' ), 'debug' );
			}

			return array(
				'successful'    => $response_data->successful,
				'response'      => $response_data->response,
				'response_data' => $response_data,
				'raw_response'  => $response,
			);
		} catch ( Exception $e ) {
			if ( Debug_Logger::is_enabled() ) {
				Debug_Logger::log( 'API exception: ' . $e->getMessage(), 'error' );
			}
			return new WP_Error( 4, 'Unknown Error', $e );
		}
	}

	/**
	 * Process a purchase transaction.
	 *
	 * @param array $params      Payment parameters.
	 * @param array $credentials API credentials.
	 * @param bool  $is_sandbox  Whether to use sandbox URL.
	 *
	 * @return array|WP_Error
	 */
	public function purchase( array $params, array $credentials, bool $is_sandbox ): array|WP_Error {
		$endpoint = 'purchases';

		if ( isset( $params['deferred'] ) && $params['deferred'] ) {
			$endpoint = 'credit_cards';
			$payload  = array(
				'card_holder' => $params['card_holder'],
				'card_number' => $params['card_number'],
				'card_expiry' => $params['card_expiry'],
				'cvv'         => $params['cvv'],
			);

			if ( isset( $params['customer_ip'] ) ) {
				$payload['customer_ip'] = $params['customer_ip'];
			}
		} else {
			$payload = $params;
		}

		$result = $this->make_request( $endpoint, $payload, $credentials, $is_sandbox );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( isset( $params['deferred'] ) && $params['deferred'] ) {
			return array(
				'card_token'  => $result['response']->token,
				'card_number' => $result['response']->card_number,
				'card_expiry' => $result['response']->card_expiry,
				'card_holder' => $result['response']->card_holder,
			);
		}

		if ( ! $result['response']->successful ) {
			return new WP_Error(
				3,
				'Payment Declined',
				array(
					'message'        => $result['response']->message,
					'id'             => $result['response']->id,
					'response_data'  => $result['response_data'],
				)
			);
		}

		return array(
			'transaction_id' => $result['response']->id,
			'card_token'     => $result['response']->card_token,
			'response_data'  => $result['response_data'],
		);
	}

	/**
	 * Process a tokenized purchase transaction.
	 *
	 * @param array $params        Payment parameters.
	 * @param array $credentials   API credentials.
	 * @param bool  $is_sandbox    Whether to use sandbox URL.
	 * @param bool  $fraud_enabled Whether fraud detection is enabled.
	 *
	 * @return array|WP_Error
	 */
	public function purchase_with_token( array $params, array $credentials, bool $is_sandbox, bool $fraud_enabled = false ): array|WP_Error {
		$result = $this->make_request( 'purchases', $params, $credentials, $is_sandbox );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result['response']->successful ) {
			$response_data = array(
				'successful'     => $result['response']->successful,
				'transaction_id' => $result['response']->id,
				'message'        => $result['response']->message,
			);

			if ( $fraud_enabled && isset( $result['response']->fraud_result ) ) {
				$response_data['fraud_result']   = $result['response']->fraud_result;
				$response_data['fraud_messages'] = implode( ', ', $result['response']->fraud_messages );
			}

			return $response_data;
		}

		$response_data = array(
			'successful'     => $result['response']->successful,
			'transaction_id' => $result['response']->id,
			'card_token'     => $result['response']->card_token,
		);

		if ( $fraud_enabled && isset( $result['response']->fraud_result ) ) {
			$response_data['fraud_result']   = $result['response']->fraud_result;
			$response_data['fraud_messages'] = implode( ', ', $result['response']->fraud_messages );
		}

		return $response_data;
	}

	/**
	 * Process a wallet purchase transaction (Google Pay, Apple Pay).
	 *
	 * @param array $params      Payment parameters.
	 * @param array $credentials API credentials.
	 * @param bool  $is_sandbox  Whether to use sandbox URL.
	 *
	 * @return array|WP_Error
	 */
	public function purchase_with_wallet( array $params, array $credentials, bool $is_sandbox ): array|WP_Error {
		$payload = $this->get_wallet_payload( $params, $is_sandbox );
		$result  = $this->make_request( 'purchases', $payload, $credentials, $is_sandbox );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result['response']->successful ) {
			return new WP_Error(
				3,
				'Payment Declined',
				array(
					'message'       => $result['response']->message,
					'id'            => $result['response']->id,
					'response_data' => $result['response_data'],
				)
			);
		}

		return array(
			'transaction_id' => $result['response']->id,
			'reference'      => $result['response']->reference,
			'card_token'     => $result['response']->card_token,
			'response_data'  => $result['response_data'],
		);
	}

	/**
	 * Process a refund transaction.
	 *
	 * @param array $params      Refund parameters.
	 * @param array $credentials API credentials.
	 * @param bool  $is_sandbox  Whether to use sandbox URL.
	 *
	 * @return array|WP_Error
	 */
	public function refund( array $params, array $credentials, bool $is_sandbox ): array|WP_Error {
		$result = $this->make_request( 'refunds', $params, $credentials, $is_sandbox );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! $result['response']->successful ) {
			return new WP_Error( 3, 'Refund Declined: ' . $result['response']->message, $result['response'] );
		}

		return array(
			'successful' => $result['response']->successful,
			'refund_id'  => $result['response']->id,
			'message'    => 'Refund Approved',
		);
	}

	/**
	 * Tokenize a credit card.
	 *
	 * @param array|string $payload     Card tokenization payload.
	 * @param array        $credentials API credentials.
	 * @param bool         $is_sandbox  Whether to use sandbox URL.
	 *
	 * @return array|WP_Error
	 */
	public function tokenize( array|string $payload, array $credentials, bool $is_sandbox ): array|WP_Error {
		$result = $this->make_request( 'credit_cards', $payload, $credentials, $is_sandbox );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'card_token'     => $result['response']->token,
			'card_number'    => $result['response']->card_number,
			'card_expiry'    => $result['response']->card_expiry,
			'card_holder'    => $result['response']->card_holder,
			'transaction_id' => $result['response']->token,
		);
	}

	/**
	 * Get payload data for Google wallet requests.
	 *
	 * @param array $params  Tokenization parameters.
	 * @param bool  $is_test Whether this is a test transaction.
	 *
	 * @return array
	 */
	public function get_wallet_payload( array $params, bool $is_test ): array {
		$wallet_type = isset( $params['wallet_type'] ) ? strtoupper( (string) $params['wallet_type'] ) : 'GOOGLE';
		$payload = array(
			'amount'      => $params['amount'],
			'reference'   => $params['reference'],
			'customer_ip' => $params['customer_ip'],
			'currency'    => $params['currency'],
			'test'        => $is_test,
			'wallet'      => array(
				'type'  => $wallet_type,
				'token' => $params['wallet_token'],
			),
		);

		if ( isset( $params['fraud'] ) ) {
			$payload['fraud'] = $params['fraud'];
		}

		return $payload;
	}
}
