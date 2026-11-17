=== WooCommerce ArtsPay Gateway ===
Contributors: artspayintegration
Tags: woocommerce, payment gateway, credit card, fat zebra, subscriptions
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.0
Requires Plugins: woocommerce
Stable tag: 3.3.0
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

**Checkout:** ArtsPay works with both the Checkout block (the default on new stores) and the classic (shortcode) checkout, including Google Pay and Apple Pay on the block Cart and Checkout.

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

Yes. Turn them on under **Express checkouts** in the ArtsPay settings. The buttons appear on the cart and checkout. Google Pay needs a Fat Zebra Google Pay merchant ID, and Apple Pay needs your domain verified and registered from the same page. Both need HTTPS on a live store.

== Changelog ==

= 3.3.0 =
* Add - Block Cart and Checkout support. ArtsPay now appears in the Checkout block with the secure hosted card form (including 3D Secure) or the on-site card form, and captcha. Stores no longer need to switch to the classic checkout.
* Add - Google Pay and Apple Pay on the block Cart and Checkout, as express payment buttons. They replace the earlier buttons on block pages, which filled in classic checkout fields the block checkout never sent.
* Fix - Google Pay and Apple Pay on the block Cart and Checkout failed with "Captcha verification failed" when captcha was on. Wallet payments now skip the captcha (the attempt limits still apply), and a failed wallet payment on the Cart block now shows its reason under the buttons.
* Fix - The Apple Pay button on the block Cart and Checkout did nothing when pressed. WordPress's React doesn't pass click handlers to Apple's button element, so the plugin now listens for the click directly.
* Fix - Google Pay no longer sends `merchantOrigin`, which Google reserves for platforms paying on behalf of other merchants. Matches pay.artspay.com.
* Update - The Google Pay button on the block Cart and Checkout is now the same height as the Apple Pay button (48px).
* Fix - Payment errors (such as a decline) now show their reason on the block checkout instead of a general error.
* Update - Captcha and wallet tokens are read from the payment data WooCommerce passes to the gateway, so they work with both checkouts.
* Update - Apple Pay setup now points to the ArtsPay Dashboard (Settings → Wallets → Add domain) to register the store's domain, and links both verification files (sandbox and production), marking the one for the store's current mode. The verification check now says which file the site serves, and warns when it doesn't match the mode. Stores in sandbox mode register from the plugin, since the Dashboard registers with the live Fat Zebra merchant. Registering from the plugin is still available for live merchants without Dashboard access.
* Update - The secure hosted card form now fills the width of the payment box and lines up on the left, with expiry date and security code side by side. Fat Zebra loads its stylesheet from the plugin on HTTPS stores (ArtsPay's standard stylesheet otherwise); the `artspay_hosted_card_css_url` filter still overrides it.
* Update - Cloudflare Turnstile stays hidden unless Cloudflare needs the customer to do something.
* Update - Block checkout: the express payment section has a plain, centred "Express Checkout" title without the surrounding box, and the divider below it reads "Or".
* Remove - The "Card details are entered securely with Fat Zebra" line under the hosted card form.
* Remove - The rule from 3.1.3 that rejected card payments through the Store API. The block checkout uses that route; attempt limits and captcha still apply to it.
* Dev - Hosted form and 3D Secure code shared by both checkouts (`artspay-fatzebra.js`). Block checkout browser tests in `tests/e2e`.

= 3.2.0 =
* Add - Secure hosted card form. Set Card form to "Secure hosted form" and customers enter their card in Fat Zebra's form inside your checkout. Card numbers go straight to Fat Zebra and never reach your server, which keeps the store out of most PCI scope (SAQ A). Needs the Gateway Shared Secret.
* Add - 3D Secure. With the hosted form, the customer's bank can verify the cardholder before a card is charged, which shifts liability for fraudulent chargebacks to the card issuer. Payments that fail verification are blocked, and each order gets a note with the outcome. Needs 3D Secure turned on for the merchant account and an OAuth client from Fat Zebra. Deferred payments and $0 orders skip it.
* Update - Settings: Forter, 3D Secure and Captcha now each have their own page under a new Fraud & security section, with an On, Off or Needs setup label on each. The Gateway Shared Secret is shown again under Account details.
* Fix - Capturing a deferred payment marked the order as paid even when the card was declined. A declined capture now leaves the order on hold with a note.
* Fix - Works with WooCommerce's order storage (HPOS), the default on new stores. Card tokens, deferred payment details, fraud results and the device ID were saved where HPOS stores don't read them, which could break deferred capture, refunds and renewals. Existing orders keep their data.
* Fix - Fat Zebra rejected a payment as "Reference is already taken" when an order was retried, captured later, or shared a merchant account with another store. Every attempt now gets its own reference: the order number plus a short suffix.
* Security - 3D Secure checks are limited to 10 per session and 20 per IP address an hour (filter: `artspay_3ds_limits`), so the check can't be used to test cards.
* Update - Forter now receives "pickup" or "international" as the shipping method when they apply, instead of always "low_cost". Filter: `artspay_fraud_shipping_method`.
* Remove - Direct Post code and its hidden setting. The secure hosted card form replaces it.
* Dev - Browser tests (Playwright) for checkout, 3D Secure and settings in `tests/e2e`, with a script that builds a throwaway test store.

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

= 3.3.0 =
Adds support for the block Cart and Checkout, including Google Pay and Apple Pay. If you switched your Checkout page to the classic checkout for ArtsPay, you can switch it back.

= 3.2.0 =
Adds an optional secure hosted card form and 3D Secure, moves Forter, 3D Secure and Captcha onto their own settings pages, adds support for WooCommerce's order storage (HPOS), and fixes declined deferred captures being marked as paid. Recommended for all stores.

= 3.1.4 =
Fixes paying by card after a declined Google Pay or Apple Pay payment. Recommended for stores using wallets.

= 3.1.3 =
Adds protection against card testing attacks and fixes the Turnstile captcha at checkout. Recommended for all stores. Direct Post is switched off in this release.

= 3.1.2 =
Fixes cardholder names with apostrophes and tightens checkout input handling. Recommended for all stores.
