=== WooCommerce ArtsPay Gateway ===
Contributors: artspayintegration
Tags: woocommerce, payment gateway, credit card, fat zebra, subscriptions
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 3.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept card payments via the Fat Zebra gateway with ArtsPay — optional secure hosted card form, 3D Secure, Forter fraud screening, captcha, and deferred capture. Supports WooCommerce Subscriptions.

== Description ==

WooCommerce ArtsPay Gateway connects your WooCommerce store to **ArtsPay** / **Fat Zebra** for card processing.

**Features:**

* Inline (server-side) card checkout — the default flow.
* Optional secure hosted card form: Fat Zebra's card form inside your checkout, so card numbers never reach your server.
* Optional 3D Secure with the hosted card form, so the card issuer verifies the cardholder and carries the chargeback liability.
* Optional **Forter fraud screening** (via Fat Zebra), with order-level audit trail (`_artspay_fraud_result`, `_artspay_fraud_messages`) and optional email alerts on decline.
* Optional captcha (reCAPTCHA v3 or Cloudflare Turnstile) before payment submission.
* Payment attempt limits per order, session and IP address to stop card testing.
* Deferred payments — tokenize and hold an order for manual capture later.
* WooCommerce Subscriptions support.

**Checkout:** ArtsPay currently works with the classic (shortcode) checkout. If your Checkout page uses the Checkout block, edit the page, select the block and choose "Switch to classic checkout". Block checkout support is planned.

**PHP:** the plugin runs on PHP 8.0 or later, but we recommend PHP 8.3 or later, kept on the latest patch release. Older PHP versions no longer receive security fixes.

See [FRAUD.md](FRAUD.md) in the repository for full detail on fraud screening and 3D Secure behaviour.

== Installation ==

1. In your WordPress admin, go to **Plugins → Add New → Upload Plugin** and choose the plugin ZIP.
2. Install and activate the plugin.
3. Go to **WooCommerce → Settings → Payments** and find **ArtsPay** (or **ArtsPay Credit Card**). If it's missing, confirm the plugin is active under **Plugins**.
4. Enable the method, set the customer-facing title, enter your Fat Zebra / ArtsPay gateway credentials, and save.
5. Test checkout in sandbox mode before going live.

Full configuration detail (card form, 3D Secure, deferred payments, Forter, captcha) is in the repository [README](README.md).

== Frequently Asked Questions ==

= Does this support 3-D Secure (3DS)? =

Yes, from 3.2.0, with the secure hosted card form. ArtsPay must turn on 3D Secure for your merchant account, and you need an OAuth client from the Fat Zebra Merchant Dashboard. Set it up under **Fraud & security → 3D Secure**.

= How do I test fraud screening outcomes in sandbox? =

Use the billing emails `accept@email.com`, `challenge@email.com`, or `deny@email.com` to simulate Accept / Challenge / Deny outcomes. See Fat Zebra's [fraud screening docs](https://www.artspay.com/docs/api/gateway/purchases/create-a-purchase-with-fraud-screening-1).

= Does this support Google Pay or Apple Pay? =

Wallet/express checkout support exists in the codebase but its enabled/disabled status varies by release — check the Changelog below and the plugin settings for the version you're running.

== Screenshots ==

[TODO: scamped, to add when deploying]

== Changelog ==

= 3.2.0 =
* Add - Secure hosted card form. Set Card form to "Secure hosted form" and customers enter their card in Fat Zebra's form inside your checkout. Card numbers go straight to Fat Zebra and never reach your server, which keeps the store out of most PCI scope (SAQ A). Needs the Gateway Shared Secret.
* Add - 3D Secure. With the hosted form, the customer's bank can verify the cardholder before a card is charged, which shifts liability for fraudulent chargebacks to the card issuer. Payments that fail verification are blocked, and each order gets a note with the outcome. Needs 3D Secure turned on for the merchant account and an OAuth client from Fat Zebra. Deferred payments and $0 orders skip it.
* Update - Settings: Forter, 3D Secure and Captcha now each have their own page under a new Fraud & security section, with an On, Off or Needs setup label on each. The Gateway Shared Secret is shown again under Account details.

= 3.1.4 =
* Fix - After a declined Google Pay or Apple Pay payment, the spent wallet token stayed on the page. Paying with a card then resent the token and ignored the card, using up payment attempts.
* Fix - The Google Pay "complete any required fields" message now only appears when the customer has something left to do, not when the order is submitting by itself.

= 3.1.3 =
* Security - Payment attempts are now limited, which stops one order being used to test many cards. An order gets 5 attempts an hour and an IP address gets 10. When an order reaches its limit it is marked as failed and an order note is added.
* Security - Card payments sent directly to the Store API checkout endpoint are now rejected. The checkout page doesn't use this route, so it was only reachable by scripts.
* Security - reCAPTCHA v3 now checks the score (0.5 or higher) and action. Previously any valid token passed, including one from a likely bot.
* Security - Captcha tokens must now match the store's hostname on live stores.
* Fix - The Turnstile widget disappeared after WooCommerce refreshed the checkout, so customers could fail their first attempt. It also now appears when a customer switches to card payment.
* Fix - reCAPTCHA tokens are refreshed before they expire.
* Note - Correction to 3.1.2: WooCommerce's IP lookup still trusts the first X-Forwarded-For entry unless the host overwrites it, so the customer IP on orders can be faked. Attempt limits use their own lookup instead.
* Update - Every declined payment now adds an order note with the Fat Zebra transaction ID.
* Remove - Direct Post is switched off and hidden from the settings until it is rebuilt, because the script it needs isn't included in the plugin. Stores with it enabled now use the standard card form. Saved settings are kept.

= 3.1.2 =
* Fix - Cardholder names with an apostrophe (for example O'Brien) were sent to Fat Zebra with a stray backslash.
* Fix - PHP warnings when a checkout used the alternative card field names.
* Fix - Card number and CVV are reduced to digits before they are sent to Fat Zebra.
* Security - The fallback customer IP now comes from WooCommerce's own lookup and is validated, instead of trusting the X-Forwarded-For header as sent.
* Update - PHP 8.3 or later is now recommended. PHP 8.0 remains the minimum.
* Dev - CI checks syntax on PHP 8.0, 8.3 and 8.5, and the WordPress security sniffs now fail the build on any finding.
* Note - ArtsPay currently requires the classic (shortcode) checkout. Support for the block-based Checkout is planned.

= 3.1.1 =
* Add - Apple Pay on the web as an express checkout on the cart and checkout, with domain registration from the gateway settings and the Apple domain association file served from `/.well-known/`.
* Update - Google Pay express checkout re-enabled.

= 3.1.0 =
* Dev - Refactored plugin structure with namespaced `ArtsPay\*` classes and PSR-4-style autoloading under `includes/`.
* Remove - Removed unused hosted payment page (HPP); card payments are inline checkout or Direct Post only.
* Dev - Frontend: consolidated `window.ArtsPay` configuration (`artsPayLocalized` and captcha settings); split JavaScript into `artspay-core`, `artspay-checkout-autofill`, `artspay-googlepay`, and `artspay-captcha`.
* Dev - Express checkout PHP moved to `includes/Express/` (Google Pay handler and Cart/Checkout block footer mounts); removed legacy standalone `GooglePayGateway.php`.
* Update - Google Pay / Apple Pay express checkout UI and storefront hooks are disabled in this release; wallet flows are planned to return in 3.1.1.

== Upgrade Notice ==

= 3.2.0 =
Adds an optional secure hosted card form and 3D Secure, and moves Forter, 3D Secure and Captcha onto their own settings pages. Nothing changes at checkout until you switch them on.

= 3.1.4 =
Fixes paying by card after a declined Google Pay or Apple Pay payment. Recommended for stores using wallets.

= 3.1.3 =
Adds protection against card testing attacks and fixes the Turnstile captcha at checkout. Recommended for all stores. Direct Post is switched off in this release.

= 3.1.2 =
Fixes cardholder names with apostrophes and tightens checkout input handling. Recommended for all stores.
