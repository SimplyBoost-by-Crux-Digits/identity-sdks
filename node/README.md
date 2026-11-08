# @simplyboost/identity

**HMAC identity verification for the [SimplyBoost](https://simplyboost.io) AI chat widget in
Node.js, Express and Next.js.** Your server signs the logged-in user's email; the widget passes
the signature to SimplyBoost; the assistant knows who it is talking to and your team sees the
customer's name in the inbox. For customer portals, dealer portals, B2B webshops and any site
with its own login.

- Zero dependencies (uses `node:crypto`), TypeScript types, ESM and CommonJS
- Signs exactly what the SimplyBoost server verifies, checked against
  [shared test vectors](https://github.com/SimplyBoost-by-Crux-Digits/identity-sdks/blob/main/test-vectors.json)
- Node.js 18+ (the Node.js runtime, not Edge)

```bash
npm install @simplyboost/identity
```

## Quick start (Express)

```js
import { visitor, widgetSnippet } from '@simplyboost/identity';

const BOT_ID = process.env.SIMPLYBOOST_BOT_ID;
const SECRET = process.env.SIMPLYBOOST_IDENTITY_SECRET; // server-side only

const signed = (user) => (user ? visitor(SECRET, { email: user.email, name: user.name }) : null);

// Every page, just before </body> (print unescaped: <%- simplyboostWidget %>)
app.use((req, res, next) => {
  res.locals.simplyboostWidget = widgetSnippet({
    botId: BOT_ID,
    visitor: signed(req.user),
    refreshUrl: '/simplyboost/identity',
  });
  next();
});

// A fresh signature for pages left open longer than ten minutes
app.get('/simplyboost/identity', (req, res) => {
  const v = signed(req.user);
  res.set('Cache-Control', 'no-store');
  v ? res.json(v) : res.status(401).json({});
});
```

Full examples: [Express](https://github.com/SimplyBoost-by-Crux-Digits/identity-sdks/tree/main/node/examples/express),
[Next.js App Router](https://github.com/SimplyBoost-by-Crux-Digits/identity-sdks/tree/main/node/examples/nextjs)
(layout + route handler).

CommonJS works too: `const { visitor, widgetSnippet } = require('@simplyboost/identity');`

## API

| Function | Returns |
|---|---|
| `visitor(secret, { email, name?, ts? })` | `{ email, name?, ts, user_hash }`, email normalized as signed: send it to the widget or return it from your refresh endpoint |
| `identity(secret, email, ts?)` | `{ ts, user_hash }` |
| `computeUserHash(secret, email, ts)` | the hex signature |
| `normalizeEmail(email)` | the email as SimplyBoost signs it |
| `widgetSnippet({ botId, visitor?, refreshUrl?, nonce?, widgetUrl? })` | the widget HTML, every value escaped for `<script>` |
| `widgetConfig({ botId, visitor?, refreshUrl? })` | the `window._CHATBOT_CONFIG_` object |

`TTL_SECONDS` (600) and `SKEW_SECONDS` (60) are the window SimplyBoost accepts. Invalid input
(no secret, not an email address, a `ts` that is not whole seconds) throws `IdentityError`.

In a single-page app, call `window.SimplyBoost.identify(visitor)` after login with values from
your server, and `window.SimplyBoost.reset()` on logout.

## How it works

```
user_hash = hex( HMAC_SHA256( secret, lowercase(trim(email)) + ":" + ts ) )
```

`ts` is Unix **seconds**; a signature is accepted for 10 minutes. The package trims exactly what
the server trims (Python's `str.strip()`), which differs slightly from JavaScript's `trim()`.

## Security

- **Never send the identity secret to the browser**: no `NEXT_PUBLIC_` variable, no client
  component, no bundle. Read it from the environment on the server.
- Sign per request; never cache a page or response that contains a signature for one user.
- The refresh endpoint must be on the same origin as your pages and answer `401` for guests.

Guide: [simplyboost.io/docs/verified-visitors](https://simplyboost.io/docs/verified-visitors)

## Nederlands

Ondertekent het e-mailadres van de ingelogde gebruiker (HMAC-SHA256) zodat de SimplyBoost-chatwidget
weet met wie hij praat. Installeer met `npm install @simplyboost/identity`, lees
`SIMPLYBOOST_IDENTITY_SECRET` alleen op de server (nooit in de browser of een `NEXT_PUBLIC_`-variabele)
en gebruik `widgetSnippet()` vlak voor `</body>`. Handleiding:
[simplyboost.io/docs/verified-visitors](https://simplyboost.io/docs/verified-visitors).

MIT licence, © Crux Digits B.V. (SimplyBoost).
