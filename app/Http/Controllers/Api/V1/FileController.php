<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FileUploadRequest;
use App\Http\Resources\UserResource;
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
        $file = $this->fileService->upload(
            $request->file('file'),
            $request->user(),
            $request->input('file_type'),
            $request->input('entity_type'),
            $request->input('entity_id'),
        );

        return response()->json([
            'success' => true,
            'data' => $file,
        ], 201);
    }
}
