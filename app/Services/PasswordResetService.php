<?php

namespace App\Services;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function createToken(string $email): void
    {
        $user = User::where('email', strtolower($email))->first();

        if (! $user) {
            return;
        }

        PasswordReset::where('user_id', $user->id)->delete();

        $rawToken = Str::random(64);

        PasswordReset::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addHour(),
        ]);

        // TODO: Queue email with $rawToken
    }

    public function reset(string $email, string $token, string $newPassword): bool
    {
        $user = User::where('email', strtolower($email))->first();

        if (! $user) {
            return false;
        }

        $reset = PasswordReset::where('user_id', $user->id)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $reset || ! hash_equals($reset->token_hash, hash('sha256', $token))) {
            return false;
        }

        $user->update([
            'password_hash' => Hash::make($newPassword),
        ]);

        $reset->update(['used_at' => now()]);

        return true;
    }
}
