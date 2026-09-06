=== WooCommerce ArtsPay Gateway ===
Contributors: artspayintegration
Tags: woocommerce, payment gateway, credit card, fat zebra, subscriptions
Requires at least: 6.0
Tested up to: [TODO: scamped, to add when deploying - unpublished]
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 3.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept card payments via the Fat Zebra gateway with ArtsPay — optional Forter fraud screening, captcha, and deferred capture. Supports WooCommerce Subscriptions.

== Description ==

WooCommerce ArtsPay Gateway connects your WooCommerce store to **ArtsPay** / **Fat Zebra** for card processing.

**Features:**

* Inline (server-side) card checkout — the default flow.
* Direct Post — browser-side tokenization via Fat Zebra's JS, charged with a card token (requires a Gateway Shared Secret).
* Optional **Forter fraud screening** (via Fat Zebra), with order-level audit trail (`_artspay_fraud_result`, `_artspay_fraud_messages`) and optional email alerts on decline.
* Optional captcha (reCAPTCHA v3 or Cloudflare Turnstile) before payment submission.
* Deferred payments — tokenize and hold an order for manual capture later.
* WooCommerce Subscriptions support.

See [FRAUD.md](FRAUD.md) in the repository for full detail on fraud screening behaviour, and note that 3-D Secure (3DS) is **not** implemented in this plugin.

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New → Upload Plugin** and choose the plugin ZIP.
2. Install and activate the plugin.
3. Go to **WooCommerce → Settings → Payments** and find **ArtsPay** (or **ArtsPay Credit Card**). If it's missing, confirm the plugin is active under **Plugins**.
4. Enable the method, set the customer-facing title, enter your Fat Zebra / ArtsPay gateway credentials, and save.
5. Test checkout in sandbox mode before going live.

Full configuration detail (Direct Post, deferred payments, Forter, captcha) is in the repository [README](README.md).

== Frequently Asked Questions ==

= Does this support 3-D Secure (3DS)? =

No. Card flows use the standard Fat Zebra purchase path; 3DS is not implemented in this plugin.

= How do I test fraud screening outcomes in sandbox? =

Use the billing emails `accept@email.com`, `challenge@email.com`, or `deny@email.com` to simulate Accept / Challenge / Deny outcomes. See Fat Zebra's [fraud screening docs](https://www.artspay.com/docs/api/gateway/purchases/create-a-purchase-with-fraud-screening-1).

= Does this support Google Pay or Apple Pay? =

Wallet/express checkout support exists in the codebase but its enabled/disabled status varies by release — check the Changelog below and the plugin settings for the version you're running.

== Screenshots ==

[TODO: scamped, to add when deploying]

== Changelog ==

= 3.1.1 =
[TODO: scamped, to add when deploying]

= 3.1.0 =
* Dev - Refactored plugin structure with namespaced `ArtsPay\*` classes and PSR-4-style autoloading under `includes/`.
* Remove - Removed unused hosted payment page (HPP); card payments are inline checkout or Direct Post only.
* Dev - Frontend: consolidated `window.ArtsPay` configuration (`artsPayLocalized` and captcha settings); split JavaScript into `artspay-core`, `artspay-checkout-autofill`, `artspay-googlepay`, and `artspay-captcha`.
* Dev - Express checkout PHP moved to `includes/Express/` (Google Pay handler and Cart/Checkout block footer mounts); removed legacy standalone `GooglePayGateway.php`.
* Update - Google Pay / Apple Pay express checkout UI and storefront hooks are disabled in this release; wallet flows are planned to return in 3.1.1.

== Upgrade Notice ==

= 3.1.1 =
[TODO: scamped, to add when deploying]
