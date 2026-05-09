<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\Employer;
use App\Models\File;
use App\Models\Interview;
use App\Models\Job;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApplicationPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function createCandidate(array $overrides = []): User
    {
        $user = User::factory()->create(array_merge([
            'role' => 'candidate',
            'password_hash' => Hash::make('password'),
        ], $overrides));

        Candidate::create([
            'user_id' => $user->id,
            'headline' => 'Senior Developer',
            'bio' => 'Experienced dev',
            'location' => 'Cairo',
        ]);

        return $user->fresh();
    }

    private function createEmployer(string $companyName = 'Test Corp'): User
    {
        $user = User::factory()->create([
            'role' => 'employer',
            'password_hash' => Hash::make('password'),
        ]);

        Employer::create([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'slug' => \Illuminate\Support\Str::slug($companyName . '-' . time()),
        ]);

        return $user->fresh();
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'password_hash' => Hash::make('password'),
        ]);
    }

    private function tokenFor(User $user): string
    {
        // Reset auth guards to clear cached user from previous requests
        auth()->forgetGuards();

        return $user->createToken('test')->plainTextToken;
    }

    private function createActiveJob(User $employerUser, array $overrides = []): Job
    {
        $employer = $employerUser->employer;
        $defaults = [
            'employer_id' => $employer->id,
            'posted_by_user_id' => $employerUser->id,
            'title' => 'Dev',
            'slug' => 'dev-' . time() . '-' . rand(1, 9999),
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'active',
            'is_confirmed' => true,
        ];

        return Job::create(array_merge($defaults, $overrides));
    }

    private function addResume(User $candidateUser, bool $isDefault = true): Resume
    {
        $file = File::create([
            'owner_id' => $candidateUser->id,
            'file_name' => 'resume.pdf',
            'original_name' => 'My Resume.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1234,
            'storage_path' => 'resumes/test.pdf',
            'url' => 'https://example.com/resume.pdf',
            'file_type' => 'resume',
        ]);

        return Resume::create([
            'candidate_id' => $candidateUser->candidate->id,
            'title' => 'My Resume',
            'file_id' => $file->id,
            'is_default' => $isDefault,
        ]);
    }

    private function applyForJob(User $candidate, Job $job, array $overrides = []): mixed
    {
        $token = $this->tokenFor($candidate);
        $data = array_merge([
            'job_id' => $job->id,
            'cover_letter' => 'I want this job',
        ], $overrides);

        return $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/applications', $data);
    }

    // ========================
    // C-1: List my applications
    // ========================

    public function test_candidate_can_list_applications(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applyResponse->assertCreated();

        $token = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/candidate/applications');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.job_snapshot.title', 'Dev');
    }

    public function test_candidate_can_filter_applications_by_status(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $this->applyForJob($candidate, $job);

        $token = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/candidate/applications?status=applied');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
    }

    // ========================
    // C-2: Get single application detail
    // ========================

    public function test_candidate_can_view_application_detail(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/candidate/applications/' . $applicationId);

        $response->assertOk();
        $response->assertJsonPath('data.current_status', 'applied');
        $response->assertJsonPath('data.job_snapshot.title', 'Dev');
        $response->assertJsonCount(1, 'data.history');
        $response->assertJsonPath('data.history.0.stage', 'applied');
    }

    public function test_candidate_cannot_view_other_candidates_application(): void
    {
        $candidate1 = $this->createCandidate();
        $candidate2 = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate1);
        $this->addResume($candidate2);

        $applyResponse = $this->applyForJob($candidate1, $job);
        $applicationId = $applyResponse->json('data.id');

        $token2 = $this->tokenFor($candidate2);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/v1/candidate/applications/' . $applicationId);

        $response->assertStatus(404);
    }

    // ========================
    // C-3: Apply to a job
    // ========================

    public function test_candidate_can_apply_to_active_job(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $response = $this->applyForJob($candidate, $job);

        $response->assertCreated();
        $response->assertJsonPath('data.current_status', 'applied');
        $response->assertJsonPath('data.job_snapshot.title', 'Dev');
        $response->assertJsonPath('data.employer_snapshot.company_name', 'Test Corp');
        $this->assertNotNull($response->json('data.candidate_snapshot'));

        $this->assertDatabaseHas('applications', [
            'original_job_id' => $job->id,
            'candidate_id' => $candidate->candidate->id,
            'current_status' => 'applied',
        ]);

        $job->refresh();
        $this->assertEquals(1, $job->applications_count);
    }

    public function test_reapply_to_same_job_returns_409(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $this->applyForJob($candidate, $job)->assertCreated();

        $response = $this->applyForJob($candidate, $job);
        $response->assertStatus(409);
        $response->assertJsonPath('message', 'You have already applied for this job.');
    }

    public function test_apply_to_closed_job_returns_404(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer, ['status' => 'closed']);
        $this->addResume($candidate);

        $response = $this->applyForJob($candidate, $job);
        $response->assertStatus(404);
    }

    public function test_apply_without_resume_returns_422(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);

        $response = $this->applyForJob($candidate, $job);
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Upload a resume first.');
    }

    public function test_apply_with_specific_resume_id(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $resume = $this->addResume($candidate);

        $response = $this->applyForJob($candidate, $job, ['resume_id' => $resume->id]);
        $response->assertCreated();
        $this->assertEquals('https://example.com/resume.pdf', $response->json('data.resume_url'));
    }

    public function test_apply_with_invalid_resume_id_returns_422(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $otherCandidate = $this->createCandidate();
        $otherResume = $this->addResume($otherCandidate);

        $response = $this->applyForJob($candidate, $job, ['resume_id' => $otherResume->id]);
        $response->assertStatus(422);
        $response->assertJsonPath('message', 'The selected resume does not belong to you.');
    }

    // ========================
    // C-4: Withdraw application
    // ========================

    public function test_candidate_can_withdraw_application(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/candidate/applications/' . $applicationId . '/withdraw', [
                'reason' => 'Accepted another offer',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.current_status', 'withdrawn');
        $this->assertNotNull($response->json('data.withdrawn_at'));

        $this->assertDatabaseHas('applications', [
            'id' => $applicationId,
            'current_status' => 'withdrawn',
            'withdrawn_reason' => 'Accepted another offer',
        ]);

        $job->refresh();
        $this->assertEquals(0, $job->applications_count);
    }

    public function test_cannot_withdraw_hired_application(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        // Advance through pipeline: applied -> shortlisted -> interviewed -> offered -> hired
        $employerToken = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $employerToken)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);
        $this->withHeader('Authorization', 'Bearer ' . $employerToken)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'interviewed',
            ]);
        $this->withHeader('Authorization', 'Bearer ' . $employerToken)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'offered',
            ]);
        $this->withHeader('Authorization', 'Bearer ' . $employerToken)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'hired',
            ]);

        // Candidate tries to withdraw
        $candidateToken = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $candidateToken)
            ->patchJson('/api/v1/candidate/applications/' . $applicationId . '/withdraw');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'You cannot withdraw from an application that has been accepted.');
    }

    public function test_cannot_withdraw_rejected_application(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $employerToken = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $employerToken)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'rejected',
            ]);

        $candidateToken = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $candidateToken)
            ->patchJson('/api/v1/candidate/applications/' . $applicationId . '/withdraw');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'This application has already been closed by the employer.');
    }

    public function test_cannot_withdraw_already_withdrawn_application(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $candidateToken = $this->tokenFor($candidate);
        $this->withHeader('Authorization', 'Bearer ' . $candidateToken)
            ->patchJson('/api/v1/candidate/applications/' . $applicationId . '/withdraw');

        $response = $this->withHeader('Authorization', 'Bearer ' . $candidateToken)
            ->patchJson('/api/v1/candidate/applications/' . $applicationId . '/withdraw');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'You have already withdrawn this application.');
    }

    public function test_withdraw_cancels_scheduled_interviews(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        // Employer shortlists and schedules interview
        $employerToken = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $employerToken)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $interviewResponse = $this->withHeader('Authorization', 'Bearer ' . $employerToken)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'location_type' => 'video_call',
                'location_details' => 'https://zoom.us/j/123',
            ]);
        $interviewId = $interviewResponse->json('data.interview.id');

        // Candidate withdraws
        $candidateToken = $this->tokenFor($candidate);
        $this->withHeader('Authorization', 'Bearer ' . $candidateToken)
            ->patchJson('/api/v1/candidate/applications/' . $applicationId . '/withdraw');

        $this->assertDatabaseHas('interviews', [
            'id' => $interviewId,
            'status' => 'cancelled',
            'cancellation_reason' => 'candidate_cancelled',
        ]);

        $this->assertSoftDeleted('interviews', ['id' => $interviewId]);
    }

    // ========================
    // E-1: Employer global inbox
    // ========================

    public function test_employer_can_list_applications(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $this->applyForJob($candidate, $job);

        $token = $this->tokenFor($employer);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/applications');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.candidate_snapshot.name', $candidate->first_name . ' ' . $candidate->last_name);
    }

    public function test_employer_can_filter_by_status(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $this->applyForJob($candidate, $job);

        $token = $this->tokenFor($employer);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/applications?status=applied');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_employer_can_filter_by_job_id(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job1 = $this->createActiveJob($employer);
        $job2 = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $this->applyForJob($candidate, $job1);

        $token = $this->tokenFor($employer);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/applications?job_id=' . $job2->id);

        $response->assertOk();
        $response->assertJsonCount(0, 'data.data');
    }

    public function test_employer_cannot_filter_by_other_employers_job(): void
    {
        $candidate = $this->createCandidate();
        $employer1 = $this->createEmployer('Employer A');
        $employer2 = $this->createEmployer('Employer B');
        $job = $this->createActiveJob($employer2);
        $this->addResume($candidate);

        $token = $this->tokenFor($employer1);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/applications?job_id=' . $job->id);

        $response->assertStatus(403);
    }

    // ========================
    // E-2: Per-job applications with pipeline summary
    // ========================

    public function test_employer_can_list_job_applications_with_pipeline(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $this->applyForJob($candidate, $job);

        $token = $this->tokenFor($employer);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/jobs/' . $job->id . '/applications');

        $response->assertOk();
        $response->assertJsonPath('data.pipeline_summary.applied', 1);
        $response->assertJsonPath('data.pipeline_summary.hired', 0);
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_employer_cannot_access_other_employer_job_applications(): void
    {
        $candidate = $this->createCandidate();
        $employer1 = $this->createEmployer('A');
        $employer2 = $this->createEmployer('B');
        $job = $this->createActiveJob($employer2);
        $this->addResume($candidate);

        $token = $this->tokenFor($employer1);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/jobs/' . $job->id . '/applications');

        $response->assertStatus(404);
    }

    // ========================
    // E-3: Employer application detail
    // ========================

    public function test_employer_can_view_application_detail(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/applications/' . $applicationId);

        $response->assertOk();
        $response->assertJsonPath('data.current_status', 'applied');
        $response->assertJsonPath('data.candidate_snapshot.name', $candidate->first_name . ' ' . $candidate->last_name);
    }

    public function test_employer_cannot_view_other_employer_application(): void
    {
        $candidate = $this->createCandidate();
        $employer1 = $this->createEmployer('A');
        $employer2 = $this->createEmployer('B');
        $job = $this->createActiveJob($employer1);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer2);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/applications/' . $applicationId);

        $response->assertStatus(404);
    }

    // ========================
    // E-4: Update application status
    // ========================

    public function test_employer_can_update_status(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
                'notes' => 'Strong background',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.current_status', 'shortlisted');
        $response->assertJsonPath('data.history.1.stage', 'shortlisted');
    }

    public function test_invalid_status_transition_returns_409(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        // Try to go applied -> hired (invalid)
        $token = $this->tokenFor($employer);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'hired',
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Invalid transition: application cannot move from applied to hired.');
    }

    public function test_cannot_update_withdrawn_application_status(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $candidateToken = $this->tokenFor($candidate);
        $this->withHeader('Authorization', 'Bearer ' . $candidateToken)
            ->patchJson('/api/v1/candidate/applications/' . $applicationId . '/withdraw');

        $employerToken = $this->tokenFor($employer);
        $response = $this->withHeader('Authorization', 'Bearer ' . $employerToken)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_update_rejected_application_status(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'rejected',
            ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $response->assertStatus(403);
    }

    // ========================
    // E-5: Schedule interview
    // ========================

    public function test_employer_can_schedule_interview(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'duration_minutes' => 60,
                'location_type' => 'video_call',
                'location_details' => 'https://zoom.us/j/123',
                'notes' => 'Technical interview',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.interview.location_type', 'video_call');
        $response->assertJsonPath('data.application_current_status', 'interviewed');
    }

    public function test_cannot_schedule_interview_if_not_shortlisted(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'location_type' => 'video_call',
            ]);

        $response->assertStatus(403);
    }

    public function test_schedule_interview_auto_advances_status(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'location_type' => 'video_call',
            ]);

        $this->assertDatabaseHas('applications', [
            'id' => $applicationId,
            'current_status' => 'interviewed',
        ]);
    }

    // ========================
    // E-6: Reschedule interview
    // ========================

    public function test_employer_can_reschedule_interview(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $interviewResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'location_type' => 'video_call',
            ]);
        $interviewId = $interviewResponse->json('data.interview.id');

        $newTime = now()->addDays(2)->toIso8601String();
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/interviews/' . $interviewId . '/reschedule', [
                'scheduled_at' => $newTime,
            ]);

        $response->assertOk();
        $this->assertEquals(
            \Carbon\Carbon::parse($newTime)->toDateTimeString(),
            \Carbon\Carbon::parse($response->json('data.scheduled_at'))->toDateTimeString()
        );
    }

    public function test_cannot_reschedule_cancelled_interview(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $interviewResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'location_type' => 'video_call',
            ]);
        $interviewId = $interviewResponse->json('data.interview.id');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/interviews/' . $interviewId . '/cancel');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/interviews/' . $interviewId . '/reschedule', [
                'scheduled_at' => now()->addDays(3)->toIso8601String(),
            ]);

        $response->assertStatus(403);
    }

    // ========================
    // E-7: Cancel interview
    // ========================

    public function test_employer_can_cancel_interview(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $interviewResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'location_type' => 'video_call',
            ]);
        $interviewId = $interviewResponse->json('data.interview.id');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/interviews/' . $interviewId . '/cancel', [
                'cancellation_note' => 'Position filled internally',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'cancelled');
        $response->assertJsonPath('data.cancellation_reason', 'employer_cancelled');
        $this->assertNotNull($response->json('data.deleted_at'));
    }

    // ========================
    // E-8: Set interview outcome
    // ========================

    public function test_employer_can_set_interview_outcome(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $interviewResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'location_type' => 'video_call',
            ]);
        $interviewId = $interviewResponse->json('data.interview.id');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/interviews/' . $interviewId . '/outcome', [
                'status' => 'completed',
                'notes' => 'Candidate performed well',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'completed');
    }

    public function test_cannot_set_outcome_on_cancelled_interview(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $interviewResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'location_type' => 'video_call',
            ]);
        $interviewId = $interviewResponse->json('data.interview.id');

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/interviews/' . $interviewId . '/cancel');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/interviews/' . $interviewId . '/outcome', [
                'status' => 'completed',
            ]);

        $response->assertStatus(403);
    }

    // ========================
    // Job soft-delete observer
    // ========================

    public function test_job_soft_delete_marks_applications_as_job_removed(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $job->delete();

        $this->assertDatabaseHas('applications', [
            'id' => $applicationId,
            'current_status' => 'job_removed',
        ]);
        $this->assertNotNull(Application::find($applicationId)->job_removed_at);
    }

    public function test_job_soft_delete_cancels_scheduled_interviews(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        $token = $this->tokenFor($employer);
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/applications/' . $applicationId . '/status', [
                'status' => 'shortlisted',
            ]);

        $interviewResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/applications/' . $applicationId . '/interviews', [
                'scheduled_at' => now()->addDay()->toIso8601String(),
                'location_type' => 'video_call',
            ]);
        $interviewId = $interviewResponse->json('data.interview.id');

        $job->delete();

        $this->assertDatabaseHas('interviews', [
            'id' => $interviewId,
            'status' => 'cancelled',
            'cancellation_reason' => 'job_removed',
        ]);
        $this->assertSoftDeleted('interviews', ['id' => $interviewId]);
    }

    // ========================
    // Snapshot integrity
    // ========================

    public function test_application_preserves_snapshots_after_job_changes(): void
    {
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $job = $this->createActiveJob($employer, ['title' => 'Original Title']);
        $this->addResume($candidate);

        $applyResponse = $this->applyForJob($candidate, $job);
        $applicationId = $applyResponse->json('data.id');

        // Change job title
        $job->update(['title' => 'Changed Title']);

        $token = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/candidate/applications/' . $applicationId);

        $response->assertOk();
        $response->assertJsonPath('data.job_snapshot.title', 'Original Title');
    }
}
