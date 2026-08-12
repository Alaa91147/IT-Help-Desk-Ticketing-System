<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PriorityController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\TicketAttachmentController;
use App\Http\Controllers\Api\TicketCommentController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketRequestController;
use App\Http\Controllers\Api\UserManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

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
        Route::patch(
            '/me',
            [AuthController::class, 'updateProfile']
        );
        Route::post(
         '/me/verify-email-change',
         [AuthController::class, 'verifyEmailChange']
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

/*
|--------------------------------------------------------------------------
| Admin User Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'role:Admin',
])->prefix('admin/users')->group(function (): void {
        Route::get(
        '/{user}',
        [UserManagementController::class, 'show']
    );
    Route::patch(
    '/{user}',
    [UserManagementController::class, 'update']
    );
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

    Route::patch(
        '/{user}/role',
        [UserManagementController::class, 'updateRole']
    );
});

/*
|--------------------------------------------------------------------------
| Role Test Endpoints
|--------------------------------------------------------------------------
*/

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
    'role:Manager',
])->get('/manager/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Welcome Manager',
    ]);
});

/*
|--------------------------------------------------------------------------
| Authenticated Endpoints
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Lookups
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/categories',
        [CategoryController::class, 'index']
    );

    Route::get(
        '/categories/{category}',
        [CategoryController::class, 'show']
    );

    Route::get(
        '/priorities',
        [PriorityController::class, 'index']
    );

    Route::get(
        '/priorities/{priority}',
        [PriorityController::class, 'show']
    );

    Route::get(
        '/statuses',
        [StatusController::class, 'index']
    );

    Route::get(
        '/statuses/{status}',
        [StatusController::class, 'show']
    );

    Route::get(
        '/support-agents',
        [UserManagementController::class, 'supportAgents']
    );

    Route::get(
        '/manager/reports/tickets',
        [TicketController::class, 'ticketSummary']
    );

    Route::get(
        '/reports/tickets/pdf',
        [\App\Http\Controllers\Api\ReportController::class, 'ticketsPdf']
    )->middleware('role:Admin,Manager,SupportAgent');

    Route::get(
        '/reports/tickets/excel',
        [\App\Http\Controllers\Api\ReportController::class, 'ticketsExcel']
    )->middleware('role:Admin,Manager,SupportAgent');

    // AI assistant chat
    Route::post(
        '/assistant/chat',
        [\App\Http\Controllers\Api\AssistantController::class, 'chat']
    );

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/notifications',
        [NotificationController::class, 'index']
    );

    Route::patch(
        '/notifications/read-all',
        [NotificationController::class, 'markAllAsRead']
    );

    Route::patch(
        '/notifications/{notification}/read',
        [NotificationController::class, 'markAsRead']
    );

    Route::delete(
        '/notifications/{notification}',
        [NotificationController::class, 'destroy']
    );
});

/*
|--------------------------------------------------------------------------
| Admin Category and Priority Management
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    'role:Admin',
])->group(function (): void {
    Route::post(
        '/categories',
        [CategoryController::class, 'store']
    );

    Route::patch(
        '/categories/{category}',
        [CategoryController::class, 'update']
    );

    Route::delete(
        '/categories/{category}',
        [CategoryController::class, 'destroy']
    );

    Route::post(
        '/priorities',
        [PriorityController::class, 'store']
    );

    Route::patch(
        '/priorities/{priority}',
        [PriorityController::class, 'update']
    );

    Route::delete(
        '/priorities/{priority}',
        [PriorityController::class, 'destroy']
    );
});

/*
|--------------------------------------------------------------------------
| Tickets, Comments, and Attachments
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')
    ->prefix('tickets')
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Ticket CRUD
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/',
            [TicketController::class, 'index']
        );

        Route::post(
            '/',
            [TicketController::class, 'store']
        );

        Route::get(
            '/agent-requests',
            [TicketRequestController::class, 'index']
        )->middleware('role:Admin');

        Route::get(
            '/{ticket}',
            [TicketController::class, 'show']
        );

        Route::put(
            '/{ticket}',
            [TicketController::class, 'update']
        );

        Route::delete(
            '/{ticket}',
            [TicketController::class, 'destroy']
        );

        /*
        |--------------------------------------------------------------------------
        | Assignment and Status Workflow
        |--------------------------------------------------------------------------
        */

        Route::patch(
            '/{ticket}/assign',
            [TicketController::class, 'assign']
        );

        Route::patch(
            '/{ticket}/start',
            [TicketController::class, 'start']
        );

                Route::patch(
            '/{ticket}/pause',
            [TicketController::class, 'pause']
        );

        Route::patch(
            '/{ticket}/resume',
            [TicketController::class, 'resume']
        );
                Route::patch(
            '/{ticket}/escalate',
            [TicketController::class, 'escalate']
        );
                Route::patch(
            '/{ticket}/cancel',
            [TicketController::class, 'cancel']
        );
        Route::patch(
            '/{ticket}/resolve',
            [TicketController::class, 'resolve']
        );

        Route::patch(
            '/{ticket}/close',
            [TicketController::class, 'close']
        );

        /*
        |--------------------------------------------------------------------------
        | Comments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{ticket}/comments',
            [TicketCommentController::class, 'index']
        );

        Route::post(
            '/{ticket}/comments',
            [TicketCommentController::class, 'store']
        );

        Route::patch(
            '/{ticket}/comments/{comment}',
            [TicketCommentController::class, 'update']
        );

        Route::delete(
            '/{ticket}/comments/{comment}',
            [TicketCommentController::class, 'destroy']
        );

        /*
        |--------------------------------------------------------------------------
        | Attachments
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/{ticket}/attachments',
            [TicketAttachmentController::class, 'index']
        );

        Route::post(
            '/{ticket}/attachments',
            [TicketAttachmentController::class, 'store']
        );

        Route::get(
            '/{ticket}/attachments/{attachment}/download',
            [TicketAttachmentController::class, 'download']
        );

        Route::delete(
            '/{ticket}/attachments/{attachment}',
            [TicketAttachmentController::class, 'destroy']
        );

        // AI triage: categorize and prioritize via AI
        Route::post(
            '/{ticket}/ai-triage',
            [\App\Http\Controllers\Api\TicketAiController::class, 'triage']
        );

        // Agent request workflow
        Route::post(
            '/{ticket}/agent-requests',
            [TicketRequestController::class, 'request']
        );

        // Admin accept/reject
        Route::patch(
            '/{ticket}/agent-request/accept',
            [TicketRequestController::class, 'accept']
        )->middleware('role:Admin');

        Route::patch(
            '/{ticket}/agent-request/reject',
            [TicketRequestController::class, 'reject']
        )->middleware('role:Admin');
    });