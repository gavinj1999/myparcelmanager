<?php

use App\Http\Controllers\Api\ActivityImageController;
use Illuminate\Support\Facades\Route;

Route::post('/activity-images', [ActivityImageController::class, 'store']);
