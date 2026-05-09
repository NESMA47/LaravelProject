<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function store(CreateCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category),
        ], 201);
    }

    public function update(UpdateCategoryRequest $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $data = $request->validated();

        // Slug is immutable unless explicitly passed
        if (! array_key_exists('slug', $data)) {
            unset($data['slug']);
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category),
        ]);
    }

   public function destroy(string $id): JsonResponse
{
    // Find the category or throw 404
    $category = Category::findOrFail($id);

    if ($category->skills()->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Action Denied: This category contains active skills. Please delete or reassign skills first.',
        ], 422);
    }

    if ($category->jobs()->exists()) {
        return response()->json([
            'success' => false,
            'message' => 'Action Denied: This category is linked to existing job postings.',
        ], 422);
    }

    $category->delete();

    return response()->json([
        'success' => true,
        'message' => 'Category deleted successfully.',
    ]);
}
}
