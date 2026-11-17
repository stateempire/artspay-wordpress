# Fraud screening and authentication

This document describes **Forter** (fraud screening via Fat Zebra) as implemented in the ArtsPay WooCommerce gateway, and records the status of **3-D Secure (3DS)**.

---

## Card testing protection

Card testing is when a script tries stolen card numbers against a checkout to find the ones that work. WooCommerce reuses a pending or failed order while the cart stays the same, so without a limit one order can be used to try any number of cards.

### Attempt limits

These are always on. Attempts are counted just before the call to Fat Zebra, and a block stops the payment before that call.

| Limit | Attempts | Window |
|-------|----------|--------|
| Per order | 5 | 1 hour |
| Per session | 5 | 1 hour |
| Per IP address | 10 | 1 hour |

- When an order reaches its limit it is marked **Failed**, with an order note. Once the window ends the same order can be paid.
- When a session or IP limit stops a payment, the order gets a note and keeps its status.
- A successful payment resets the order and session counts. It doesn't count towards the IP limit, so a box office selling from one IP isn't limited by its sales, but earlier declines from that IP still count.
- A gateway outage or rejected API credentials don't use up an attempt.
- Every declined payment adds an order note with the Fat Zebra transaction ID.

Change the numbers with the `artspay_attempt_limits` filter:

```php
add_filter( 'artspay_attempt_limits', function ( $limits ) {
	$limits['ip'] = 20;
	return $limits;
} );
```

### Visitor IP

The IP limit uses the `CF-Connecting-IP` header if there is one, then the last `X-Forwarded-For` entry, then `REMOTE_ADDR`. On a store behind several proxies without Cloudflare, the last `X-Forwarded-For` entry can be a proxy, so customers would share one count. Use the `artspay_rate_limit_ip` filter to return the right address.

The per order limit doesn't depend on the IP address, so it applies even if an attacker changes IP.

### Store API

Since 3.3.0 the block Cart and Checkout pay through WooCommerce's Store API checkout endpoint (`/wp-json/wc/store/v1/checkout`), so it's no longer blocked. The attempt limits and captcha apply to it the same as to the classic checkout.

### What these don't do

Limits slow an attack down but don't stop one that keeps changing order, session and IP. For that, also use:

- The captcha (**WooCommerce → Settings → Payments → ArtsPay**). It applies to card payments. Google Pay and Apple Pay skip it: their express buttons sit outside the card form, and each wallet token is signed by Google or Apple for a real account, so they can't be used to test card numbers.
- Rate limit rules at your firewall or CDN for `?wc-ajax=checkout`, `/checkout/order-pay/` and `/wp-json/wc/store/v1/checkout`
- Forter, described below

---

## Forter

Forter screening is **optional**. When **WooCommerce → Settings → Payments → ArtsPay → Enable Forter fraud screening** is on, each eligible charge sends a **`fraud`** object on Fat Zebra’s **`purchases`** API (customer, line items, shipping; optional **`device_id`** from Fat Zebra’s fingerprint script when **Enable device fingerprinting** is enabled).

**Enable only after** ArtsPay or Fat Zebra has activated Forter on your **merchant account** (sandbox and production are configured separately).

### Outcomes the gateway can return

The plugin reads **`fraud_result`** and **`fraud_messages`** from the API response when present. Documented result labels include **Accept**, **Challenge**, **Deny**, and **Error** (see gateway settings description in the plugin).

| Result    | Typical API shape | Plugin behaviour (summary) |
|-----------|-------------------|----------------------------|
| **Accept** | Purchase succeeds (`response.successful` true) | Order completes; fraud outcome stored in order notes and meta. |
| **Challenge** | Purchase often still succeeds | Order **still completes**; outcome stored. The plugin does **not** auto-hold or cancel on Challenge alone — treat as **advisory** unless you add your own rules or Fat Zebra configures stricter handling. |
| **Deny** | Usually tied to a **declined** purchase (`response.successful` false) | Checkout **fails** like any other decline; optional **Email on Forter decline** notifies the site admin when `fraud_result` is Deny on that decline path. |
| **Error** | Often with screening/availability issues | If the transaction is **declined** and `fraud_result` is Error, the shopper sees a dedicated message that fraud screening could not be completed (not only a generic decline). |

The Fat Zebra **merchant dashboard** may show extra fields (for example **Recommended action**) that reflect Forter’s guidance; those are not necessarily mirrored as separate fields in the WooCommerce order. Align operational process (e.g. review before fulfilment on Challenge) with Fat Zebra’s documentation and your MID settings.

### Order audit trail

When the API returns fraud fields on success or decline handling paths, the plugin can record:

- **`_artspay_fraud_result`** — string `fraud_result`
- **`_artspay_fraud_messages`** — joined `fraud_messages`

Order notes include **Forter fraud check: …** and optional **Forter messages: …** lines.

### Sandbox testing

Fat Zebra documents **test billing emails** (for example `accept@email.com`, `challenge@email.com`, `deny@email.com`) to exercise fraud outcomes in **sandbox**. Behaviour in sandbox is for testing only; **production** depends on live MID configuration and Forter mode (see gotchas below).

### Known gotchas

1. **Dry run vs decisions (account-side)**  
   The merchant account can be in **dry run** (or similar) mode where Forter does not apply full **decisioning**. In that state you may see misleading portal results (for example **Error** on risk while the card still **approves**). Production should use **decisions** (or the mode Fat Zebra specifies for live screening). This is **configuration on Fat Zebra’s side**, not a WooCommerce code change.

2. **Challenge + “Stop” but payment approved**  
   **Challenge** with a portal recommendation such as **Stop** can still coincide with **response code 00 / approved** charge. That is consistent with **advisory** risk: you may need to **review or hold fulfilment** even though checkout completed. Only **Deny** (when the API declines the purchase) blocks the customer flow in the usual way.

3. **Sandbox vs production**  
   Sandbox can look correct while production misbehaves if Forter is not provisioned on the **live** MID, or the account was in dry run. Always confirm **production** activation and mode with ArtsPay / Fat Zebra.

4. **Device fingerprinting**  
   Stronger Forter results usually need **`device_id`** (fingerprint script). The plugin can enqueue Fat Zebra’s script on checkout when enabled; site-wide loading may be preferable per Fat Zebra’s docs.

5. **Plugin does not interpret Challenge as decline**  
   There is **no** built-in rule to fail or void an order solely because `fraud_result` is Challenge. If your policy requires automatic blocking on Challenge, that must come from **processor rules** or **custom automation** outside this document.

---

## 3DS (3-D Secure)

Available from 3.2.0 with the **secure hosted card form**. Set it up under **Fraud & security → 3D Secure**. It needs 3D Secure turned on for the merchant account and an OAuth client from the Fat Zebra Merchant Dashboard.

How it runs:

1. The customer enters their card in Fat Zebra's hosted form, which returns a card token.
2. The browser asks the store for a short-lived OAuth token and an HMAC of the card token (`wc-ajax=artspay_3ds_prepare`), then calls fatzebra.js `verifyCard` with `card_on_file`.
3. On `fz.sca.success` the result is posted with the checkout. The plugin keeps the documented fields (`sli`, `cavv`, `xid`, `ver`, `par`, `directory_server_txn_id`, `threeds_version`) and sends them under `extra` on the purchase.
4. On `fz.sca.error` the payment is blocked and the customer can retry or use another card.

Policy, matching ArtsPay's hosted checkout:

- Any result fatzebra.js reports as success is accepted, including **attempted** and **not enrolled** (`ver: N`). The order note says whether liability shifted.
- The server requires a 3DS result for hosted card payments while 3D Secure is on, so a request that skips the browser step is refused.
- Deferred payments and $0 orders skip 3D Secure. Subscription renewals are merchant-initiated and are not verified.
- The result is stored on the order as `_artspay_3ds_result`.

3D Secure and Forter are independent: both can run on the same charge.
