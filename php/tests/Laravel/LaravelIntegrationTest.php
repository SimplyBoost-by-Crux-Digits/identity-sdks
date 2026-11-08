<?php

declare(strict_types=1);

namespace SimplyBoost\Identity\Tests\Laravel;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase;
use SimplyBoost\Identity\Identity;
use SimplyBoost\Identity\Laravel\SimplyBoostServiceProvider;
use SimplyBoost\Identity\Laravel\WidgetRenderer;

final class LaravelIntegrationTest extends TestCase
{
    private const SECRET = 'laravel-test-secret';

    protected function getPackageProviders($app): array
    {
        return [SimplyBoostServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // The "web" middleware encrypts cookies; any key will do in tests.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('simplyboost.bot_id', 'bot-123');
        $app['config']->set('simplyboost.identity_secret', self::SECRET);
    }

    protected function tearDown(): void
    {
        WidgetRenderer::resolveUserUsing(null);
        parent::tearDown();
    }

    private function signIn(string $email = 'Jane@Example.com', string $name = 'Jane Doe'): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'email' => $email, 'name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private static function config(string $html): array
    {
        self::assertSame(1, preg_match('/window\._CHATBOT_CONFIG_ = (\{.*?\});<\/script>/', $html, $m), $html);

        return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
    }

    public function testDirectiveSignsTheLoggedInUser(): void
    {
        $this->signIn();
        $config = self::config(Blade::render('@simplyboostWidget'));

        self::assertSame('bot-123', $config['chat_bot_id']);
        self::assertSame('jane@example.com', $config['visitor']['email']);
        self::assertSame('Jane Doe', $config['visitor']['name']);
        self::assertSame('/simplyboost/identity', $config['visitor']['refresh_url']);
        self::assertSame(
            Identity::computeUserHash(self::SECRET, 'jane@example.com', $config['visitor']['ts']),
            $config['visitor']['user_hash']
        );
    }

    public function testComponentRendersTheSameSnippetWithANonce(): void
    {
        $this->signIn();
        $html = Blade::render('<x-simplyboost-widget nonce="abc123" />');

        self::assertSame('jane@example.com', self::config($html)['visitor']['email']);
        self::assertSame(2, substr_count($html, '<script nonce="abc123">'));
    }

    public function testNameIsNotCompiledAsBlade(): void
    {
        $this->signIn('jane@example.com', '{{ 7*7 }} @php echo "x"; @endphp </script>');
        $html = Blade::render('<x-simplyboost-widget />');

        self::assertSame('{{ 7*7 }} @php echo "x"; @endphp </script>', self::config($html)['visitor']['name']);
        self::assertSame(2, substr_count($html, '</script>'));
    }

    public function testGuestGetsAnAnonymousWidget(): void
    {
        $config = self::config(Blade::render('@simplyboostWidget'));

        self::assertSame(['chat_bot_id' => 'bot-123'], $config);
    }

    public function testMissingBotIdRendersNothing(): void
    {
        config(['simplyboost.bot_id' => null]);

        self::assertSame('', Blade::render('@simplyboostWidget'));
    }

    public function testMissingSecretRendersAnAnonymousWidget(): void
    {
        config(['simplyboost.identity_secret' => '']);
        $this->signIn();

        self::assertArrayNotHasKey('visitor', self::config(Blade::render('@simplyboostWidget')));
    }

    public function testCustomUserResolver(): void
    {
        WidgetRenderer::resolveUserUsing(static function ($user): array {
            return ['email' => 'dealer-' . $user->getAuthIdentifier() . '@portal.example', 'name' => 'Dealer'];
        });
        $this->signIn();

        $visitor = self::config(Blade::render('@simplyboostWidget'))['visitor'];
        self::assertSame('dealer-1@portal.example', $visitor['email']);
        self::assertSame('Dealer', $visitor['name']);
    }

    public function testRefreshEndpointSignsTheLoggedInUser(): void
    {
        $this->signIn();
        $response = $this->getJson('/simplyboost/identity');

        $response->assertOk();
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $body = $response->json();
        self::assertSame(['email', 'name', 'ts', 'user_hash'], array_keys($body));
        self::assertSame('jane@example.com', $body['email']);
        self::assertEqualsWithDelta(time(), $body['ts'], 5);
        self::assertSame(Identity::computeUserHash(self::SECRET, 'jane@example.com', $body['ts']), $body['user_hash']);
    }

    public function testRefreshEndpointRefusesGuests(): void
    {
        $response = $this->getJson('/simplyboost/identity');

        $response->assertStatus(401);
        self::assertSame([], $response->json());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testRouteIsNamed(): void
    {
        self::assertTrue(Route::has(WidgetRenderer::ROUTE_NAME));
    }
}
