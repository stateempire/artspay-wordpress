# WooCommerce ArtsPay Gateway

Official ArtsPay payment integration for WooCommerce: accept cards via the Fat Zebra gateway, with an optional secure hosted card form, 3D Secure, fraud screening (Forter), captcha, and deferred capture. Supports WooCommerce Subscriptions.

**Plugin metadata:** see [`artspay-woocommerce.php`](artspay-woocommerce.php) (version, WordPress and PHP requirements, text domain).

**License:** GNU General Public License v2 or later — see [`LICENSE`](LICENSE).

## Requirements

- **WordPress** 6.0 or higher (see plugin header for the authoritative minimum).
- **WooCommerce** (required). Works with the Checkout block and the classic (shortcode) checkout.
- **PHP** 8.0 or higher. We recommend PHP 8.3 or later, kept on the latest patch release: PHP 8.0 and 8.1 no longer receive security fixes, and 8.2's end on 31 December 2026.
- **WooCommerce Subscriptions** (optional; required only for subscription features).

This repository does not publish a formal L-n version support policy yet. Test changes against the WordPress and WooCommerce versions your merchants use; align dependency claims with the plugin header when releasing.

## Installation

1. Download the plugin ZIP from [GitHub Releases](https://github.com/artspay/artspay-woocommerce-plugin/releases) (a `.sha256` checksum is published alongside each ZIP).
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin** and choose the ZIP.
3. Click **Install Now**, then **Activate**.
4. If the plugin name appears blank at first, the gateway may be whitelabelled: open **Plugins**, use **Manage** (or the gateway settings), and save to set the name.

## Quick Setup

Fastest path to a working sandbox checkout:

1. Install and activate the plugin (see [Installation](#installation) above).
2. Go to **WooCommerce → Settings → Payments** and enable **ArtsPay** (or **ArtsPay Credit Card**).
3. Confirm **Sandbox Mode** is on — it's the default, and the plugin ships with Fat Zebra's public sandbox username, token, and shared secret pre-filled, so no credentials are required just to test.
4. Save changes.
5. Run a test checkout with a [Sandbox test card](https://www.artspay.com/docs/developer/testing/test-card-numbers) to confirm everything works end-to-end.
6. When you're ready to go live, follow [Configuration](#configuration) below to add production credentials and switch off Sandbox Mode.

## Configuration

Open **WooCommerce → Settings → Payments → ArtsPay** (or **ArtsPay Credit Card**) to configure the gateway.

### Sandbox & production credentials

- **Sandbox mode** – uses Fat Zebra sandbox URLs and test credentials (defaults are the documented sandbox username/token `TEST` / `TEST` and shared secret).
- **Production** – enter your live username, token, and shared secret from Fat Zebra / ArtsPay, and switch Sandbox Mode off.

### Card form

- **On-site card form** (default): card fields sit in the checkout and post to your server, which sends them to Fat Zebra.
- **Secure hosted form**: Fat Zebra's hosted payments page loads inside the checkout in tokenize-only mode. The customer presses **Pay now** in that form, Fat Zebra returns a card token, and the plugin charges the token. Card numbers never reach your server (PCI SAQ A). Needs the **Gateway Shared Secret**. WooCommerce's **Place order** button stays hidden until a card has been entered. The form's stylesheet (`assets/css/hosted-form.css`, full width and aligned left) is fetched by Fat Zebra from the store, so it's used on HTTPS stores only; other stores get ArtsPay's standard stylesheet from the CDN. The `artspay_hosted_card_css_url` filter changes it.

Subscriptions, deferred payments, Forter, captcha and attempt limits work the same with either form, because the plugin still makes the charge.

### Fraud & security

Forter, 3D Secure and Captcha each have their own settings page, opened from the **Fraud & security** section.

### 3D Secure

Needs the secure hosted form, 3D Secure turned on for the merchant account by ArtsPay, and an OAuth client (**Settings → OAuth Clients** in the Fat Zebra Merchant Dashboard). After the card is entered, fatzebra.js asks the customer's bank to verify the cardholder. Most customers pass without noticing. Some banks show a one-time code or app approval.

- A failed or errored check blocks the payment. The customer can try again or use another card.
- A successful check (including "attempted" and "not enrolled" outcomes) is sent with the purchase, and the order gets a note saying whether liability shifted.
- The server refuses a hosted card payment with no 3DS result while 3D Secure is on.
- Deferred payments and $0 orders skip 3D Secure, because the result is only valid for one immediate charge. Subscription renewals are charged without the customer, so only the first payment is verified.
- Needs HTTPS, including on staging. fatzebra.js uses browser features (such as `crypto.randomUUID`) that only exist on secure pages, so on plain HTTP every check fails.
- In sandbox, `4000 0000 0000 1000` passes straight through and `4000 0000 0000 1091` shows a bank challenge with the code `1234`.

### Deferred payments

Tokenize and hold the order for manual capture later (not used for subscription carts).

### Forter fraud screening

Enable only after ArtsPay/Fat Zebra has activated Forter on the merchant account.

- Optional **device fingerprinting** loads Fat Zebra's `pmnts_id` script on checkout (recommended).
- Optional **Email on Forter decline** notifies the site admin when the gateway returns **Deny**.
- Order meta `_artspay_fraud_result` / `_artspay_fraud_messages` store outcomes when present.
- See [`FRAUD.md`](FRAUD.md) for the full behaviour reference.

### Captcha

Optional reCAPTCHA v3 or Cloudflare Turnstile challenge before payment submit. reCAPTCHA tokens need a score of 0.5 or higher, which the `artspay_recaptcha_min_score` filter can change. On live stores a token must also match the store's hostname. Use the `artspay_captcha_allowed_hostnames` filter if the store runs on more than one domain.

### Payment attempt limits

Always on. See [FRAUD.md](FRAUD.md#card-testing-protection) for the limits and filters.

## Payment flows

| Mode | Behaviour |
|------|-----------|
| **Inline (server)** | Default. Card fields post to your server; the plugin calls Fat Zebra `purchases` API. |
| **Secure hosted form** | Fat Zebra's form (tokenize only) inside the checkout returns a card token. Optional 3D Secure runs on that token with fatzebra.js, then the plugin charges the token with the 3DS result under `extra`. |
| **Google Pay / Apple Pay (wallets)** | When enabled, express buttons appear on the cart and checkout. The wallet token is charged through Fat Zebra's wallet purchase API. |

## Testing (sandbox)

- Use **Sandbox mode** and Fat Zebra test credentials.
- **Fraud screening** (when enabled on the account): use billing email `accept@email.com`, `challenge@email.com`, or `deny@email.com` to simulate Accept / Challenge / Deny ([Fraud screening](https://www.artspay.com/docs/fraud-screening)).
- Exercise checkout, refunds, deferred capture (if enabled), and subscription renewals on a staging site.

## Development

No npm or Composer build step is required to run this plugin — JavaScript and CSS ship as static files under `assets/`. The following is only needed if you're contributing to the plugin itself.

### Build

From the plugin repository root:

```bash
./bin/build.sh
```

This writes `releases/artspay-woocommerce-<version>.zip` (folder inside the archive: `artspay-woocommerce/`, ready for **Plugins → Add New → Upload**) and `releases/artspay-woocommerce-<version>.zip.sha256` for checksum verification. Excludes are listed in [`.distignore`](.distignore) (dev tooling, internal docs, VCS, and `releases/` itself).

To override the version embedded in the filename (e.g. a release candidate), set `ARTSPAY_VERSION` before running the script; otherwise the version is read from `ARTSPAY_PLUGIN_VERSION` in [`artspay-woocommerce.php`](artspay-woocommerce.php). Pushing a `v<version>` tag builds and publishes this ZIP automatically as a GitHub Release — see [`CONTRIBUTING.md`](CONTRIBUTING.md).

### Quality checks

From the plugin root (no WordPress required):

```bash
./bin/check-php-syntax.sh
```

This runs `php -l` on all `*.php` files. CI also runs a security-scoped PHPCS pass (WordPress escaping/nonce/SQL sniffs, see [`phpcs.xml`](phpcs.xml)) and CodeQL on every pull request — see [`.github/workflows/`](.github/workflows/).

### Project layout

```
artspay-woocommerce.php    # Bootstrap, autoloader, constants
includes/
  Plugin.php                 # Hooks, gateway registration
  API/Client.php             # Fat Zebra HTTP client
  Assets/Core.php            # Shared artspay-core script
  Blocks/CardMethod.php      # Block Cart/Checkout: card method and wallet settings
  Express/
    GooglePay.php            # Google Pay on the classic checkout (and block settings)
    ApplePay.php             # Apple Pay on the classic checkout (and block settings)
  Gateways/
    CreditCardGateway.php    # Card, subscriptions, fraud, wallet tokens
  Admin/DeferredPayments.php
  Traits/                    # Encryption, Utils, Captcha, AttemptLimits, HostedCard
assets/js/
  artspay-blocks.js          # Block checkout: card method, Google Pay, Apple Pay
  artspay-fatzebra.js        # Hosted form and 3D Secure, shared by both checkouts
  artspay-hosted-card.js     # Hosted form on the classic checkout
  artspay-core.js
  artspay-checkout-autofill.js
  artspay-googlepay.js
  artspay-applepay.js
  artspay-captcha.js
assets/css/
  blocks.css
  hosted-card.css
  hosted-form.css            # Loaded inside Fat Zebra's hosted form
  express-googlepay.css
```

## References

- [API / Purchases](https://www.artspay.com/docs/api/gateway/purchases)
- [Fraud Screening](https://www.artspay.com/docs/api/gateway/purchases/create-a-purchase-with-fraud-screening-1)
- [3DS2 / Custom form](https://www.artspay.com/docs/developer/3ds2-integration/3ds2-overview) (not implemented in the default inline flow)

## Changelog

Release notes are maintained in [`changelog.txt`](changelog.txt). Upgrading from a pre-3.1.0 install? See [`docs/MIGRATION.md`](docs/MIGRATION.md) for settings that changed.

## Contributing & security

See [`CONTRIBUTING.md`](CONTRIBUTING.md) and [`SECURITY.md`](SECURITY.md).
