<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Agent\AgentAddressController;
use App\Http\Controllers\Api\V1\Agent\AgentCheckRunController;
use App\Http\Controllers\Api\V1\Agent\AgentLoginController;
use App\Http\Controllers\Api\V1\Agent\AgentLogoutController;
use App\Http\Controllers\Api\V1\Agent\AgentMeController;
use App\Http\Controllers\Api\V1\Agent\AgentSiteBodyChangeController;
use App\Http\Controllers\Api\V1\Agent\AgentSiteController;
use App\Http\Controllers\Api\V1\Agent\AgentSnapshotController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/agent')->name('v1.agent.')->group(function (): void {
    Route::post('/login', [AgentLoginController::class, 'store'])
        ->middleware('throttle:agent-login')
        ->name('login');

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
