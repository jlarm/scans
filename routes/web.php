<?php

declare(strict_types=1);

use App\Http\Controllers\ScanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('scans/create', [ScanController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('scans.create');

Route::post('scans', [ScanController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('scans.store');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
