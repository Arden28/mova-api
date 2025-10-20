<?php

namespace App\Providers;

use App\Domain\Pricing\Services\PricingEngine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PricingEngine::class, fn() => new PricingEngine());
        
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
