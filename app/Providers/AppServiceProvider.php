<?php

namespace App\Providers;

use App\Mail\GraphApiTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Mail::extend('graph', function (array $config = []) {
            return new GraphApiTransport(
                config('services.azure.tenant_id'),
                config('services.azure.client_id'),
                config('services.azure.client_secret'),
                config('mail.from.address'),
            );
        });
    }
}
