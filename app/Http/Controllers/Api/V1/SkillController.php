<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Skill::query()
            ->where('is_active', true)
            ->with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $skills = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => SkillResource::collection($skills),
        ]);
    }

    public function autocomplete(Request $request): JsonResponse
    {
        $q = $request->input('q', '');

        $skills = Skill::query()
            ->where('is_active', true)
            ->where('name', 'like', '%' . $q . '%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $skills,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:skills,name'],
            'category_id' => ['sometimes', 'nullable', 'string', 'uuid', 'exists:categories,id'],
        ]);

        $skill = Skill::create([
            'name' => $validated['name'],
            'slug' => \Illuminate\Support\Str::slug($validated['name']),
            'category_id' => $validated['category_id'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'data' => new SkillResource($skill->load('category')),
        ], 201);
    }
}
