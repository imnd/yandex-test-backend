<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $isProductionOrHttps = config('app.env') === 'production'
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || (isset($_SERVER['HTTP_HOST']) && str_contains($_SERVER['HTTP_HOST'], 'onrender.com'));

        config([
            'session.debug_is_prod' => $isProductionOrHttps,
            'session.debug_app_env' => config('app.env'),
            'session.debug_server' => isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : 'none',
        ]);

        if ($isProductionOrHttps) {
            config(['session.secure' => true]);
            config(['session.same_site' => 'none']);
        }
    }

    public function boot(): void
    {
        //
    }
}
