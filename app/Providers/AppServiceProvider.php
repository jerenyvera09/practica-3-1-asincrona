<?php

namespace App\Providers;

use App\Services\Citas\PublicadorNotificaciones;
use App\Services\Citas\RabbitMqPublicador;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PublicadorNotificaciones::class, RabbitMqPublicador::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
