<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AddressApiController;
use App\Http\Controllers\Api\V1\Agent\AgentAddressController;
use App\Http\Controllers\Api\V1\Agent\AgentCheckRunController;
use App\Http\Controllers\Api\V1\Agent\AgentExtensionLoginController;
use App\Http\Controllers\Api\V1\Agent\AgentLoginController;
use App\Http\Controllers\Api\V1\Agent\AgentLogoutController;
use App\Http\Controllers\Api\V1\Agent\AgentMeController;
use App\Http\Controllers\Api\V1\Agent\AgentProviderController;
use App\Http\Controllers\Api\V1\Agent\AgentSiteBodyChangeController;
use App\Http\Controllers\Api\V1\Agent\AgentSiteController;
use App\Http\Controllers\Api\V1\Agent\AgentSnapshotController;
use App\Http\Controllers\Api\V1\CheckRunApiController;
use App\Http\Controllers\Api\V1\SiteApiController;
use App\Http\Controllers\Api\V1\SnapshotApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->middleware([
    'auth:sanctum',
    'ability:api',
    'throttle:public-api',
])->group(function (): void {
    Route::apiResource('sites', SiteApiController::class);
    Route::apiResource('sites.addresses', AddressApiController::class)->scoped();
    Route::post('sites/{site}/check-runs', [CheckRunApiController::class, 'store'])->name('sites.check-runs.store');
    Route::get('sites/{site}/addresses/{address}/snapshots', [SnapshotApiController::class, 'index'])
        ->scopeBindings()
        ->name('sites.addresses.snapshots.index');
    Route::get('sites/{site}/addresses/{address}/diff', [SnapshotApiController::class, 'diff'])
        ->scopeBindings()
        ->name('sites.addresses.diff');
    Route::post('sites/{site}/addresses/{address}/baseline', [SnapshotApiController::class, 'baseline'])
        ->scopeBindings()
        ->name('sites.addresses.baseline');
});

Route::prefix('v1/agent')->name('v1.agent.')->group(function (): void {
    Route::post('/login', [AgentLoginController::class, 'store'])
        ->middleware('throttle:agent-login')
        ->name('login');
    Route::get('/providers', [AgentProviderController::class, 'index'])
        ->middleware('throttle:extension-login')
        ->name('providers');
    Route::post('/extension-logins', [AgentExtensionLoginController::class, 'store'])
        ->middleware('throttle:extension-login')
        ->name('extension-logins.store');
    Route::get('/extension-logins/{ticket}', [AgentExtensionLoginController::class, 'show'])
        ->middleware('throttle:extension-login')
        ->name('extension-logins.show');

    Route::middleware([
        'auth:sanctum',
        'ability:agent',
        'check-agent',
        'throttle:agent-api',
    ])->group(function (): void {
        Route::get('/me', [AgentMeController::class, 'show'])->name('me');
        Route::get('/sites', [AgentSiteController::class, 'index'])->name('sites');
        Route::post('/sites/{site}/addresses', [AgentAddressController::class, 'store'])
            ->name('sites.addresses.store');
        Route::get('/sites/{site}/body-changes', [AgentSiteBodyChangeController::class, 'index'])
            ->name('sites.body-changes');
        Route::post('/check-runs', [AgentCheckRunController::class, 'store'])->name('check-runs.store');
        Route::post('/check-runs/{checkRun}/snapshots', [AgentSnapshotController::class, 'store'])
            ->name('check-runs.snapshots.store');
        Route::post('/logout', [AgentLogoutController::class, 'store'])->name('logout');
    });
});
