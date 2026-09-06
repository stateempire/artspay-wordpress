<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * Encryption related functionality
 *
 * @package ArtsPay
 */

namespace ArtsPay\Traits;

/**
 * Encryption trait.
 */
trait Encryption {
	/**
	 * Encrypt string.
	 *
	 * @param string $pure_string    String to encrypt.
	 * @param string $encryption_key Encryption key.
	 *
	 * @return string
	 */
	private function encrypt( string $pure_string, string $encryption_key ): string {
		$cipher         = 'AES-256-CBC';
		$options        = OPENSSL_RAW_DATA;
		$hash_algo      = 'sha256';
		$ivlen          = openssl_cipher_iv_length( $cipher );
		$iv             = openssl_random_pseudo_bytes( $ivlen );
		$ciphertext_raw = openssl_encrypt( $pure_string, $cipher, $encryption_key, $options, $iv );
		$hmac           = hash_hmac( $hash_algo, $ciphertext_raw, $encryption_key, true );
		return $iv . $hmac . $ciphertext_raw;
	}

	/**
	 * Decrypt string.
	 *
	 * @param string $encrypted_string Encrypted string.
	 * @param string $encryption_key   Encryption key.
	 *
	 * @return string
	 */
	private function decrypt( string $encrypted_string, string $encryption_key ): string {
		$cipher             = 'AES-256-CBC';
		$options            = OPENSSL_RAW_DATA;
		$hash_algo          = 'sha256';
		$sha2len            = 32;
		$ivlen              = openssl_cipher_iv_length( $cipher );
		$iv                 = substr( $encrypted_string, 0, $ivlen );
		$hmac               = substr( $encrypted_string, $ivlen, $sha2len );
		$ciphertext_raw     = substr( $encrypted_string, $ivlen + $sha2len );
		$original_plaintext = openssl_decrypt( $ciphertext_raw, $cipher, $encryption_key, $options, $iv );
		$calcmac            = hash_hmac( $hash_algo, $ciphertext_raw, $encryption_key, true );

		if ( hash_equals( $hmac, $calcmac ) ) {
			return $original_plaintext;
		}

		return '';
	}

	/**
	 * Helper function base64 url encode.
	 *
	 * @param string $input String to encode.
	 *
	 * @return string
	 */
	private function base64_url_encode( string $input ): string {
		return strtr( base64_encode( $input ), '+/=', '._-' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * Helper function base64 url decode.
	 *
	 * @param string $input String to decode.
	 *
	 * @return false|string
	 */
	private function base64_url_decode( string $input ): bool|string {
		return base64_decode( strtr( $input, '._-', '+/=' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}
}
