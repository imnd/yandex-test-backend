<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token(),
    ]);
});

Route::get('/debug-session', function () {
    return response()->json([
        'session_config' => config('session'),
        'server' => array_intersect_key($_SERVER, array_flip([
            'HTTP_HOST', 'HTTP_X_FORWARDED_PROTO', 'HTTPS', 'APP_ENV'
        ])),
        'app_env' => env('APP_ENV'),
    ]);
});

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
    });
});

// Organization Routes
Route::middleware('auth:sanctum')->prefix('organization')->group(function () {
    Route::get('/', [OrganizationController::class, 'getOrganization']);
    Route::post('/settings', [OrganizationController::class, 'saveSettings']);
    Route::get('/reviews', [OrganizationController::class, 'getReviews']);
    Route::post('/refresh', [OrganizationController::class, 'refreshReviews']);
});
