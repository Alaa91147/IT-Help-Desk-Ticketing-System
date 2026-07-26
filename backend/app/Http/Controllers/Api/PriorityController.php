<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Priority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriorityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Priority::query()
                ->where('isActive', true)
                ->orderBy('priorityLevel')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'priorityName' => ['required', 'string', 'max:255', 'unique:priorities,priorityName'],
            'description' => ['nullable', 'string', 'max:255'],
            'priorityLevel' => ['required', 'integer', 'min:1', 'max:255', 'unique:priorities,priorityLevel'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $priority = Priority::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Priority created successfully.',
            'data' => $priority,
        ], 201);
    }

    public function show(Priority $priority): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $priority,
        ]);
    }

    public function update(
        Request $request,
        Priority $priority
    ): JsonResponse {
        $validated = $request->validate([
            'priorityName' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('priorities', 'priorityName')->ignore($priority->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'priorityLevel' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:255',
                Rule::unique('priorities', 'priorityLevel')->ignore($priority->id),
            ],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $priority->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Priority updated successfully.',
            'data' => $priority->fresh(),
        ]);
    }

    public function destroy(Priority $priority): JsonResponse
    {
        if ($priority->tickets()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This priority is used by tickets and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        $priority->delete();

        return response()->json([
            'success' => true,
            'message' => 'Priority deleted successfully.',
        ]);
    }
}