<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileUploadRequest;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Employer;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function __construct(
        private FileUploadService $fileService,
    ) {
    }

    public function upload(FileUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        $fileType = $request->input('file_type');
        $entityId = $request->input('entity_id');

        $file = $this->fileService->upload(
            $request->file('file'),
            $user,
            $fileType,
            $request->input('entity_type'),
            $entityId,
        );

        // Auto-update related entity fields and replace old files
        match ($fileType) {
            'avatar' => $this->handleAvatarUpload($user, $file),
            'company_logo' => $this->handleCompanyLogoUpload($user, $file),
            'company_cover' => $this->handleCompanyCoverUpload($user, $file),
            default => null,
        };

        return response()->json([
            'success' => true,
            'data' => $file,
        ], 201);
    }

    private function handleAvatarUpload($user, $file): void
    {
        if ($user->avatar_file_id) {
            $oldFile = $user->avatarFile;
            $user->avatar_url = null;
            $user->avatar_file_id = null;
            $user->save();
            if ($oldFile) {
                $this->fileService->delete($oldFile);
            }
        }

        $user->avatar_url = $file->url;
        $user->avatar_file_id = $file->id;
        $user->save();
    }

    private function handleCompanyLogoUpload($user, $file): void
    {
        $employer = $user->employer;
        if (! $employer) {
            return;
        }

        if ($employer->logo_file_id) {
            $oldFile = $employer->logoFile;
            $employer->logo_url = null;
            $employer->logo_file_id = null;
            $employer->save();
            if ($oldFile) {
                $this->fileService->delete($oldFile);
            }
        }

        $employer->logo_url = $file->url;
        $employer->logo_file_id = $file->id;
        $employer->save();
    }

    private function handleCompanyCoverUpload($user, $file): void
    {
        $employer = $user->employer;
        if (! $employer) {
            return;
        }

        if ($employer->cover_image_file_id) {
            $oldFile = $employer->coverImageFile;
            $employer->cover_image_url = null;
            $employer->cover_image_file_id = null;
            $employer->save();
            if ($oldFile) {
                $this->fileService->delete($oldFile);
            }
        }

        $employer->cover_image_url = $file->url;
        $employer->cover_image_file_id = $file->id;
        $employer->save();
    }

    /**
     * Download/view a file by its ID.
     * Only the file owner or an admin can access it.
     */
    public function downloadFile(string $fileId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = request()->user();

        $file = \App\Models\File::findOrFail($fileId);

        $authorized = false;
        if ($user->role === 'admin') {
            $authorized = true;
        } elseif ($file->owner_id === $user->id) {
            $authorized = true;
        }

        if (! $authorized) {
            abort(403, 'You are not authorized to view this file.');
        }

        $path = $file->storage_path;
        if (! $path) {
            abort(404, 'File not found on disk.');
        }

        $disk = Storage::disk(config('filesystems.default', 'local'));

        if (! $disk->exists($path)) {
            abort(404, 'File not found on disk.');
        }

        return $disk->response($path, $file->original_name, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
        ]);
    }

    /**
     * Download/view a resume attached to an application.
     * Accessible by the candidate who applied, or the employer who posted the job.
     */
    public function downloadResume(string $applicationId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = request()->user();

        $application = Application::with(['job.employer'])->findOrFail($applicationId);

        // Authorize
        $authorized = false;
        if ($user->role === 'candidate') {
            $candidate = Candidate::where('user_id', $user->id)->first();
            $authorized = $candidate && $application->candidate_id === $candidate->id;
        } elseif ($user->role === 'employer') {
            $employer = Employer::where('user_id', $user->id)->first();
            $authorized = $employer
                && $application->job
                && $application->job->employer_id === $employer->id;
        } elseif ($user->role === 'admin') {
            $authorized = true;
        }

        if (! $authorized) {
            abort(403, 'You are not authorized to view this resume.');
        }

        $resumeUrl = $application->resume_url;
        if (! $resumeUrl) {
            abort(404, 'No resume found for this application.');
        }

        // Parse the path from the stored URL.
        // The URL might be /storage/resume/uuid.pdf or storage/resume/uuid.pdf
        $path = ltrim(parse_url($resumeUrl, PHP_URL_PATH) ?? $resumeUrl, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $disk = Storage::disk(config('filesystems.default', 'local'));

        if (! $disk->exists($path)) {
            abort(404, 'Resume file not found on disk.');
        }

        return $disk->response($path, null, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
        ]);
    }
}
