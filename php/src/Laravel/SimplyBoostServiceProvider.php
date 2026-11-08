<?php

declare(strict_types=1);

namespace SimplyBoost\Identity\Laravel;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Auto-discovered by Laravel. Adds the config file, the @simplyboostWidget
 * directive, the <x-simplyboost-widget /> component and the identity
 * refresh route.
 */
class SimplyBoostServiceProvider extends ServiceProvider
{
    private const CONFIG = __DIR__ . '/../../config/simplyboost.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG, 'simplyboost');
        $this->app->bind(WidgetRenderer::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([self::CONFIG => $this->app->configPath('simplyboost.php')], 'simplyboost-config');
        }

        Blade::directive('simplyboostWidget', static function (string $expression): string {
            $nonce = trim($expression) === '' ? 'null' : $expression;

            return '<?php echo app(\\' . WidgetRenderer::class . '::class)->snippet(' . $nonce . '); ?>';
        });
        Blade::component('simplyboost-widget', WidgetComponent::class);

        if (!$this->app->routesAreCached() && $this->app['config']->get('simplyboost.refresh_route.enabled', true)) {
            Route::middleware((array) $this->app['config']->get('simplyboost.refresh_route.middleware', ['web']))
                ->get(
                    (string) $this->app['config']->get('simplyboost.refresh_route.path', 'simplyboost/identity'),
                    IdentityController::class
                )
                ->name(WidgetRenderer::ROUTE_NAME);
        }
    }
}
