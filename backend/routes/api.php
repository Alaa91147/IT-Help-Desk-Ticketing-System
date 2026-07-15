<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);

    });

});

/*
|--------------------------------------------------------------------------
| RBAC Test Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:Admin'])->get('/admin/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Welcome Admin'
    ]);
});

Route::middleware(['auth:sanctum', 'role:SupportAgent'])->get('/agent/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Welcome Support Agent'
    ]);
});

Route::middleware(['auth:sanctum', 'role:User'])->get('/user/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Welcome User'
    ]);
});