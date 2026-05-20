# Content Security Policy (CSP) — Payment Gateway Compatibility

If guests see "Paystack could not load" or "Flutterwave could not load" on
your booking form, and the browser DevTools Network tab shows the SDK
requests as `(blocked:csp)`, the cause is a Content Security Policy header
being served by your site that does not whitelist the payment gateway
domains.

This plugin cannot fix that — the CSP header is set outside the plugin
(by your host, a security plugin, Cloudflare, or a custom server config),
and a plugin cannot loosen a CSP that another component sets. You have to
update the CSP at its source.

This document lists the exact domains the bundled gateways need.

---

## How to find what is setting the CSP

1. Open the booking page in your browser.
2. Open DevTools → **Network** tab → reload the page.
3. Click the document request (the HTML one, usually the first row).
4. Scroll the right panel to **Response Headers**.
5. Look for `Content-Security-Policy` or `Content-Security-Policy-Report-Only`.

Common sources, in order of likelihood on a WordPress site:

| Source | Where to fix |
|---|---|
| Cloudflare Transform Rule / Modify Response Header | Cloudflare dashboard → your zone → Rules |
| Really Simple SSL Pro (CSP module) | WP admin → Settings → SSL → Hardening |
| HTTP Headers plugin | WP admin → Settings → HTTP Headers |
| Headers Security Advanced & HSTS WP | WP admin → that plugin's settings |
| Wordfence / iThemes / Solid Security headers feature | The plugin's settings panel |
| `.htaccess` (Apache) | `Header set Content-Security-Policy "..."` |
| nginx config | `add_header Content-Security-Policy "..."` |
| Custom `header()` call in `functions.php` or another plugin | `grep -r "Content-Security-Policy" wp-content/` |

---

## Required CSP directives

Add the following sources to each directive (merge with what you already
have — do not replace your full policy with these alone, or you will
break other parts of your site).

### `script-src` — to load the SDK

```
https://js.paystack.co
https://checkout.flutterwave.com
```

### `frame-src` (and `child-src` if your policy uses it) — for the modal iframes

```
https://checkout.paystack.com
https://standard.paystack.co
https://checkout.flutterwave.com
https://ravemodal-dev.herokuapp.com
```

### `connect-src` — for XHR calls the modals make to the gateway APIs

```
https://api.paystack.co
https://checkout.paystack.com
https://api.flutterwave.com
https://checkout.flutterwave.com
```

### `img-src` — for card-network and gateway logos

```
https://*.paystack.co
https://*.paystack.com
https://*.flutterwave.com
data:
```

### `form-action` — Flutterwave occasionally posts a form

```
https://checkout.flutterwave.com
```

---

## Complete example

If your current CSP is, for example:

```
default-src 'self'; script-src 'self' 'unsafe-inline'; frame-src 'self'; connect-src 'self'; img-src 'self' data:
```

Update it to (still one line, just shown wrapped here for readability):

```
default-src 'self';
script-src 'self' 'unsafe-inline' https://js.paystack.co https://checkout.flutterwave.com;
frame-src 'self' https://checkout.paystack.com https://standard.paystack.co https://checkout.flutterwave.com https://ravemodal-dev.herokuapp.com;
connect-src 'self' https://api.paystack.co https://checkout.paystack.com https://api.flutterwave.com https://checkout.flutterwave.com;
img-src 'self' data: https://*.paystack.co https://*.paystack.com https://*.flutterwave.com;
form-action 'self' https://checkout.flutterwave.com;
```

---

## Verification

After updating the CSP at its source:

1. Hard-reload the booking page (Ctrl+Shift+R / Cmd+Shift+R) to bypass cache.
2. Open DevTools → Network tab → search for `paystack` and `flutterwave`.
3. Both requests should now show **status 200** (not `blocked:csp`).
4. Click "Pay" — the modal should open.

If you still see `blocked:csp` after updating, check whether you have
**both** an HTTP header CSP and a `<meta http-equiv="Content-Security-Policy">`
tag in the page; both apply and the resulting policy is the *intersection*
(most restrictive) of the two.

---

## Why this can't be fixed in the plugin

CSP is enforced by the browser before any JavaScript runs. The plugin's
defensive script-injection workaround in `public/js/ghm-public.js`
(`ensurePaymentSDKs()`) handles the case where another plugin *strips*
the SDK script tags from the HTML, but it cannot defeat a CSP — the
browser blocks dynamically-injected scripts the same way it blocks
statically-included ones. Multiple `Content-Security-Policy` headers
also do not relax each other; they intersect to produce a *stricter*
policy. So the only fix is to whitelist the gateway domains in the
existing CSP source.
