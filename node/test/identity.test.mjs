import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { describe, it } from 'node:test';

import * as esm from '../dist/esm/index.js';

const require = createRequire(import.meta.url);
const cjs = require('../dist/cjs/index.js');
const VECTORS = JSON.parse(readFileSync(new URL('../../test-vectors.json', import.meta.url), 'utf8'));

for (const [format, sb] of [['ESM', esm], ['CommonJS', cjs]]) {
  describe(`${format} build`, () => {
    it('matches every shared server vector', () => {
      for (const v of VECTORS.vectors) {
        assert.equal(sb.computeUserHash(v.secret, v.email, v.ts), v.user_hash, v.name);
        assert.equal(sb.normalizeEmail(v.email), v.normalized_email, v.name);
      }
    });

    it('uses the server window', () => {
      assert.equal(sb.TTL_SECONDS, VECTORS.ttl_seconds);
      assert.equal(sb.SKEW_SECONDS, VECTORS.skew_seconds);
    });

    it('identity() defaults ts to now in seconds', () => {
      const before = Math.floor(Date.now() / 1000);
      const { ts, user_hash } = sb.identity('secret', 'a@example.com');
      assert.ok(ts >= before && ts <= Math.floor(Date.now() / 1000));
      assert.match(user_hash, /^[0-9a-f]{64}$/);
    });

    it('visitor() returns the normalized email it signed', () => {
      const v = sb.visitor('secret', { email: '  Jane@Example.COM ', name: ' Jane ', ts: 1792768282 });
      assert.deepEqual(v, {
        email: 'jane@example.com',
        name: 'Jane',
        ts: 1792768282,
        user_hash: sb.computeUserHash('secret', 'jane@example.com', 1792768282),
      });
      assert.equal('name' in sb.visitor('secret', { email: 'a@example.com', ts: 1 }), false);
    });

    it('refuses input the server would refuse', () => {
      const bad = [
        ['', 'a@example.com', 1],
        ['secret', '', 1],
        ['secret', '   ', 1],
        ['secret', 'not-an-email', 1],
        ['secret', `a@${'b'.repeat(400)}`, 1],
        ['secret', 'a@example.com', -1],
        ['secret', 'a@example.com', 1.5],
        ['secret', 'a@example.com', 1792768282000.5],
        ['secret', 'a@example.com', '1792768282'],
        ['secret', null, 1],
      ];
      for (const args of bad) {
        assert.throws(() => sb.computeUserHash(...args), sb.IdentityError, JSON.stringify(args));
      }
    });

    it('guest snippet has no visitor and loads the widget', () => {
      const html = sb.widgetSnippet({ botId: 'bot-123', refreshUrl: '/simplyboost/identity' });
      assert.ok(html.includes('window._CHATBOT_CONFIG_ = {"chat_bot_id":"bot-123"};'));
      assert.ok(!html.includes('visitor'));
      assert.ok(html.includes('"https://get.simplyboost.io/widget.js?v="+Date.now()'));
    });

    it('signed-in config carries the visitor and refresh_url without changing the input', () => {
      const v = sb.visitor('s', { email: 'jane@example.com', ts: 1 });
      const config = sb.widgetConfig({ botId: 'bot', visitor: v, refreshUrl: '/simplyboost/identity' });
      assert.equal(config.visitor.refresh_url, '/simplyboost/identity');
      assert.equal(config.visitor.user_hash, v.user_hash);
      assert.equal('refresh_url' in v, false);
    });

    it('values cannot break out of the script element', () => {
      const html = sb.widgetSnippet({
        botId: 'bot',
        visitor: { email: 'a@example.com', name: '</script><script>alert(1)</script>&\u2028', ts: 1, user_hash: 'x' },
        nonce: 'n"><x',
      });
      assert.ok(!html.includes('</script><script>alert'));
      assert.ok(html.includes('\\u003c/script\\u003e'));
      assert.ok(html.includes('\\u2028'));
      assert.ok(html.includes('nonce="n&quot;&gt;&lt;x"'));
      assert.equal(html.split('</script>').length - 1, 2);
    });

    it('requires a bot id', () => {
      assert.throws(() => sb.widgetSnippet({ botId: ' ' }), sb.IdentityError);
    });

    it('the snippet runs and sets the config', () => {
      const v = sb.visitor('s', { email: 'jane@example.com', name: 'Jane', ts: 1 });
      const html = sb.widgetSnippet({ botId: 'bot', visitor: v, refreshUrl: '/r' });
      const first = html.slice(html.indexOf('>') + 1, html.indexOf('</script>'));
      const window = {};
      new Function('window', first)(window);
      assert.deepEqual(window._CHATBOT_CONFIG_, { chat_bot_id: 'bot', visitor: { ...v, refresh_url: '/r' } });
    });
  });
}
