<?php

use App\Http\Controllers\Admin\ProviderController as AdminProviderController;
use App\Http\Controllers\Admin\SponsoredSlotController;
use App\Http\Controllers\Admin\VettingController;
use App\Http\Controllers\Api\MarketplaceController;
use App\Http\Controllers\Api\MarketplaceEventController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Provider Marketplace routes
|--------------------------------------------------------------------------
| Merge this into routes/api.php, e.g.:
|   require __DIR__.'/marketplace_api.php';
|
| Routes are named (marketplace.*) so tests and any client code can resolve
| them with route('marketplace.slate') etc. regardless of where this file
| ends up mounted (routes/api.php typically prefixes everything with /api).
*/

Route::prefix('v1/marketplace')->middleware('throttle:60,1')->name('marketplace.')->group(function () {
    Route::get('/slate', [MarketplaceController::class, 'slate'])->name('slate');
    Route::post('/events', [MarketplaceEventController::class, 'store'])->name('events.store');
});


Route::prefix('v1/admin')->middleware(['auth:sanctum', 'marketplace.admin'])->name('marketplace.admin.')->group(function () {
    Route::get('/providers', [AdminProviderController::class, 'index'])->name('providers.index');
    Route::get('/providers/{provider}', [AdminProviderController::class, 'show'])->name('providers.show');
    Route::patch('/providers/{provider}', [AdminProviderController::class, 'update'])->name('providers.update');
    Route::post('/providers/{provider}/status', [AdminProviderController::class, 'transitionStatus'])->name('providers.status');

    Route::get('/vetting', [VettingController::class, 'index'])->name('vetting.index');
    Route::post('/providers/{provider}/vetting', [VettingController::class, 'store'])->name('vetting.store');

    Route::get('/slots', [SponsoredSlotController::class, 'index'])->name('slots.index');
    Route::post('/slots', [SponsoredSlotController::class, 'reserve'])->name('slots.reserve');
    Route::post('/slots/{slot}/activate', [SponsoredSlotController::class, 'activate'])->name('slots.activate');
    Route::post('/slots/{slot}/cancel', [SponsoredSlotController::class, 'cancel'])->name('slots.cancel');
});
