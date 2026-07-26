<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Status;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Status::query()
                ->where('isActive', true)
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function show(Status $status): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }
}