<?php

namespace App\Providers;

use App\Models\Detection;
use App\Observers\DetectionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Detection::observe(DetectionObserver::class);
    }
}
