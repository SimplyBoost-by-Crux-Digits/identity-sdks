/**
 * Verified user identity for the SimplyBoost chat widget.
 *
 * Your backend signs the logged-in user's email with the chatbot's identity
 * secret; the widget passes `{ email, name, ts, user_hash }` to SimplyBoost,
 * which recomputes the signature and marks the chat as that user. The secret
 * stays on your server and must never reach the browser.
 *
 *     user_hash = hex(HMAC_SHA256(secret, lower(strip(email)) + ":" + ts))
 *
 * Guide: https://simplyboost.io/docs/verified-visitors
 */
import { createHmac } from 'node:crypto';

/** How long SimplyBoost accepts a signature after its `ts`, in seconds. */
export const TTL_SECONDS = 600;
/** How far `ts` may be ahead of SimplyBoost's clock, in seconds. */
export const SKEW_SECONDS = 60;
/** The widget script every snippet loads. */
export const DEFAULT_WIDGET_URL = 'https://get.simplyboost.io/widget.js';

const MAX_EMAIL_LENGTH = 320;

/**
 * The characters Python's `str.strip()` removes, which is what the
 * SimplyBoost server trims. JavaScript's `trim()` differs (it also removes
 * U+FEFF and keeps U+001C–U+001F and U+0085), so it is not used.
 */
const SERVER_WHITESPACE =
  '\\t\\n\\v\\f\\r\\x1c-\\x20\\x85\\xa0\\u1680\\u2000-\\u200a\\u2028\\u2029\\u202f\\u205f\\u3000';
const TRIM = new RegExp(`^[${SERVER_WHITESPACE}]+|[${SERVER_WHITESPACE}]+$`, 'g');

/** Thrown for input SimplyBoost would never accept. */
export class IdentityError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'IdentityError';
  }
}

export interface Signature {
  ts: number;
  user_hash: string;
}

export interface Visitor extends Signature {
  email: string;
  name?: string;
}

export interface VisitorInput {
  email: string;
  name?: string | null;
  /** Unix seconds. Defaults to now. */
  ts?: number;
}

export interface WidgetConfigOptions {
  botId: string;
  /** The signed-in user from {@link visitor}; omit for guests. */
  visitor?: Visitor | null;
  /** Same-origin path that returns a fresh {@link visitor} as JSON. */
  refreshUrl?: string | null;
}

export interface WidgetSnippetOptions extends WidgetConfigOptions {
  /** Content-Security-Policy nonce for the two inline scripts. */
  nonce?: string | null;
  widgetUrl?: string;
}

export interface WidgetConfig {
  chat_bot_id: string;
  visitor?: Visitor & { refresh_url?: string };
}

/** The email exactly as SimplyBoost signs and stores it: trimmed and lowercased. */
export function normalizeEmail(email: string): string {
  if (typeof email !== 'string') throw new IdentityError('email must be a string');
  const normalized = email.replace(TRIM, '').toLowerCase();
  if (!normalized) throw new IdentityError('email is required');
  if (!normalized.includes('@') || normalized.length > MAX_EMAIL_LENGTH) {
    throw new IdentityError('email is not an email address');
  }
  return normalized;
}

function timestamp(ts?: number): number {
  if (ts === undefined || ts === null) return Math.floor(Date.now() / 1000);
  if (typeof ts !== 'number' || !Number.isSafeInteger(ts)) {
    throw new IdentityError('ts must be a whole number of seconds');
  }
  if (ts < 0) throw new IdentityError('ts must not be negative');
  return ts;
}

/** The hex HMAC-SHA256 of `"<normalized email>:<ts>"` with `secret`. */
export function computeUserHash(secret: string, email: string, ts: number): string {
  if (typeof secret !== 'string' || !secret) throw new IdentityError('secret is required');
  const payload = `${normalizeEmail(email)}:${timestamp(ts)}`;
  return createHmac('sha256', Buffer.from(secret, 'utf8')).update(payload, 'utf8').digest('hex');
}

/** `{ ts, user_hash }` for a logged-in user (`ts` defaults to now). */
export function identity(secret: string, email: string, ts?: number): Signature {
  const stamp = timestamp(ts);
  return { ts: stamp, user_hash: computeUserHash(secret, email, stamp) };
}

/**
 * The four values the widget needs for a logged-in user, with the email
 * normalized as signed. Return it from your refresh endpoint as JSON, or pass
 * it to {@link widgetSnippet}.
 */
export function visitor(secret: string, input: VisitorInput): Visitor {
  const signature = identity(secret, input.email, input.ts);
  const result: Visitor = { email: normalizeEmail(input.email), ...signature };
  const name = input.name == null ? '' : String(input.name).replace(TRIM, '');
  if (name) result.name = name;
  return result;
}

/** The `window._CHATBOT_CONFIG_` object. */
export function widgetConfig(options: WidgetConfigOptions): WidgetConfig {
  const botId = typeof options.botId === 'string' ? options.botId.trim() : '';
  if (!botId) throw new IdentityError('botId is required');
  const config: WidgetConfig = { chat_bot_id: botId };
  if (options.visitor) {
    config.visitor = { ...options.visitor };
    if (options.refreshUrl) config.visitor.refresh_url = options.refreshUrl;
  }
  return config;
}

/** JSON that is safe inside an inline `<script>` element. */
function scriptJson(value: unknown): string {
  return JSON.stringify(value)
    .replace(/</g, '\\u003c')
    .replace(/>/g, '\\u003e')
    .replace(/&/g, '\\u0026')
    .replace(/\u2028/g, '\\u2028')
    .replace(/\u2029/g, '\\u2029');
}

function htmlAttribute(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/**
 * The HTML that loads the widget, for the end of `<body>`. Every value is
 * escaped for the `<script>` element, so the result can be printed unescaped
 * (`dangerouslySetInnerHTML`, `<%- %>`, `{{{ }}}`).
 */
export function widgetSnippet(options: WidgetSnippetOptions): string {
  const config = scriptJson(widgetConfig(options));
  const widgetUrl = options.widgetUrl || DEFAULT_WIDGET_URL;
  const src = scriptJson(`${widgetUrl}${widgetUrl.includes('?') ? '&' : '?'}v=`);
  const attr = options.nonce ? ` nonce="${htmlAttribute(options.nonce)}"` : '';
  return (
    `<script${attr}>window._CHATBOT_CONFIG_ = ${config};</script>\n` +
    `<script${attr}>(function(d,s,id){var js=d.createElement(s);js.id=id;` +
    `js.src=${src}+Date.now();d.body.appendChild(js);})` +
    `(document,'script','simplyboost-classic-script');</script>`
  );
}
