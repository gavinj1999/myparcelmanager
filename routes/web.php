<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatePeriodController;
use App\Http\Controllers\RoundController;
use App\Http\Controllers\ParcelTypeController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AutomationController;
use App\Http\Controllers\Api\ImageUploadController;
use App\Http\Controllers\ActivityImageController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('date-periods', DatePeriodController::class)->except(['create', 'edit', 'show']);
    Route::resource('rounds', RoundController::class)->except(['create', 'edit', 'show']);
    Route::resource('parcel-types', ParcelTypeController::class)->except(['create', 'edit', 'show']);
    Route::post('parcel-types/bulk', [ParcelTypeController::class, 'storeBulk'])->name('parcel-types.bulk');
    Route::resource('activities', ActivityController::class)->except(['create', 'edit', 'show']);
    Route::post('activities/bulk', [ActivityController::class, 'storeBulk'])->name('activities.bulk');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/activities/details', [ActivityController::class, 'details'])->name('activities.details');
    Route::get('end-of-day-image', [ActivityImageController::class, 'index'])->name('endofdayimages');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
