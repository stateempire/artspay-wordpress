<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * Captcha functionality
 *
 * @package ArtsPay
 */

namespace ArtsPay\Traits;

use ArtsPay\Assets\Core;

/**
 * Captcha trait.
 */
trait Captcha {
	/**
	 * Check if captcha is enabled.
	 *
	 * @return bool True if captcha is enabled.
	 */
	public function is_captcha_enabled(): bool {
		return 'yes' === $this->get_option( 'captcha_enabled' );
	}

	/**
	 * Get captcha settings.
	 *
	 * @return array Captcha settings.
	 */
	public function get_captcha_settings(): array {
		return array(
			'enabled'    => $this->is_captcha_enabled(),
			'provider'   => $this->get_option( 'captcha_provider', 'google' ),
			'site_key'   => $this->get_option( 'captcha_site_key' ),
			'secret_key' => $this->get_option( 'captcha_secret_key' ),
		);
	}

	/**
	 * Get captcha provider.
	 *
	 * @return string Captcha provider (google or cloudflare).
	 */
	public function get_captcha_provider(): string {
		return $this->get_option( 'captcha_provider', 'google' );
	}

	/**
	 * Render captcha field HTML.
	 */
	public function render_captcha_field(): void {
		if ( ! $this->is_captcha_enabled() ) {
			return;
		}

		$provider = $this->get_captcha_provider();
		$site_key = $this->get_option( 'captcha_site_key' );

		if ( empty( $site_key ) ) {
			return;
		}

		echo '<div class="captcha-field-wrapper" style="margin-top: 20px;">';
		echo '<div id="captcha-container" data-provider="' . esc_attr( $provider ) . '" data-site-key="' . esc_attr( $site_key ) . '"></div>';
		echo '<input type="hidden" name="captcha_token" id="captcha_token" />';
		echo '</div>';
	}

	/**
	 * Enqueue captcha scripts.
	 */
	public function enqueue_captcha_scripts(): void {
		if ( ! $this->is_captcha_enabled() ) {
			return;
		}

		$provider = $this->get_captcha_provider();
		$site_key = $this->get_option( 'captcha_site_key' );

		if ( empty( $site_key ) ) {
			return;
		}

		Core::register_scripts();

		$dependencies = array( 'jquery', 'artspay-core' );

		// Enqueue provider script and set dependency.
		if ( 'google' === $provider ) {
			wp_enqueue_script(
				'google-recaptcha',
				'https://www.google.com/recaptcha/api.js?render=' . $site_key,
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				true
			);
			$dependencies[] = 'google-recaptcha';
		} elseif ( 'cloudflare' === $provider ) {
			wp_enqueue_script(
				'cloudflare-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				true
			);
			$dependencies[] = 'cloudflare-turnstile';
		}

		wp_enqueue_script( 'artspay-core' );

		$captcha_payload = wp_json_encode(
			array(
				'provider' => $provider,
				'site_key' => $site_key,
			)
		);

		wp_enqueue_script(
			'artspay-captcha',
			ARTSPAY_PLUGIN_URL . 'assets/js/artspay-captcha.js',
			$dependencies,
			ARTSPAY_PLUGIN_VERSION,
			true
		);

		wp_add_inline_script(
			'artspay-captcha',
			'window.ArtsPay=window.ArtsPay||{};window.ArtsPay.captcha=' . $captcha_payload . ';window.artspay_captcha=window.ArtsPay.captcha;',
			'before'
		);
	}

	/**
	 * Verify captcha token with provider API.
	 *
	 * @param string $token Captcha token to verify.
	 *
	 * @return bool True if verification successful.
	 */
	public function verify_captcha_token( string $token ): bool {
		if ( ! $this->is_captcha_enabled() || empty( $token ) ) {
			return false;
		}

		$provider   = $this->get_captcha_provider();
		$secret_key = $this->get_option( 'captcha_secret_key' );

		if ( empty( $secret_key ) ) {
			return false;
		}

		$verify_url = '';
		$data       = array(
			'secret'   => $secret_key,
			'response' => $token,
		);

		// Set verification URL based on provider.
		if ( 'google' === $provider ) {
			$verify_url = 'https://www.google.com/recaptcha/api/siteverify';
		} elseif ( 'cloudflare' === $provider ) {
			$verify_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
		}

		if ( empty( $verify_url ) ) {
			return false;
		}

		// Add remote IP if available.
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$data['remoteip'] = wp_unslash( $_SERVER['REMOTE_ADDR'] );
		}

		// Make API request.
		$response = wp_remote_post(
			$verify_url,
			array(
				'body'    => $data,
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		return isset( $data['success'] ) && true === $data['success'];
	}

	/**
	 * Get captcha error message.
	 *
	 * @return string Error message for failed captcha verification.
	 */
	public function get_captcha_error_message(): string {
		return __( 'Captcha verification failed. Please try again or refresh the page to update the captcha.', 'artspay' );
	}
}
