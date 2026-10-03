<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| ZONE scheduler
|--------------------------------------------------------------------------
| php artisan schedule:work   (local / WAMP)
| or register `php artisan schedule:run` in Windows Task Scheduler every minute
*/
Schedule::command('zone:purge-old-pings')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/purge.log'));
