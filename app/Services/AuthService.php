<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Employer;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthService
{
    public function register(array $data): array
    {
        $user = User::create([
            'email' => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'role' => $data['role'],
        ]);

        if ($data['role'] === 'candidate') {
            Candidate::create([
                'user_id' => $user->id,
                'headline' => '',
                'bio' => '',
                'location' => 'Egypt',
            ]);
        }

        if ($data['role'] === 'employer') {
            Employer::create([
                'user_id' => $user->id,
                'company_name' => '',
                'slug' => Str::slug($user->id . '-' . time()),
            ]);
        }

        $token = $user->createToken('api-auth')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->fresh()->load(['candidate', 'employer']),
        ];
    }

    public function login(string $email, string $password): ?array
    {
        $user = User::where('email', strtolower($email))->first();

        if (! $user || ! Hash::check($password, $user->password_hash)) {
            return null;
        }

        if (! $user->is_active) {
            return ['deactivated' => true];
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('api-auth')->plainTextToken;

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load(['candidate', 'employer']),
        ];
    }

    public function updateProfile(User $user, array $data): User
    {
        if (isset($data['first_name'])) {
            $user->first_name = $data['first_name'];
        }

        if (isset($data['last_name'])) {
            $user->last_name = $data['last_name'];
        }

        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
        }

        $user->save();

        return $user->fresh();
    }
}
