# Upgrading from a pre-3.1.0 install

Version 3.1.0 was a structural refactor (namespaced `ArtsPay\*` classes, PSR-4 autoloading) that also removed several settings that had become redundant or unused. If you're upgrading a store that's been running an older version of this plugin, read this before you update.

## Settings that no longer exist

### Test Mode — merged into Sandbox Mode

Older versions had two separate toggles: **Sandbox Mode** (which gateway URL to call) and **Test Mode** (whether transactions were flagged as test to Fat Zebra). These are now the same setting.

- Before: you could run, for example, the sandbox gateway with Test Mode off, or the live gateway with Test Mode on.
- Now: **Sandbox Mode** alone controls both — sandbox on always sends `test: true` to Fat Zebra, live always sends `test: false`.

If your store had these two settings in different states, transaction behaviour changes after upgrading. Check **WooCommerce → Settings → Payments → ArtsPay** and confirm **Sandbox Mode** reflects what you actually want (off for real, live-charged transactions).

### Hosted Payment Page (HPP) — removed entirely

The **Use Hosted Payment Page**, **Logo URL**, and **CSS URL** settings are gone; HPP checkout is no longer supported. If your store was using HPP, checkout now falls through to **Inline** (default) or **Direct Post** (if you enable it and configure the Gateway Shared Secret). Test checkout on staging after upgrading — the customer-facing flow changes from a redirect to an on-site form.

### Payment Method Title / Payment Icon — removed

These admin fields (custom checkout title text and a custom icon URL) no longer exist. The gateway now shows a fixed title ("Credit / Debit Card", translatable via the `artspay` text domain) and a fixed icon. If you need a custom icon, it's now a developer-level change via the `woocommerce_fatzebra_icon` filter rather than an admin setting — there is no admin UI replacement for icon customization.

## For developers extending this plugin

The 3.1.0 refactor also renamed/moved classes — if you have custom code hooking into the old structure, it will break silently (no deprecation shims exist):

- `includes/Gateways/GooglePayGateway.php` no longer exists; Google Pay now lives under `includes/Express/GooglePay.php`.
- Classes moved under the `ArtsPay\*` namespace with PSR-4 autoloading — any direct `require`/`include` of plugin files by third-party code should switch to referencing the namespaced classes instead.

## Checklist

1. Note your current **Sandbox Mode** and (if present) **Test Mode** settings before upgrading.
2. Upgrade the plugin.
3. Re-open **WooCommerce → Settings → Payments → ArtsPay** and confirm Sandbox Mode is correct — this now fully controls test-vs-live behaviour.
4. If you were using the Hosted Payment Page, switch to Inline or Direct Post and test a full checkout on staging.
5. If you'd customized the payment title or icon, expect the defaults; use the `woocommerce_fatzebra_icon` filter if a custom icon is required.
6. Run a full sandbox checkout (purchase, refund, and subscription renewal if applicable) before considering the upgrade complete.
