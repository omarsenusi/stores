<?php

use App\Http\Controllers\Admin\CampaignController;
use App\Http\Controllers\Admin\MailSettingsController;
use App\Http\Controllers\Admin\NotificationCampaignController;
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

    // Notification Campaigns Routes
    Route::get('notification-campaigns', [NotificationCampaignController::class, 'index'])->name('notification-campaigns.index');
    Route::get('notification-campaigns/create/{campaign?}', [NotificationCampaignController::class, 'create'])->name('notification-campaigns.create');
    Route::post('notification-campaigns/step-1', [NotificationCampaignController::class, 'storeStep1'])->name('notification-campaigns.step1');
    Route::post('notification-campaigns/{campaign}/step-2', [NotificationCampaignController::class, 'storeStep2'])->name('notification-campaigns.step2');
    Route::post('notification-campaigns/{campaign}/step-3', [NotificationCampaignController::class, 'storeStep3'])->name('notification-campaigns.step3');
    Route::post('notification-campaigns/{campaign}/launch', [NotificationCampaignController::class, 'launch'])->name('notification-campaigns.launch');
    Route::post('notification-campaigns/{campaign}/pause', [NotificationCampaignController::class, 'pause'])->name('notification-campaigns.pause');
    Route::post('notification-campaigns/{campaign}/resume', [NotificationCampaignController::class, 'resume'])->name('notification-campaigns.resume');
    Route::delete('notification-campaigns/{campaign}', [NotificationCampaignController::class, 'destroy'])->name('notification-campaigns.destroy');
    Route::get('notification-campaigns/{campaign}/stats', [NotificationCampaignController::class, 'stats'])->name('notification-campaigns.stats');
    Route::get('api/stores-for-notifications', [NotificationCampaignController::class, 'apiStores'])->name('notification-campaigns.api_stores');

    // Mail Settings Routes
    Route::get('notification-settings', [MailSettingsController::class, 'index'])->name('notification-settings.index');
    Route::post('notification-settings', [MailSettingsController::class, 'update'])->name('notification-settings.update');
    Route::post('notification-settings/test', [MailSettingsController::class, 'test'])->name('notification-settings.test');
});

require __DIR__.'/settings.php';
