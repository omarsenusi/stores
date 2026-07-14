<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('stores:scrape-next --batch=20')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->description('Continuously scrape the next Salla store IDs in the background');
