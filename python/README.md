# simplyboost-identity

**HMAC identity verification for the [SimplyBoost](https://simplyboost.io) AI chat widget in
Django, Flask and any Python backend.** Your server signs the logged-in user's email; the widget
passes the signature to SimplyBoost; the assistant knows who it is talking to and your team sees
the customer's name in the inbox. For customer portals, dealer portals, B2B webshops and any
site with its own login.

- Zero dependencies, typed (`py.typed`), Python 3.10+
- Signs exactly what the SimplyBoost server verifies, checked against
  [shared test vectors](https://github.com/SimplyBoost-by-Crux-Digits/identity-sdks/blob/main/test-vectors.json)

```bash
pip install simplyboost-identity
```

## Quick start

```python
import os
from simplyboost_identity import visitor, widget_snippet

BOT_ID = os.environ["SIMPLYBOOST_BOT_ID"]
SECRET = os.environ["SIMPLYBOOST_IDENTITY_SECRET"]  # server-side only

signed = visitor(SECRET, user.email, name=user.get_full_name()) if user.is_authenticated else None

# Just before </body>. The result escapes every value for <script>: mark it safe.
html = widget_snippet(BOT_ID, signed, refresh_url="/simplyboost/identity")
```

Your refresh endpoint returns `visitor(...)` as JSON for the logged-in user and `401` for a guest,
with `Cache-Control: no-store`. The widget calls it on its own when a page has been open longer
than eight minutes.

Full examples: [Django](https://github.com/SimplyBoost-by-Crux-Digits/identity-sdks/tree/main/python/examples/django)
(context processor + view), [Flask](https://github.com/SimplyBoost-by-Crux-Digits/identity-sdks/tree/main/python/examples/flask).

## API

| Function | Returns |
|---|---|
| `visitor(secret, email, name=None, ts=None)` | `{"email", "name"?, "ts", "user_hash"}`, email normalized as signed |
| `identity(secret, email, ts=None)` | `{"ts", "user_hash"}` |
| `compute_user_hash(secret, email, ts)` | the hex signature |
| `normalize_email(email)` | the email as SimplyBoost signs it |
| `widget_snippet(bot_id, visitor=None, refresh_url=None, nonce=None)` | the widget HTML |
| `widget_config(bot_id, visitor=None, refresh_url=None)` | the `window._CHATBOT_CONFIG_` dict |

`TTL_SECONDS` (600) and `SKEW_SECONDS` (60) are the window SimplyBoost accepts. Invalid input
raises `IdentityError` (a `ValueError`).

## How it works

```
user_hash = hex( HMAC_SHA256( secret, email.strip().lower() + ":" + str(ts) ) )
```

`ts` is Unix **seconds**; a signature is accepted for 10 minutes, so sign on every request.

## Security

- **Never send the identity secret to the browser.** Keep it in an environment variable or a
  secret manager on the server; only `email`, `name`, `ts` and `user_hash` go into the page.
- Sign per request; never cache a page that contains one user's signature.
- The refresh endpoint must be on the same origin as your pages and answer `401` for guests.

Guide: [simplyboost.io/docs/verified-visitors](https://simplyboost.io/docs/verified-visitors)

## Nederlands

Ondertekent het e-mailadres van de ingelogde gebruiker (HMAC-SHA256) zodat de SimplyBoost-chatwidget
weet met wie hij praat. Installeer met `pip install simplyboost-identity`, lees
`SIMPLYBOOST_IDENTITY_SECRET` alleen op de server (nooit in een template of in JavaScript) en zet
`widget_snippet()` vlak voor `</body>`. Handleiding:
[simplyboost.io/docs/verified-visitors](https://simplyboost.io/docs/verified-visitors).

MIT licence, © Crux Digits B.V. (SimplyBoost).
