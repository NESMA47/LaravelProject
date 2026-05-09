<?php

use App\Http\Controllers\Api\V1\Admin\JobController as AdminJobController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Candidate\EducationController as CandidateEducationController;
use App\Http\Controllers\Api\V1\Candidate\ExperienceController as CandidateExperienceController;
use App\Http\Controllers\Api\V1\Candidate\ProfileController as CandidateProfileController;
use App\Http\Controllers\Api\V1\Candidate\ResumeController as CandidateResumeController;
use App\Http\Controllers\Api\V1\Candidate\SkillController as CandidateSkillController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\Employer\JobController as EmployerJobController;
use App\Http\Controllers\Api\V1\Employer\ProfileController as EmployerProfileController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureCandidate;
use App\Http\Middleware\EnsureEmployer;
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

// Candidate endpoints
Route::middleware(['auth:sanctum', EnsureCandidate::class])->prefix('candidate')->group(function () {
    Route::get('profile', [CandidateProfileController::class, 'show']);
    Route::put('profile', [CandidateProfileController::class, 'update']);

    Route::post('education', [CandidateEducationController::class, 'store']);
    Route::put('education/{id}', [CandidateEducationController::class, 'update']);
    Route::delete('education/{id}', [CandidateEducationController::class, 'destroy']);

    Route::post('experience', [CandidateExperienceController::class, 'store']);
    Route::put('experience/{id}', [CandidateExperienceController::class, 'update']);
    Route::delete('experience/{id}', [CandidateExperienceController::class, 'destroy']);

    Route::post('skills', [CandidateSkillController::class, 'sync']);

    Route::get('resumes', [CandidateResumeController::class, 'index']);
    Route::post('resumes', [CandidateResumeController::class, 'store']);
    Route::put('resumes/{id}', [CandidateResumeController::class, 'update']);
    Route::delete('resumes/{id}', [CandidateResumeController::class, 'destroy']);
    Route::patch('resumes/{id}/default', [CandidateResumeController::class, 'setDefault']);
});

// Public employer endpoints
Route::get('employers/{slug}', [EmployerProfileController::class, 'showBySlug']);
Route::get('employers/{slug}/jobs', [EmployerJobController::class, 'indexByEmployer']);

// Public job endpoint
Route::get('jobs/{id}', [EmployerJobController::class, 'show']);

// Employer endpoints
// Employer endpoints
Route::middleware(['auth:sanctum', EnsureEmployer::class])->prefix('employer')->group(function () {
    Route::get('profile', [EmployerProfileController::class, 'show']);
    Route::put('profile', [EmployerProfileController::class, 'update']);

    Route::get('jobs', [EmployerJobController::class, 'index']);         
    Route::post('jobs', [EmployerJobController::class, 'store']);        
    
    Route::get('jobs/{id}', [EmployerJobController::class, 'show']);      
    
    Route::put('jobs/{id}', [EmployerJobController::class, 'update']);    
    
    Route::patch('jobs/{id}/status', [EmployerJobController::class, 'updateStatus']); 
    Route::delete('jobs/{id}', [EmployerJobController::class, 'destroy']);
});

// Admin-only endpoints
Route::middleware(['auth:sanctum', EnsureAdmin::class])->prefix('admin')->group(function () {
    Route::get('jobs', [AdminJobController::class, 'index']);    
    Route::post('categories', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'store']);
    Route::put('categories/{id}', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'update']);
    Route::delete('categories/{id}', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'destroy']);

    Route::post('skills', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'store']);
    Route::put('skills/{id}', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'update']);
    Route::delete('skills/{id}', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'destroy']);

    Route::patch('jobs/{id}/confirm', [AdminJobController::class, 'confirm']);
    Route::patch('jobs/{id}/reject', [AdminJobController::class, 'reject']);
    Route::patch('jobs/{id}/status', [AdminJobController::class, 'updateStatus']);
});
