<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Bucket;
use App\Policies\BucketPolicy;

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
        Vite::prefetch(concurrency: 3);
        
        // Register policies
        Gate::policy(Bucket::class, BucketPolicy::class);
        
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            \URL::forceScheme('https');
        }
    }
}
