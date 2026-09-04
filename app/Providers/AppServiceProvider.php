<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Services\TestSmsSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TestSmsSender::class);
        $this->app->alias(TestSmsSender::class, SmsSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
