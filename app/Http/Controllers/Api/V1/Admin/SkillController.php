<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateSkillRequest;
use App\Http\Requests\Admin\UpdateSkillRequest;
use App\Http\Resources\SkillResource;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class SkillController extends Controller
{
    public function store(CreateSkillRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $skill = Skill::create($data);

        return response()->json([
            'success' => true,
            'data' => new SkillResource($skill->load('category')),
        ], 201);
    }

    public function update(UpdateSkillRequest $request, string $id): JsonResponse
    {
        $skill = Skill::findOrFail($id);
        $data = $request->validated();

        if (! array_key_exists('slug', $data)) {
            unset($data['slug']);
        }

        $skill->update($data);

        return response()->json([
            'success' => true,
            'data' => new SkillResource($skill->load('category')),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $skill = Skill::findOrFail($id);

        if ($skill->candidateSkills()->exists() || $skill->jobSkills()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Skill in use.',
            ], 409);
        }

        $skill->delete();

        return response()->json([
            'success' => true,
            'message' => 'Skill deleted successfully.',
        ]);
    }
}
