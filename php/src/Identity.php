<?php

declare(strict_types=1);

namespace SimplyBoost\Identity;

/**
 * Verified user identity for the SimplyBoost chat widget.
 *
 * Your backend signs the logged-in user's email with the chatbot's identity
 * secret; the widget passes {email, name, ts, user_hash} to SimplyBoost,
 * which recomputes the signature and marks the chat as that user. The secret
 * stays on your server and must never reach the browser.
 *
 *     user_hash = hex(HMAC_SHA256(secret, lower(strip(email)) . ":" . ts))
 *
 * @see https://simplyboost.io/docs/verified-visitors
 */
final class Identity
{
    /** How long SimplyBoost accepts a signature after its ts, in seconds. */
    public const TTL_SECONDS = 600;

    /** How far ts may be ahead of SimplyBoost's clock, in seconds. */
    public const SKEW_SECONDS = 60;

    private const MAX_EMAIL_LENGTH = 320;

    /**
     * The characters Python's str.strip() removes, which is what the
     * SimplyBoost server trims. PHP's trim() removes fewer (no NBSP, no
     * Unicode spaces), so it is not used.
     */
    private const SERVER_WHITESPACE = '\x{9}-\x{d}\x{1c}-\x{20}\x{85}\x{a0}\x{1680}\x{2000}-\x{200a}\x{2028}\x{2029}\x{202f}\x{205f}\x{3000}';

    private function __construct()
    {
    }

    /**
     * The email exactly as SimplyBoost signs and stores it: trimmed and
     * lowercased.
     *
     * @throws IdentityException
     */
    public static function normalizeEmail(string $email): string
    {
        $normalized = mb_strtolower(self::strip($email), 'UTF-8');
        if ($normalized === '') {
            throw new IdentityException('email is required');
        }
        if (strpos($normalized, '@') === false || mb_strlen($normalized, 'UTF-8') > self::MAX_EMAIL_LENGTH) {
            throw new IdentityException('email is not an email address');
        }

        return $normalized;
    }

    /**
     * The hex HMAC-SHA256 of "<normalized email>:<ts>" with the secret.
     *
     * @throws IdentityException
     */
    public static function computeUserHash(string $secret, string $email, int $ts): string
    {
        if ($secret === '') {
            throw new IdentityException('secret is required');
        }

        return hash_hmac('sha256', self::normalizeEmail($email) . ':' . self::timestamp($ts), $secret);
    }

    /**
     * ['ts' => int, 'user_hash' => string] for a logged-in user (ts defaults to now).
     *
     * @return array{ts: int, user_hash: string}
     *
     * @throws IdentityException
     */
    public static function compute(string $secret, string $email, ?int $ts = null): array
    {
        $ts = self::timestamp($ts);

        return ['ts' => $ts, 'user_hash' => self::computeUserHash($secret, $email, $ts)];
    }

    /**
     * The four values the widget needs for a logged-in user, with the email
     * normalized as signed. Return it from your refresh endpoint as JSON, or
     * pass it to Widget::snippet().
     *
     * @return array{email: string, name?: string, ts: int, user_hash: string}
     *
     * @throws IdentityException
     */
    public static function visitor(string $secret, string $email, ?string $name = null, ?int $ts = null): array
    {
        $signature = self::compute($secret, $email, $ts);
        $visitor = ['email' => self::normalizeEmail($email)];
        $name = $name === null ? '' : self::strip($name);
        if ($name !== '') {
            $visitor['name'] = $name;
        }

        return $visitor + $signature;
    }

    private static function timestamp(?int $ts): int
    {
        if ($ts === null) {
            return time();
        }
        if ($ts < 0) {
            throw new IdentityException('ts must not be negative');
        }

        return $ts;
    }

    private static function strip(string $value): string
    {
        $class = '[' . self::SERVER_WHITESPACE . ']+';
        $stripped = preg_replace('/\A' . $class . '|' . $class . '\z/u', '', $value);
        if ($stripped === null) {
            throw new IdentityException('value is not valid UTF-8');
        }

        return $stripped;
    }
}
