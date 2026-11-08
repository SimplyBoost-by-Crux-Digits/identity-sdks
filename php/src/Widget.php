<?php

declare(strict_types=1);

namespace SimplyBoost\Identity;

/**
 * The HTML that loads the SimplyBoost chat widget, with the signed-in
 * visitor from Identity::visitor().
 */
final class Widget
{
    public const DEFAULT_URL = 'https://get.simplyboost.io/widget.js';

    private const SCRIPT_JSON = JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    private function __construct()
    {
    }

    /**
     * The window._CHATBOT_CONFIG_ object. refresh_url (a path on your own
     * site that returns a fresh Identity::visitor() as JSON) is only added
     * for a signed-in visitor.
     *
     * @param array<string, mixed>|null $visitor
     *
     * @return array<string, mixed>
     *
     * @throws IdentityException
     */
    public static function config(string $botId, ?array $visitor = null, ?string $refreshUrl = null): array
    {
        $botId = trim($botId);
        if ($botId === '') {
            throw new IdentityException('bot id is required');
        }
        $config = ['chat_bot_id' => $botId];
        if ($visitor) {
            if ($refreshUrl !== null && $refreshUrl !== '') {
                $visitor['refresh_url'] = $refreshUrl;
            }
            $config['visitor'] = $visitor;
        }

        return $config;
    }

    /**
     * The HTML for the end of <body>. Every value is escaped for the
     * <script> element, so print it unescaped ({!! !!} in Blade).
     *
     * @param array<string, mixed>|null $visitor
     * @param array{refresh_url?: string|null, nonce?: string|null, widget_url?: string|null} $options
     *
     * @throws IdentityException
     */
    public static function snippet(string $botId, ?array $visitor = null, array $options = []): string
    {
        $config = self::scriptJson(self::config($botId, $visitor, $options['refresh_url'] ?? null));
        $widgetUrl = ($options['widget_url'] ?? null) ?: self::DEFAULT_URL;
        $src = self::scriptJson($widgetUrl . (strpos($widgetUrl, '?') === false ? '?' : '&') . 'v=');
        $nonce = $options['nonce'] ?? null;
        $attr = ($nonce !== null && $nonce !== '')
            ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            : '';

        return '<script' . $attr . '>window._CHATBOT_CONFIG_ = ' . $config . ';</script>' . "\n"
            . '<script' . $attr . '>(function(d,s,id){var js=d.createElement(s);js.id=id;'
            . 'js.src=' . $src . '+Date.now();d.body.appendChild(js);})'
            . "(document,'script','simplyboost-classic-script');</script>";
    }

    /**
     * @param mixed $value
     */
    private static function scriptJson($value): string
    {
        try {
            return json_encode($value, self::SCRIPT_JSON);
        } catch (\JsonException $e) {
            throw new IdentityException('widget config is not valid UTF-8', 0, $e);
        }
    }
}
