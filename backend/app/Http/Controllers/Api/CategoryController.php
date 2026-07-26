<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Category::query()
                ->where('isActive', true)
                ->orderBy('categoryName')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'categoryName' => ['required', 'string', 'max:255', 'unique:categories,categoryName'],
            'description' => ['nullable', 'string', 'max:255'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $category = Category::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => $category,
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    public function update(
        Request $request,
        Category $category
    ): JsonResponse {
        $validated = $request->validate([
            'categoryName' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'categoryName')->ignore($category->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'isActive' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $category->fresh(),
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->tickets()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This category is used by tickets and cannot be deleted. Deactivate it instead.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}