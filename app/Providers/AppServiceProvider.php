<?php

namespace App\Providers;

use App\Contracts\AIDriverContract;
use App\Contracts\MediaStorageContract;
use App\Contracts\ThemeRendererContract;
use App\Modules\AI\GeminiDriver;
use App\Modules\AI\QwenDriver;
use App\Modules\Media\MediaService;
use App\Modules\Shortcode\ShortcodeRegistry;
use App\Modules\Theme\ThemeRenderer;
use App\Support\ApiRateLimitKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // AI Driver
        $this->app->bind(AIDriverContract::class, function ($app) {
            return match (config('ai.default_driver', 'gemini')) {
                'qwen' => $app->make(QwenDriver::class),
                default => $app->make(GeminiDriver::class),
            };
        });

        // Shortcode Registry (singleton — handlers registered once)
        $this->app->singleton(ShortcodeRegistry::class, function ($app) {
            $registry = new ShortcodeRegistry;
            foreach (config('shortcodes.handlers', []) as $name => $class) {
                if (class_exists($class)) {
                    $registry->register($app->make($class));
                }
            }

            return $registry;
        });

        // Media
        $this->app->bind(MediaStorageContract::class, MediaService::class);

        // Theme
        $this->app->bind(ThemeRendererContract::class, ThemeRenderer::class);
    }

    public function boot(): void
    {
        RateLimiter::for('api-public', function (Request $request) {
            return Limit::perMinute(60)->by('api:public:'.$request->ip());
        });

        RateLimiter::for('api-authenticated', function (Request $request) {
            $identifier = $request->bearerToken() ?? $request->header('X-API-Token');

            if ($identifier === null) {
                return Limit::perMinute(5)->by(ApiRateLimitKey::missingAuthenticatedToken($request->ip()));
            }

            return Limit::perMinute(300)->by(ApiRateLimitKey::authenticated($identifier));
        });

        RateLimiter::for('auth-token', function (Request $request) {
            return Limit::perMinute(5)->by('auth-token:'.$request->ip());
        });
    }
}
