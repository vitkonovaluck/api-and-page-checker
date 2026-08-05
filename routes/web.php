<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'index'])->name('sites.index');
Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
Route::put('/sites/{site}', [SiteController::class, 'update'])->name('sites.update');
Route::post('/sites/{site}/copy', [SiteController::class, 'copy'])->name('sites.copy');
Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
Route::post('/sites/{site}/check', [CheckController::class, 'storeAll'])->name('sites.check');

Route::post('/sites/{site}/addresses', [AddressController::class, 'store'])->name('addresses.store');
Route::get('/sites/{site}/addresses/{address}', [AddressController::class, 'show'])->name('addresses.show');
Route::put('/sites/{site}/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
Route::delete('/sites/{site}/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
Route::post('/sites/{site}/addresses/{address}/check', [CheckController::class, 'store'])->name('addresses.check');
Route::get('/sites/{site}/addresses/{address}/snapshots/{snapshot}', [AddressController::class, 'showSnapshot'])->name('addresses.snapshots.show');
Route::delete('/sites/{site}/addresses/{address}/snapshots/{snapshot}', [AddressController::class, 'destroySnapshot'])->name('addresses.snapshots.destroy');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings/backup', [SettingsController::class, 'backup'])->name('settings.backup');
Route::post('/settings/restore', [SettingsController::class, 'restore'])->name('settings.restore');
