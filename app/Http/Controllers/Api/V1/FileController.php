<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileUploadRequest;
use App\Models\Employer;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;

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
}
