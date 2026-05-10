<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    private array $cloudinaryTypes = ['avatar', 'company_logo', 'company_cover'];

    public function __construct(
        private CloudinaryService $cloudinary,
    ) {
    }

    public function upload(UploadedFile $file, User $owner, string $fileType, ?string $entityType = null, ?string $entityId = null): File
    {
        // Image files go to Cloudinary; everything else stays local
        if (in_array($fileType, $this->cloudinaryTypes, true)) {
            return $this->uploadToCloudinary($file, $owner, $fileType, $entityType, $entityId);
        }

        return $this->uploadToLocal($file, $owner, $fileType, $entityType, $entityId);
    }

    private function uploadToCloudinary(UploadedFile $file, User $owner, string $fileType, ?string $entityType, ?string $entityId): File
    {
        $folder = match ($fileType) {
            'avatar' => 'avatars',
            'company_logo' => 'company_logos',
            'company_cover' => 'company_covers',
            default => 'general',
        };

        $result = $this->cloudinary->upload($file, $folder);

        return File::create([
            'owner_id' => $owner->id,
            'file_name' => $file->getClientOriginalName(),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'storage_path' => null,
            'cloudinary_public_id' => $result['public_id'],
            'url' => $result['url'],
            'file_type' => $fileType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    private function uploadToLocal(UploadedFile $file, User $owner, string $fileType, ?string $entityType, ?string $entityId): File
    {
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::uuid() . ($extension ? '.' . $extension : '');
        $disk = config('filesystems.default', 'local');
        $path = $fileType . '/' . $fileName;

        Storage::disk($disk)->putFileAs(
            $fileType,
            $file,
            $fileName
        );

        $url = Storage::disk($disk)->url($path);

        return File::create([
            'owner_id' => $owner->id,
            'file_name' => $fileName,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'storage_path' => $path,
            'cloudinary_public_id' => null,
            'url' => $url,
            'file_type' => $fileType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    public function delete(File $file): void
    {
        // Cloudinary files
        if ($file->cloudinary_public_id) {
            $this->cloudinary->delete($file->cloudinary_public_id);
            $file->forceDelete();
            return;
        }

        // Local files
        if ($file->storage_path) {
            Storage::disk(config('filesystems.default', 'local'))->delete($file->storage_path);
        }
        $file->forceDelete();
    }
}
