<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token(),
    ]);
});

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Organization Routes
Route::middleware('auth:sanctum')->prefix('organization')->group(function () {
    Route::get('/', [OrganizationController::class, 'getOrganization']);
    Route::post('/settings', [OrganizationController::class, 'saveSettings']);
    Route::get('/reviews', [OrganizationController::class, 'getReviews']);
    Route::post('/refresh', [OrganizationController::class, 'refreshReviews']);
});
