<?php

use App\Http\Controllers\Api\V1\HealthController;
use Illuminate\Support\Facades\Route;

// All routes here are served under /api/v1 (see bootstrap/app.php).

// US1: public service status (60 requests/min per IP).
Route::get('/health', HealthController::class)->middleware('throttle:public-health');
