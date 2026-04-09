<?php

use App\Http\Controllers\Api\SearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public health check
Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'app'    => config('app.name'),
    'time'   => now()->toIso8601String(),
]));

// Search — accept either Sanctum tokens OR an active web session
Route::middleware(['auth:sanctum,web'])->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/v1/search', SearchController::class);
});
