<?php

declare(strict_types=1);

namespace SimplyBoost\Identity\Laravel;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\Vite;
use Psr\Log\LoggerInterface;
use SimplyBoost\Identity\Identity;
use SimplyBoost\Identity\IdentityException;
use SimplyBoost\Identity\Widget;

/**
 * Builds the widget snippet and the signed visitor for the current request.
 * Used by the Blade directive, the Blade component and the refresh endpoint.
 */
class WidgetRenderer
{
    public const ROUTE_NAME = 'simplyboost.identity';

    /** @var (Closure(Authenticatable): (array{email: string, name?: string|null}|null))|null */
    private static $userResolver;

    /** @var AuthFactory */
    private $auth;

    /** @var Config */
    private $config;

    /** @var UrlGenerator */
    private $url;

    /** @var LoggerInterface */
    private $log;

    public function __construct(AuthFactory $auth, Config $config, UrlGenerator $url, LoggerInterface $log)
    {
        $this->auth = $auth;
        $this->config = $config;
        $this->url = $url;
        $this->log = $log;
    }

    /**
     * Decide which email and name are signed for a user, e.g. a portal whose
     * login model keeps the address elsewhere. Return null for no identity.
     *
     * @param (Closure(Authenticatable): (array{email: string, name?: string|null}|null))|null $resolver
     */
    public static function resolveUserUsing(?Closure $resolver): void
    {
        self::$userResolver = $resolver;
    }

    /**
     * The widget HTML for the end of <body>, or an empty string when the
     * package is not configured (a page never breaks over the widget).
     */
    public function snippet(?string $nonce = null): string
    {
        $botId = trim((string) $this->config->get('simplyboost.bot_id'));
        if ($botId === '') {
            $this->log->warning('SimplyBoost widget not rendered: SIMPLYBOOST_BOT_ID is not set.');

            return '';
        }

        $visitor = $this->visitor();
        $refreshUrl = $visitor !== null && $this->config->get('simplyboost.refresh_route.enabled', true)
            ? $this->url->route(self::ROUTE_NAME, [], false)
            : null;

        return Widget::snippet($botId, $visitor, [
            'refresh_url' => $refreshUrl,
            'nonce' => $nonce ?? $this->viteNonce(),
            'widget_url' => $this->config->get('simplyboost.widget_url'),
        ]);
    }

    /**
     * The signed visitor for the logged-in user, or null for a guest, a user
     * without an email, or a missing secret.
     *
     * @return array{email: string, name?: string, ts: int, user_hash: string}|null
     */
    public function visitor(): ?array
    {
        $user = $this->auth->guard($this->config->get('simplyboost.guard'))->user();
        if (!$user instanceof Authenticatable) {
            return null;
        }

        $secret = (string) $this->config->get('simplyboost.identity_secret');
        if ($secret === '') {
            $this->log->warning('SimplyBoost identity not signed: SIMPLYBOOST_IDENTITY_SECRET is not set.');

            return null;
        }

        $details = $this->details($user);
        if ($details === null || trim((string) ($details['email'] ?? '')) === '') {
            return null;
        }

        try {
            return Identity::visitor($secret, (string) $details['email'], isset($details['name']) ? (string) $details['name'] : null);
        } catch (IdentityException $e) {
            // An account whose email is not an address stays anonymous.
            $this->log->info('SimplyBoost identity not signed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * @return array{email?: mixed, name?: mixed}|null
     */
    private function details(Authenticatable $user): ?array
    {
        if (self::$userResolver !== null) {
            return (self::$userResolver)($user);
        }

        return [
            'email' => data_get($user, (string) $this->config->get('simplyboost.email_attribute', 'email')),
            'name' => data_get($user, (string) $this->config->get('simplyboost.name_attribute', 'name')),
        ];
    }

    private function viteNonce(): ?string
    {
        if (!class_exists(Vite::class)) {
            return null;
        }
        $nonce = Vite::cspNonce();

        return is_string($nonce) && $nonce !== '' ? $nonce : null;
    }
}
