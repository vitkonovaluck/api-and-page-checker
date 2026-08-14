<?php

namespace App\Providers;

use App\Jobs\CheckAddressJob;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
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
        // XAMPP php.ini sets max_execution_time=120 for CLI too, so queue:work
        // dies mid-usleep after ~2 minutes of jobs. CLI should not be capped.
        if ($this->app->runningInConsole()) {
            set_time_limit(0);
        }

        RateLimiter::for('address-checks', function (object $job) {
            $limit = max(1, (int) config('checking.max_per_minute', 30));

            if ($job instanceof CheckAddressJob) {
                $job->address->loadMissing('site');
                $limit = $job->address->site?->requestsPerMinute() ?? $limit;
                $key = 'site-'.(int) $job->address->site_id;
            } else {
                $key = 'global';
            }

            return Limit::perMinute($limit)->by($key);
        });
    }
}
