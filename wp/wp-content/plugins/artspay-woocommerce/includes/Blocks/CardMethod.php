<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * ArtsPay on the block Cart and Checkout.
 *
 * Registers the card payment method (secure hosted form or on-site fields, with captcha and
 * 3D Secure) and Google Pay and Apple Pay as express payment methods. Payments still go through
 * CreditCardGateway::process_payment(): the Store API copies the block's payment data into $_POST.
 *
 * @package ArtsPay
 */

namespace ArtsPay\Blocks;

use ArtsPay\Gateways\CreditCardGateway;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Card payment method for the block checkout.
 */
final class CardMethod extends AbstractPaymentMethodType {
	/**
	 * Payment method name: the gateway ID.
	 *
	 * @var string
	 */
	protected $name = 'fatzebra';

	/**
	 * Gateway instance.
	 *
	 * @var CreditCardGateway|null
	 */
	private ?CreditCardGateway $gateway = null;

	/**
	 * Load settings.
	 */
	public function initialize(): void {
		$this->settings = get_option( 'woocommerce_fatzebra_settings', array() );
	}

	/**
	 * The gateway WooCommerce has loaded.
	 */
	private function gateway(): ?CreditCardGateway {
		if ( null === $this->gateway && function_exists( 'WC' ) ) {
			$gateways      = WC()->payment_gateways()->payment_gateways();
			$this->gateway = isset( $gateways['fatzebra'] ) && $gateways['fatzebra'] instanceof CreditCardGateway ? $gateways['fatzebra'] : null;
		}

		return $this->gateway;
	}

	/**
	 * Whether ArtsPay is switched on.
	 */
	public function is_active(): bool {
		return 'yes' === $this->get_setting( 'enabled', 'no' );
	}

	/**
	 * Scripts for the block Cart and Checkout. Provider scripts (Google Pay, Apple Pay, captcha,
	 * device fingerprint) load only when the matching feature is on.
	 *
	 * @return string[]
	 */
	public function get_payment_method_script_handles(): array {
		$gateway = $this->gateway();
		$deps    = array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-data', 'wp-html-entities', 'artspay-fatzebra' );

		CreditCardGateway::register_hosted_card_assets();
		wp_enqueue_style( 'artspay-hosted-card' );
		wp_enqueue_style( 'artspay-blocks', ARTSPAY_PLUGIN_URL . 'assets/css/blocks.css', array(), ARTSPAY_PLUGIN_VERSION );

		if ( $gateway ) {
			if ( $gateway->get_express_google_pay() && $gateway->get_express_google_pay()->get_blocks_config() ) {
				wp_register_script( 'google-pay-api', 'https://pay.google.com/gp/p/js/pay.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				$deps[] = 'google-pay-api';
			}
			if ( $gateway->get_express_apple_pay() && $gateway->get_express_apple_pay()->get_blocks_config() ) {
				wp_register_script( 'apple-pay-js-sdk', 'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				$deps[] = 'apple-pay-js-sdk';
			}

			$captcha = $gateway->get_captcha_settings();
			if ( $captcha['enabled'] && '' !== (string) $captcha['site_key'] ) {
				if ( 'google' === $captcha['provider'] ) {
					wp_register_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( (string) $captcha['site_key'] ), array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
					$deps[] = 'google-recaptcha';
				} elseif ( 'cloudflare' === $captcha['provider'] ) {
					wp_register_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
					$deps[] = 'cloudflare-turnstile';
				}
			}

			$device_url = $gateway->get_device_id_script_url();
			if ( '' !== $device_url ) {
				wp_register_script( 'fz-fraud-deviceid', $device_url, array(), ARTSPAY_PLUGIN_VERSION, false );
				$deps[] = 'fz-fraud-deviceid';
			}
		}

		wp_register_script( 'artspay-blocks', ARTSPAY_PLUGIN_URL . 'assets/js/artspay-blocks.js', $deps, ARTSPAY_PLUGIN_VERSION, true );

		return array( 'artspay-blocks' );
	}

	/**
	 * Data for the block script, read with getSetting( 'fatzebra_data' ).
	 *
	 * @return array<string, mixed>
	 */
	public function get_payment_method_data(): array {
		$gateway = $this->gateway();
		if ( ! $gateway ) {
			return array();
		}

		$captcha = $gateway->get_captcha_settings();
		$hosted  = $gateway->is_hosted_card_enabled();

		return array(
			'title'         => $gateway->title,
			'supports'      => array_values( array_filter( $gateway->supports, array( $gateway, 'supports' ) ) ),
			'icons'         => $gateway->get_card_logo_list(),
			'sandboxNotice' => $gateway->get_sandbox_checkout_notice_html(),
			'cardForm'      => $hosted ? 'hosted' : 'onsite',
			'hostedCard'    => $hosted ? $gateway->get_hosted_card_config() : null,
			'captcha'       => $captcha['enabled'] && '' !== (string) $captcha['site_key']
				? array(
					'provider' => (string) $captcha['provider'],
					'siteKey'  => (string) $captcha['site_key'],
				)
				: null,
			'deviceId'      => '' !== $gateway->get_device_id_script_url(),
			'googlePay'     => $gateway->get_express_google_pay() ? $gateway->get_express_google_pay()->get_blocks_config() : null,
			'applePay'      => $gateway->get_express_apple_pay() ? $gateway->get_express_apple_pay()->get_blocks_config() : null,
			'strings'       => array(
				'cardNumber'     => __( 'Card number', 'artspay' ),
				'expiry'         => __( 'Expiry (MM / YY)', 'artspay' ),
				'cvc'            => __( 'Card code (CVC)', 'artspay' ),
				'cardIncomplete' => __( 'Enter your card number, expiry date and card code.', 'artspay' ),
				'captchaMissing' => __( 'Complete the captcha check, then place your order again.', 'artspay' ),
				'captchaError'   => __( 'The captcha check could not run. Refresh the page and try again.', 'artspay' ),
				'walletError'    => __( 'The payment could not be started. Try again or pay by card.', 'artspay' ),
			),
		);
	}
}
