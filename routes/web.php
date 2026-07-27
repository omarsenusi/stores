<?php

use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\StoreController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::get('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
    Route::get('stores/export', [StoreController::class, 'export'])->name('stores.export');
    Route::get('stores', [StoreController::class, 'index'])->name('stores.index');

    // Campaigns routes
    Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::post('campaigns/excel', [CampaignController::class, 'storeExcel'])->name('campaigns.store_excel');
    Route::post('campaigns/google', [CampaignController::class, 'storeGoogle'])->name('campaigns.store_google');
    Route::post('campaigns/serpapi', [CampaignController::class, 'storeSerpApi'])->name('campaigns.store_serpapi');

    Route::get('campaigns/{campaign}/stats', [CampaignController::class, 'stats'])->name('campaigns.stats');
    Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])->name('campaigns.cancel');
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
    Route::get('campaigns/{campaign}/errors', [CampaignController::class, 'errors'])->name('campaigns.errors');
});

require __DIR__.'/settings.php';
