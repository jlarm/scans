<?php

declare(strict_types=1);

use App\Http\Controllers\ScanController;
use App\Http\Controllers\ScanIndexController;
use App\Http\Controllers\ScanReportController;
use App\Http\Controllers\ScanShowController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('scans', [ScanIndexController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('scans.index');

Route::get('scans/create', [ScanController::class, 'create'])
    ->middleware(['auth', 'verified'])
    ->name('scans.create');

Route::get('scans/{scan}/progress', [ScanIndexController::class, 'progress'])
    ->middleware(['auth', 'verified'])
    ->name('scans.progress');

Route::get('scans/{scan}', [ScanShowController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->name('scans.show');

Route::get('scans/{scan}/report.pdf', [ScanReportController::class, 'generatePdf'])
    ->middleware(['auth', 'verified'])
    ->name('scans.report.pdf');

Route::post('scans', [ScanController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('scans.store');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
