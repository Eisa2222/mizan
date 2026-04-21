<?php

use Modules\Search\Http\Controllers\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public health check
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'app'    => config('app.name'),
    'time'   => now()->toIso8601String(),
]));

// Auth: web session only. Sanctum is NOT installed (no token issuance yet).
// When tokens become needed, run `php artisan install:api` and switch to
// `auth:sanctum,web`.
Route::middleware(['auth:web'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/v1/search', SearchController::class);
});

// Catch-all for /api/* — return JSON rather than HTML stack traces on error.
Route::fallback(function () {
    return response()->json(['message' => 'Route not found', 'status' => 404], 404);
});
