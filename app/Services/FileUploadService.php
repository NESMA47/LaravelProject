<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileUploadService
{
    public function upload(UploadedFile $file, User $owner, string $fileType, ?string $entityType = null, ?string $entityId = null): File
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
            'url' => $url,
            'file_type' => $fileType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ]);
    }

    public function delete(File $file): void
    {
        Storage::disk(config('filesystems.default', 'local'))->delete($file->storage_path);
        $file->delete();
    }
}
