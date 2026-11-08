<?php

declare(strict_types=1);

namespace SimplyBoost\Identity\Tests\Core;

use PHPUnit\Framework\TestCase;
use SimplyBoost\Identity\Identity;
use SimplyBoost\Identity\IdentityException;
use SimplyBoost\Identity\Widget;

final class IdentityTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function vectors(): array
    {
        $json = file_get_contents(__DIR__ . '/../../../test-vectors.json');

        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function testEveryVectorMatchesTheServer(): void
    {
        foreach (self::vectors()['vectors'] as $vector) {
            self::assertSame(
                $vector['user_hash'],
                Identity::computeUserHash($vector['secret'], $vector['email'], $vector['ts']),
                $vector['name']
            );
            self::assertSame($vector['normalized_email'], Identity::normalizeEmail($vector['email']), $vector['name']);
        }
    }

    public function testWindowConstantsMatchTheServer(): void
    {
        $vectors = self::vectors();
        self::assertSame($vectors['ttl_seconds'], Identity::TTL_SECONDS);
        self::assertSame($vectors['skew_seconds'], Identity::SKEW_SECONDS);
    }

    public function testComputeDefaultsTsToNow(): void
    {
        $before = time();
        $signature = Identity::compute('secret', 'a@example.com');
        self::assertGreaterThanOrEqual($before, $signature['ts']);
        self::assertLessThanOrEqual(time(), $signature['ts']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $signature['user_hash']);
    }

    public function testVisitorReturnsTheNormalizedEmailItSigned(): void
    {
        self::assertSame(
            [
                'email' => 'jane@example.com',
                'name' => 'Jane',
                'ts' => 1792768282,
                'user_hash' => Identity::computeUserHash('secret', 'jane@example.com', 1792768282),
            ],
            Identity::visitor('secret', '  Jane@Example.COM ', ' Jane ', 1792768282)
        );
        self::assertArrayNotHasKey('name', Identity::visitor('secret', 'a@example.com', null, 1));
        self::assertArrayNotHasKey('name', Identity::visitor('secret', 'a@example.com', '  ', 1));
    }

    public function testRefusesInputTheServerWouldRefuse(): void
    {
        $cases = [
            ['', 'a@example.com', 1],
            ['secret', '', 1],
            ['secret', "  \t", 1],
            ['secret', 'not-an-email', 1],
            ['secret', 'a@' . str_repeat('b', 400), 1],
            ['secret', 'a@example.com', -1],
            ['secret', "\xff@example.com", 1],
        ];
        foreach ($cases as $case) {
            try {
                Identity::computeUserHash(...$case);
                self::fail('Accepted ' . json_encode($case, JSON_INVALID_UTF8_SUBSTITUTE));
            } catch (IdentityException $e) {
                self::assertInstanceOf(\InvalidArgumentException::class, $e);
            }
        }
    }

    public function testGuestSnippetHasNoVisitor(): void
    {
        $html = Widget::snippet('bot-123', null, ['refresh_url' => '/simplyboost/identity']);
        self::assertStringContainsString('window._CHATBOT_CONFIG_ = {"chat_bot_id":"bot-123"};', $html);
        self::assertStringNotContainsString('visitor', $html);
        self::assertStringContainsString('"https://get.simplyboost.io/widget.js?v="+Date.now()', $html);
    }

    public function testSignedInConfigCarriesVisitorAndRefreshUrl(): void
    {
        $visitor = Identity::visitor('s', 'jane@example.com', 'Jane', 1);
        $config = Widget::config('bot', $visitor, '/simplyboost/identity');
        self::assertSame('/simplyboost/identity', $config['visitor']['refresh_url']);
        self::assertSame($visitor['user_hash'], $config['visitor']['user_hash']);
    }

    public function testValuesCannotBreakOutOfTheScriptElement(): void
    {
        $visitor = ['email' => 'a@example.com', 'name' => "</script><script>alert(1)</script>&\u{2028}", 'ts' => 1, 'user_hash' => 'x'];
        $html = Widget::snippet('bot', $visitor, ['nonce' => 'n"><x']);
        self::assertStringNotContainsString('</script><script>alert', $html);
        self::assertStringContainsString('\u003C/script\u003E', $html);
        self::assertStringContainsString('\u2028', $html);
        self::assertStringContainsString('nonce="n&quot;&gt;&lt;x"', $html);
        self::assertSame(2, substr_count($html, '</script>'));
    }

    public function testWidgetUrlWithQueryString(): void
    {
        $html = Widget::snippet('bot', null, ['widget_url' => 'https://cdn.example.com/w.js?x=1']);
        self::assertStringContainsString('"https://cdn.example.com/w.js?x=1\\u0026v="+Date.now()', $html);
    }

    public function testBotIdIsRequired(): void
    {
        $this->expectException(IdentityException::class);
        Widget::snippet(' ');
    }
}
