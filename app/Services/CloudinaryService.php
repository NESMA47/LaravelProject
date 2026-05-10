<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CloudinaryService
{
    private ?Cloudinary $cloudinary = null;

    public function __construct()
    {
        if (app()->environment('testing')) {
            return;
        }

        $config = Configuration::instance([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
            'url' => [
                'secure' => true,
            ],
        ]);

        $this->cloudinary = new Cloudinary($config);
    }

    public function upload(UploadedFile $file, string $folder = 'general'): array
    {
        if (app()->environment('testing')) {
            $publicId = $folder . '/' . Str::uuid();

            return [
                'url' => 'https://res.cloudinary.com/' . config('cloudinary.cloud_name') . '/image/upload/v1/' . $publicId . '.jpg',
                'public_id' => $publicId,
            ];
        }

        $result = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => $folder,
                'resource_type' => 'image',
                'overwrite' => true,
            ]
        );

        return [
            'url' => $result['secure_url'],
            'public_id' => $result['public_id'],
        ];
    }

    public function delete(string $publicId): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $this->cloudinary->uploadApi()->destroy($publicId);
    }
}
