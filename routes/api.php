<?php

use App\Http\Controllers\Api\V1\TelemetryPingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ZONE Device API — v1
|--------------------------------------------------------------------------
| Auth: Authorization: Bearer <SANCTUM_TOKEN>  ·  Accept: application/json
*/

Route::middleware(['device.auth', 'throttle:telemetry'])->group(function () {
    Route::post('/telemetry/ping', TelemetryPingController::class)->name('api.telemetry.ping');
});
