<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\CheckController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteController;
use App\Livewire\Addresses\Show as AddressShow;
use App\Livewire\Sites\Index as SitesIndex;
use App\Livewire\Sites\Show as SiteShow;
use Illuminate\Support\Facades\Route;

Route::get('/', SitesIndex::class)->name('sites.index');
Route::get('/sites/{site}', SiteShow::class)->name('sites.show');
Route::post('/sites/{site}/copy', [SiteController::class, 'copy'])->name('sites.copy');
Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
Route::post('/sites/{site}/check', [CheckController::class, 'storeAll'])->name('sites.check');

Route::get('/sites/{site}/addresses/{address}', AddressShow::class)->name('addresses.show');
Route::delete('/sites/{site}/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
Route::post('/sites/{site}/addresses/{address}/check', [CheckController::class, 'store'])->name('addresses.check');
Route::get('/sites/{site}/addresses/{address}/snapshots/{snapshot}', [AddressController::class, 'showSnapshot'])->name('addresses.snapshots.show');
Route::delete('/sites/{site}/addresses/{address}/snapshots/{snapshot}', [AddressController::class, 'destroySnapshot'])->name('addresses.snapshots.destroy');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings/backup', [SettingsController::class, 'backup'])->name('settings.backup');
Route::post('/settings/restore', [SettingsController::class, 'restore'])->name('settings.restore');
