// SimplyBoost chat widget with verified identity in an Express app.
//
//   npm install express @simplyboost/identity
//   SIMPLYBOOST_BOT_ID=... SIMPLYBOOST_IDENTITY_SECRET=... node server.mjs
//
// The secret is read from the environment on the server and never sent to
// the browser. Replace `req.user` with however your app stores the login.
import express from 'express';
import { visitor, widgetSnippet } from '@simplyboost/identity';

const BOT_ID = process.env.SIMPLYBOOST_BOT_ID;
const SECRET = process.env.SIMPLYBOOST_IDENTITY_SECRET;
if (!BOT_ID || !SECRET) throw new Error('Set SIMPLYBOOST_BOT_ID and SIMPLYBOOST_IDENTITY_SECRET');

const app = express();
// app.use(yourSessionMiddleware); app.use(passport.session()); ...

function signedVisitor(user) {
  return user?.email ? visitor(SECRET, { email: user.email, name: user.name }) : null;
}

// Every page: the widget, signed for the logged-in user, anonymous for guests.
app.use((req, res, next) => {
  res.locals.simplyboostWidget = widgetSnippet({
    botId: BOT_ID,
    visitor: signedVisitor(req.user),
    refreshUrl: '/simplyboost/identity',
  });
  next();
});

// Fresh signature for pages left open longer than the 10-minute window.
app.get('/simplyboost/identity', (req, res) => {
  const signed = signedVisitor(req.user);
  res.set('Cache-Control', 'no-store');
  if (!signed) return res.status(401).json({});
  res.json(signed);
});

app.get('/', (req, res) => {
  // With a template engine, print it unescaped just before </body>:
  //   EJS: <%- simplyboostWidget %>   Handlebars: {{{simplyboostWidget}}}
  res.send(`<!doctype html><html><body><h1>Portal</h1>${res.locals.simplyboostWidget}</body></html>`);
});

app.listen(3000, () => console.log('http://localhost:3000'));
