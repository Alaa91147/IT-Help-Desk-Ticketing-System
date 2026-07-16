<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post(
        '/register',
        [AuthController::class, 'register']
    )->middleware('throttle:5,1');

    Route::post(
        '/verify-otp',
        [AuthController::class, 'verifyOtp']
    )->middleware('throttle:10,1');

    Route::post(
        '/resend-otp',
        [AuthController::class, 'resendOtp']
    )->middleware('throttle:3,1');

    Route::post(
        '/login',
        [AuthController::class, 'login']
    )->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(
        function (): void {
            Route::get(
                '/me',
                [AuthController::class, 'me']
            );

            Route::post(
                '/logout',
                [AuthController::class, 'logout']
            );

            Route::post(
                '/logout-all',
                [AuthController::class, 'logoutAll']
            );
        }
    );
});



Route::middleware([
    'auth:sanctum',
    'role:Admin',
])->get('/admin/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Welcome Admin',
    ]);
});

Route::middleware([
    'auth:sanctum',
    'role:SupportAgent',
])->get('/agent/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Welcome Support Agent',
    ]);
});

Route::middleware([
    'auth:sanctum',
    'role:User',
])->get('/user/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Welcome User',
    ]);
});