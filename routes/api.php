<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [PasswordController::class, 'forgot']);
    Route::post('reset-password', [PasswordController::class, 'reset']);
    Route::post('verify-email', [VerificationController::class, 'verify']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'updateMe']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('resend-verification', [VerificationController::class, 'resend']);
    });
});

// Public taxonomy endpoints
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{slug}', [CategoryController::class, 'show']);
Route::get('skills', [SkillController::class, 'index']);
Route::get('skills/autocomplete', [SkillController::class, 'autocomplete']);

// Authenticated endpoints
Route::middleware('auth:sanctum')->group(function () {
    Route::post('files/upload', [FileController::class, 'upload']);

    // All authenticated users can create skills
    Route::post('skills', [SkillController::class, 'store']);
});

// Admin-only endpoints
Route::middleware(['auth:sanctum', EnsureAdmin::class])->prefix('admin')->group(function () {
    Route::post('categories', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'store']);
    Route::put('categories/{id}', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'update']);
    Route::delete('categories/{id}', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'destroy']);

    Route::post('skills', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'store']);
    Route::put('skills/{id}', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'update']);
    Route::delete('skills/{id}', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'destroy']);
});
