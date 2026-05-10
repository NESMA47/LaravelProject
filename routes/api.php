<?php

use App\Http\Controllers\Api\V1\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\JobController as AdminJobController;
use App\Http\Controllers\Api\V1\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\V1\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\PasswordController;
use App\Http\Controllers\Api\V1\Auth\VerificationController;
use App\Http\Controllers\Api\V1\Candidate\ApplicationController as CandidateApplicationController;
use App\Http\Controllers\Api\V1\Candidate\EducationController as CandidateEducationController;
use App\Http\Controllers\Api\V1\Candidate\ExperienceController as CandidateExperienceController;
use App\Http\Controllers\Api\V1\Candidate\ProfileController as CandidateProfileController;
use App\Http\Controllers\Api\V1\Candidate\ResumeController as CandidateResumeController;
use App\Http\Controllers\Api\V1\Candidate\SavedJobController;
use App\Http\Controllers\Api\V1\Candidate\SkillController as CandidateSkillController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\EmployerController;
use App\Http\Controllers\Api\V1\Employer\ApplicationController as EmployerApplicationController;
use App\Http\Controllers\Api\V1\Employer\InterviewController as EmployerInterviewController;
use App\Http\Controllers\Api\V1\Employer\JobController as EmployerJobController;
use App\Http\Controllers\Api\V1\Employer\ProfileController as EmployerProfileController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\JobController;
use App\Http\Controllers\Api\V1\NotificationController;
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

    // Saved jobs
    Route::get('saved-jobs', [SavedJobController::class, 'index']);
    Route::post('saved-jobs', [SavedJobController::class, 'store']);
    Route::delete('saved-jobs/{job_id}', [SavedJobController::class, 'destroy']);

    // Applications (US6)
    Route::get('applications', [CandidateApplicationController::class, 'index']);
    Route::get('applications/{id}', [CandidateApplicationController::class, 'show']);
    Route::post('applications', [CandidateApplicationController::class, 'store']);
    Route::patch('applications/{id}/withdraw', [CandidateApplicationController::class, 'withdraw']);
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
    Route::get('jobs/{id}', [EmployerJobController::class, 'show']);
    // --- تعديل قسم الوظائف هنا ---
    Route::get('jobs', [EmployerJobController::class, 'index']);          // عرض كل وظائف صاحب العمل
    Route::get('jobs/{id}', [EmployerJobController::class, 'show']);     // عرض وظيفة واحدة
    Route::post('jobs', [EmployerJobController::class, 'store']);         // إنشاء وظيفة جديدة

    Route::put('jobs/{id}', [EmployerJobController::class, 'update']);

    Route::patch('jobs/{id}/status', [EmployerJobController::class, 'updateStatus']); // تغيير الحالة فقط
    Route::delete('jobs/{id}', [EmployerJobController::class, 'destroy']);

    // Applications (US6)
    Route::get('applications', [EmployerApplicationController::class, 'index']);
    Route::get('jobs/{job_id}/applications', [EmployerApplicationController::class, 'jobApplications']);
    Route::get('applications/{id}', [EmployerApplicationController::class, 'show']);
    Route::patch('applications/{id}/status', [EmployerApplicationController::class, 'updateStatus']);

    // Interviews (US6)
    Route::post('applications/{id}/interviews', [EmployerInterviewController::class, 'store']);
    Route::patch('applications/{id}/interviews/{interview_id}/reschedule', [EmployerInterviewController::class, 'reschedule']);
    Route::patch('applications/{id}/interviews/{interview_id}/cancel', [EmployerInterviewController::class, 'cancel']);
    Route::patch('applications/{id}/interviews/{interview_id}/outcome', [EmployerInterviewController::class, 'outcome']);
});

// Admin-only endpoints
Route::middleware(['auth:sanctum', EnsureAdmin::class])->prefix('admin')->group(function () {
    // Dashboard (8.1)
    Route::get('dashboard', [AdminDashboardController::class, 'index']);

    // Users (8.2 - 8.4)
    Route::get('users', [AdminUserController::class, 'index']);
    Route::get('users/{id}', [AdminUserController::class, 'show']);
    Route::patch('users/{id}/status', [AdminUserController::class, 'updateStatus']);

    // Jobs (8.5 - 8.9)
    Route::get('jobs', [AdminJobController::class, 'index']);
    Route::get('jobs/{id}', [AdminJobController::class, 'show']);
    Route::post('jobs', [AdminJobController::class, 'store']);
    Route::patch('jobs/{id}/confirm', [AdminJobController::class, 'confirm']);
    Route::patch('jobs/{id}/reject', [AdminJobController::class, 'reject']);
    Route::patch('jobs/{id}/status', [AdminJobController::class, 'updateStatus']);
    Route::delete('jobs/{id}', [AdminJobController::class, 'destroy']);

    // Categories (8.13 - 8.15)
    Route::get('categories', [CategoryController::class, 'index']);
    Route::post('categories', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'store']);
    Route::put('categories/{id}', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'update']);
    Route::delete('categories/{id}', [\App\Http\Controllers\Api\V1\Admin\CategoryController::class, 'destroy']);

    // Skills (8.16 - 8.18)
    Route::get('skills', [SkillController::class, 'index']);
    Route::post('skills', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'store']);
    Route::put('skills/{id}', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'update']);
    Route::delete('skills/{id}', [\App\Http\Controllers\Api\V1\Admin\SkillController::class, 'destroy']);

    // Reviews (8.10 - 8.12)
    Route::get('reviews', [AdminReviewController::class, 'index']);
    Route::patch('reviews/{id}/approve', [AdminReviewController::class, 'approve']);
    Route::patch('reviews/{id}/reject', [AdminReviewController::class, 'reject']);

    // Reports (8.19 - 8.20)
    Route::get('reports', [AdminReportController::class, 'index']);
    Route::patch('reports/{id}', [AdminReportController::class, 'update']);
});

// Notifications (8.21 - 8.24) — any authenticated role
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('read-all', [NotificationController::class, 'markAllRead']);
    Route::patch('{id}/read', [NotificationController::class, 'markRead']);
});
