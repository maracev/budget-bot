<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use Telegram\Bot\Api;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local')) {
            if (class_exists(TelescopeApplicationServiceProvider::class)) {
                $this->app->register(TelescopeServiceProvider::class);
            }
        }

        $this->app->singleton(Api::class, function ($app): Api {
            $token = $app['config']->get('app.telegram_bot_token');

            return new Api($token ?: null);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
