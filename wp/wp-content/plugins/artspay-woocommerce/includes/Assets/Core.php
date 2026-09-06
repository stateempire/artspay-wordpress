<?php /* phpcs:ignore WordPress.Files.FileName */
/**
 * Registers shared frontend script handles (artspay-core).
 *
 * @package ArtsPay
 */

namespace ArtsPay\Assets;

/**
 * Core frontend assets registration (single registration point).
 */
final class Core {
	/**
	 * Whether artspay-core has been registered.
	 *
	 * @var bool
	 */
	private static bool $registered = false;

	/**
	 * Register shared scripts (idempotent).
	 */
	public static function register_scripts(): void {
		if ( self::$registered ) {
			return;
		}

		wp_register_script(
			'artspay-core',
			ARTSPAY_PLUGIN_URL . 'assets/js/artspay-core.js',
			array( 'jquery' ),
			ARTSPAY_PLUGIN_VERSION,
			true
		);

		self::$registered = true;
	}

	/**
	 * True if artspay-core is registered.
	 */
	public static function is_registered(): bool {
		return self::$registered;
	}
}
