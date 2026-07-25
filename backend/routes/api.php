<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserManagementController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TicketController;

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

    Route::post(
        '/forgot-password',
        [AuthController::class, 'forgotPassword']
    )->middleware('throttle:3,1');

    Route::post(
        '/reset-password',
        [AuthController::class, 'resetPassword']
    )->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function (): void {
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
    });
});

Route::middleware([
    'auth:sanctum',
    'role:Admin',
])->prefix('admin/users')->group(function (): void {
    Route::get(
        '/',
        [UserManagementController::class, 'index']
    );

    Route::patch(
        '/{user}/activate',
        [UserManagementController::class, 'activate']
    );

    Route::patch(
        '/{user}/deactivate',
        [UserManagementController::class, 'deactivate']
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

Route::middleware([
    'auth:sanctum',
])->prefix('tickets')->group(function (): void {

    Route::get(
        '/',
        [TicketController::class, 'index']
    );

    Route::get(
    '/{ticket}',
    [TicketController::class, 'show']
    );

    Route::put(
        '/{ticket}',
        [TicketController::class, 'update']
    );

        Route::post(
        '/',
        [TicketController::class, 'store']
    );

    Route::delete(
    '/{ticket}',
    [TicketController::class, 'destroy']
    );

    Route::patch(
        '/{ticket}/assign',
        [TicketController::class, 'assign']
    );

    Route::patch('/{ticket}/start', 
    [TicketController::class, 'start']
    );

    Route::patch('/{ticket}/resolve', 
    [TicketController::class, 'resolve']
    );

    Route::patch('/{ticket}/close', 
    [TicketController::class, 'close']
    );
});