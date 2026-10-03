<?php

use App\Http\Controllers\AuthController;
use App\Livewire\DeviceManager;
use App\Livewire\EventLogTable;
use App\Livewire\LiveDashboard;
use App\Livewire\PeopleDirectory;
use App\Livewire\ZoneBuilder;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('monitoring'));

    Route::get('/monitoring', LiveDashboard::class)->name('monitoring');
    Route::get('/zones', ZoneBuilder::class)->name('zones.index');
    Route::get('/events', EventLogTable::class)->name('events.index');
    Route::get('/people', PeopleDirectory::class)->name('people.index');
    Route::get('/devices', DeviceManager::class)->name('devices.index');
});
