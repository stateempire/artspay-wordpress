# Apple Pay on the Web (WooCommerce)

This note is for merchants configuring **Apple Pay on the Web** with the ArtsPay WooCommerce plugin.

## HTTPS and browsers

- **Live Apple Pay requires HTTPS** on the checkout origin.
- **Apple Pay on the Web is effectively Safari-first** for real-world testing and customer usage patterns.
- **Sandbox still expects a real HTTPS domain for verification in most setups** (Apple domain association checks are still done against a public URL on your hostname).

Google Pay has similar “real browser + HTTPS origin” expectations in live mode; see your gateway/wallet documentation for details.

## What gets verified

Apple requires a domain association file at a fixed path on the **same host** customers use at checkout:

`/.well-known/apple-developer-merchantid-domain-association`

The WooCommerce plugin treats the **WordPress “Site Address (URL)” host** as the canonical domain to verify and register.

## Sandbox vs production file

Fat Zebra publishes two different verification files. You must upload the file that matches the **active gateway mode** (sandbox vs production):

- Sandbox: `https://paynow.pmnts-sandbox.io/apple_pay/domain_verification/sandbox.txt`
- Production: `https://paynow.pmnts.io/apple_pay/domain_verification/production.txt`

Upload it so it is served **byte-for-byte** at the required `/.well-known/...` URL (no HTML error pages, no redirects to a login screen, no “pretty” 404 pages).

## Common hosting pitfalls (why “it 404s” even after upload)

Many stacks intercept `/.well-known` **before** WordPress runs:

- **Static webroot rules** that don’t map `/.well-known` into WordPress
- **CDN / edge caching** serving an old 404 object
- **Security plugins / WAF rules** blocking unknown paths
- **Multisite / subdirectory installs** where the public host for checkout differs from the WP admin canonical host

If verification fails, use `curl -i` against the exact verification URL and confirm you receive **HTTP 200** with the **correct file bytes** (not HTML).

## After the file is reachable

Use the plugin’s **Register domain with ArtsPay** action (this calls **Fat Zebra**’s Apple Pay domain registration API for your merchant).

If checkout runs on a **different hostname** than your WordPress Site Address (for example `www` vs apex, staging domains, tunnels), you must host the file and register **that checkout hostname**.

## References

- Apple Pay Web domain registration (`https://www.artspay.com/docs/api/digital-wallet-registration/merchant-registration`)
- Get Apple Pay Session (`https://www.artspay.com/docs/developer/wallets/apple-pay`)
