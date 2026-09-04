<?php

namespace App\Providers;

use App\Contracts\AttachmentStorage;
use App\Contracts\SmsSender;
use App\Services\LocalAttachmentStorage;
use App\Services\TestSmsSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AttachmentStorage::class, LocalAttachmentStorage::class);
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
