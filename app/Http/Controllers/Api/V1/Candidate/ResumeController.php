<?php

namespace App\Http\Controllers\Api\V1\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ResumeUploadRequest;
use App\Http\Resources\ResumeResource;
use App\Models\Candidate;
use App\Models\Resume;
use App\Services\FileUploadService;
use App\Services\ProfileCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResumeController extends Controller
{
    public function __construct(
        private FileUploadService $fileService,
    ) {
    }

    private function getCandidate(): Candidate
    {
        return Candidate::where('user_id', Auth::id())->firstOrFail();
    }

    public function index(): JsonResponse
    {
        $candidate = $this->getCandidate();
        $resumes = $candidate->resumes()->with('file')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => ResumeResource::collection($resumes),
        ]);
    }

    public function store(ResumeUploadRequest $request): JsonResponse
    {
        $candidate = $this->getCandidate();
        $isDefault = $request->boolean('is_default', false);

        $file = $this->fileService->upload(
            $request->file('file'),
            Auth::user(),
            'resume',
            'candidate',
            $candidate->id,
        );

        DB::transaction(function () use ($candidate, $request, $file, $isDefault) {
            $existingCount = $candidate->resumes()->count();

            if ($existingCount === 0 || $isDefault) {
                $candidate->resumes()->update(['is_default' => false]);
                $isDefault = true;
            }

            $resume = $candidate->resumes()->create([
                'title' => $request->input('title'),
                'file_id' => $file->id,
                'is_default' => $isDefault,
            ]);
        });

        $candidate->profile_completion_score = ProfileCompletionService::calculate($candidate);
        $candidate->save();

        $resume = $candidate->resumes()
            ->with('file')
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => new ResumeResource($resume),
        ], 201);
    }

    public function update(string $id): JsonResponse
    {
        $candidate = $this->getCandidate();
        $resume = $candidate->resumes()->findOrFail($id);

        $validated = request()->validate([
            'title' => ['sometimes', 'string', 'max:150'],
        ]);

        $resume->update($validated);

        return response()->json([
            'success' => true,
            'data' => new ResumeResource($resume->load('file')),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $candidate = $this->getCandidate();
        $resume = $candidate->resumes()->findOrFail($id);
        $file = $resume->file;

        $resume->delete();
        if ($file) {
            $this->fileService->delete($file);
        }

        $candidate->profile_completion_score = ProfileCompletionService::calculate($candidate);
        $candidate->save();

        return response()->json([
            'success' => true,
            'message' => 'Resume deleted successfully.',
        ]);
    }

    public function setDefault(string $id): JsonResponse
    {
        $candidate = $this->getCandidate();
        $resume = $candidate->resumes()->findOrFail($id);

        DB::transaction(function () use ($candidate, $resume) {
            $candidate->resumes()->update(['is_default' => false]);
            $resume->update(['is_default' => true]);
        });

        return response()->json([
            'success' => true,
            'data' => new ResumeResource($resume->load('file')),
        ]);
    }
}
