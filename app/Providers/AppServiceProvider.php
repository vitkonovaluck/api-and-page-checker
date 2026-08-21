<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\ColorScheme;
use App\Jobs\CheckAddressJob;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

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
            if (! $job instanceof CheckAddressJob) {
                return Limit::perMinute(max(1, (int) config('checking.max_per_minute', 30)));
            }

            $job->address->loadMissing('site');
            $perMinute = $job->address->site->checksPerMinute();

            if ($perMinute <= 0) {
                return Limit::none();
            }

            return Limit::perMinute($perMinute)->by('site-'.$job->address->site_id);
        });

        RateLimiter::for('agent-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip() ?? 'agent-login');
        });

        RateLimiter::for('agent-api', function (Request $request) {
            $userId = $request->user()?->getAuthIdentifier();

            return Limit::perMinute(60)->by('agent-api:'.($userId ?? $request->ip() ?? 'guest'));
        });

        $this->shareColorScheme();
    }

    private function shareColorScheme(): void
    {
        View::composer(
            ['layouts.app', 'layouts::app', 'layouts.landing', 'layouts::landing', 'layouts.guest', 'layouts::guest'],
            function (ViewInstance $view): void {
                $user = Auth::user();
                $scheme = $user instanceof User ? $user->color_scheme : ColorScheme::default();

                $view->with('colorScheme', $scheme);
            },
        );
    }
}
