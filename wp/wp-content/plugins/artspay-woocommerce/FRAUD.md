# Fraud screening and authentication

This document describes **Forter** (fraud screening via Fat Zebra) as implemented in the ArtsPay WooCommerce gateway, and records the status of **3-D Secure (3DS)**.

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

**3-D Secure is not implemented in this plugin.** Card flows use the standard Fat Zebra purchase path (and optional Direct Post / wallet flows where documented elsewhere); liability shift and step-up authentication via 3DS are **not** handled by ArtsPay WooCommerce today.

If you need 3DS, discuss **availability and integration** with ArtsPay / Fat Zebra (product and MID-level), and any future WooCommerce support would be a **separate feature** from the current Forter payload behaviour above.
