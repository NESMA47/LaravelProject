<?php

use App\Http\Controllers\Api\V1\Admin\JobController as AdminJobController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Candidate\EducationController as CandidateEducationController;
use App\Http\Controllers\Api\V1\Candidate\ExperienceController as CandidateExperienceController;
use App\Http\Controllers\Api\V1\Candidate\ProfileController as CandidateProfileController;
use App\Http\Controllers\Api\V1\Candidate\ResumeController as CandidateResumeController;
use App\Http\Controllers\Api\V1\Candidate\SavedJobController;
use App\Http\Controllers\Api\V1\Candidate\SkillController as CandidateSkillController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\EmployerController;
use App\Http\Controllers\Api\V1\Employer\JobController as EmployerJobController;
use App\Http\Controllers\Api\V1\Employer\ProfileController as EmployerProfileController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\SkillController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureCandidate;
use App\Http\Middleware\EnsureEmployer;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController;
use App\Http\Controllers\Api\V1\NotificationController;

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

    // Saved jobs
    Route::get('saved-jobs', [SavedJobController::class, 'index']);
    Route::post('saved-jobs', [SavedJobController::class, 'store']);
    Route::delete('saved-jobs/{job_id}', [SavedJobController::class, 'destroy']);
});

// Public job discovery endpoints
Route::get('jobs', [JobController::class, 'index']);
Route::get('jobs/{id}', [JobController::class, 'show']);

// Public employer endpoints
Route::get('employers', [EmployerController::class, 'index']);
Route::get('employers/{slug}', [EmployerController::class, 'show']);
Route::get('employers/{slug}/reviews', [EmployerController::class, 'reviews']);
Route::get('employers/{slug}/jobs', [EmployerController::class, 'jobs']);

// Employer endpoints
// Employer endpoints
Route::middleware(['auth:sanctum', EnsureEmployer::class])->prefix('employer')->group(function () {
    Route::get('profile', [EmployerProfileController::class, 'show']);
    Route::put('profile', [EmployerProfileController::class, 'update']);

    // --- تعديل قسم الوظائف هنا ---
    Route::get('jobs', [EmployerJobController::class, 'index']);          // عرض كل وظائف صاحب العمل
    Route::post('jobs', [EmployerJobController::class, 'store']);         // إنشاء وظيفة جديدة

    Route::put('jobs/{id}', [EmployerJobController::class, 'update']);

    Route::patch('jobs/{id}/status', [EmployerJobController::class, 'updateStatus']); // تغيير الحالة فقط
    Route::delete('jobs/{id}', [EmployerJobController::class, 'destroy']);
});

// Admin-only endpoints
Route::middleware(['auth:sanctum', EnsureAdmin::class])->prefix('admin')->group(function () {
    Route::post('categories', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'store']);
    Route::put('categories/{id}', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'update']);
    Route::delete('categories/{id}', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'destroy']);

    Route::post('skills', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'store']);
    Route::put('skills/{id}', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'update']);
    Route::delete('skills/{id}', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'destroy']);

    Route::patch('jobs/{id}/confirm', [AdminJobController::class, 'confirm']);
    Route::patch('jobs/{id}/reject', [AdminJobController::class, 'reject']);
    Route::patch('jobs/{id}/status', [AdminJobController::class, 'updateStatus']);

// ── US8 Admin Extra Routes ──────────────────────────────────────────
Route::middleware(['auth:sanctum', EnsureAdmin::class])->prefix('admin')->group(function () {
    Route::get('dashboard',              [AdminDashboardController::class, 'dashboard']);
    Route::get('users',                  [AdminDashboardController::class, 'listUsers']);
    Route::get('users/{id}',             [AdminDashboardController::class, 'showUser']);
    Route::patch('users/{id}/status',    [AdminDashboardController::class, 'updateUserStatus']);
    Route::get('jobs',                   [AdminDashboardController::class, 'listJobs']);
    Route::get('jobs/{id}',              [AdminDashboardController::class, 'showJob']);
    Route::delete('jobs/{id}',           [AdminDashboardController::class, 'deleteJob']);
    Route::get('reviews',                [AdminReviewController::class, 'index']);
    Route::patch('reviews/{id}/approve', [AdminReviewController::class, 'approve']);
    Route::patch('reviews/{id}/reject',  [AdminReviewController::class, 'reject']);
    Route::get('categories',             [\App\Http\Controllers\Api\V1\CategoryController::class, 'index']);
    Route::get('skills',                 [\App\Http\Controllers\Api\V1\SkillController::class, 'index']);
    Route::get('reports',                [AdminReportController::class, 'index']);
    Route::patch('reports/{id}',         [AdminReportController::class, 'update']);
});

// ── US8 Notification Routes ─────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications',                [NotificationController::class, 'index']);
    Route::get('notifications/unread-count',   [NotificationController::class, 'unreadCount']);
    Route::patch('notifications/read-all',     [NotificationController::class, 'markAllRead']);
    Route::patch('notifications/{id}/read',    [NotificationController::class, 'markRead']);
});

});

// ── US8 Notification Routes ─────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications',                [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
    Route::get('notifications/unread-count',   [\App\Http\Controllers\Api\V1\NotificationController::class, 'unreadCount']);
    Route::patch('notifications/read-all',     [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllRead']);
    Route::patch('notifications/{id}/read',    [\App\Http\Controllers\Api\V1\NotificationController::class, 'markRead']);
});

// ── US8 Admin Extra Routes ──────────────────────────────────────────
Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureAdmin::class])->prefix('admin')->group(function () {
    Route::get('dashboard',              [\App\Http\Controllers\Api\V1\Admin\AdminDashboardController::class, 'dashboard']);
    Route::get('users',                  [\App\Http\Controllers\Api\V1\Admin\AdminDashboardController::class, 'listUsers']);
    Route::get('users/{id}',             [\App\Http\Controllers\Api\V1\Admin\AdminDashboardController::class, 'showUser']);
    Route::patch('users/{id}/status',    [\App\Http\Controllers\Api\V1\Admin\AdminDashboardController::class, 'updateUserStatus']);
    Route::get('jobs',                   [\App\Http\Controllers\Api\V1\Admin\AdminDashboardController::class, 'listJobs']);
    Route::get('jobs/{id}',              [\App\Http\Controllers\Api\V1\Admin\AdminDashboardController::class, 'showJob']);
    Route::delete('jobs/{id}',           [\App\Http\Controllers\Api\V1\Admin\AdminDashboardController::class, 'deleteJob']);
    Route::get('reviews',                [\App\Http\Controllers\Api\V1\Admin\AdminReviewController::class, 'index']);
    Route::patch('reviews/{id}/approve', [\App\Http\Controllers\Api\V1\Admin\AdminReviewController::class, 'approve']);
    Route::patch('reviews/{id}/reject',  [\App\Http\Controllers\Api\V1\Admin\AdminReviewController::class, 'reject']);
    Route::get('categories',             [\App\Http\Controllers\Api\V1\CategoryController::class, 'index']);
    Route::get('skills',                 [\App\Http\Controllers\Api\V1\SkillController::class, 'index']);
    Route::get('reports',                [\App\Http\Controllers\Api\V1\Admin\AdminReportController::class, 'index']);
    Route::patch('reports/{id}',         [\App\Http\Controllers\Api\V1\Admin\AdminReportController::class, 'update']);
});
