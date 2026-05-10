<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Candidate;
use App\Models\CompanyReview;
use App\Models\Employer;
use App\Models\Job;
use App\Models\Notification;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(string $role, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role' => $role,
            'password_hash' => Hash::make('password'),
            'is_active' => true,
        ], $overrides));
    }

    private function createCandidate(): User
    {
        $user = $this->createUser('candidate');
        Candidate::create([
            'user_id' => $user->id,
            'headline' => 'Dev',
            'bio' => 'Bio',
            'location' => 'Cairo',
        ]);
        return $user->fresh();
    }

    private function createEmployer(string $companyName = 'Test Corp'): User
    {
        $user = $this->createUser('employer');
        Employer::create([
            'user_id' => $user->id,
            'company_name' => $companyName,
            'slug' => \Illuminate\Support\Str::slug($companyName . '-' . time()),
        ]);
        return $user->fresh();
    }

    private function createAdmin(): User
    {
        return $this->createUser('admin');
    }

    private function tokenFor(User $user): string
    {
        auth()->forgetGuards();
        return $user->createToken('test')->plainTextToken;
    }

    private function createJob(User $employerUser, array $overrides = []): Job
    {
        $employer = $employerUser->employer;
        return Job::create(array_merge([
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
            'status' => 'pending_review',
            'is_confirmed' => false,
        ], $overrides));
    }

    // ========================
    // 8.1: Dashboard
    // ========================

    public function test_admin_can_view_dashboard_stats(): void
    {
        $admin = $this->createAdmin();
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();
        $this->createJob($employer);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/dashboard');

        $response->assertOk();
        $response->assertJsonPath('data.stats.total_users', 3);
        $response->assertJsonPath('data.stats.total_candidates', 1);
        $response->assertJsonPath('data.stats.total_employers', 1);
        $response->assertJsonPath('data.stats.total_admins', 1);
        $response->assertJsonPath('data.stats.total_jobs', 1);
        $response->assertJsonPath('data.stats.pending_jobs', 1);
    }

    public function test_non_admin_cannot_view_dashboard(): void
    {
        $candidate = $this->createCandidate();
        $token = $this->tokenFor($candidate);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(403);
    }

    // ========================
    // 8.2 - 8.4: Users
    // ========================

    public function test_admin_can_list_users(): void
    {
        $admin = $this->createAdmin();
        $this->createCandidate();
        $this->createEmployer();

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/users');

        $response->assertOk();
        $response->assertJsonCount(3, 'data.data');
    }

    public function test_admin_can_filter_users_by_role(): void
    {
        $admin = $this->createAdmin();
        $this->createCandidate();
        $this->createEmployer();

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/users?role=candidate');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.role', 'candidate');
    }

    public function test_admin_can_search_users(): void
    {
        $admin = $this->createAdmin();
        User::factory()->create(['first_name' => 'Ahmed', 'email' => 'ahmed@test.com', 'role' => 'candidate', 'password_hash' => Hash::make('password')]);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/users?search=Ahmed');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_admin_can_view_user_detail(): void
    {
        $admin = $this->createAdmin();
        $candidate = $this->createCandidate();

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/users/' . $candidate->id);

        $response->assertOk();
        $response->assertJsonPath('data.id', $candidate->id);
    }

    public function test_admin_can_deactivate_user(): void
    {
        $admin = $this->createAdmin();
        $candidate = $this->createCandidate();

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/users/' . $candidate->id . '/status', [
                'is_active' => false,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.is_active', false);
        $this->assertDatabaseHas('users', ['id' => $candidate->id, 'is_active' => false]);
    }

    public function test_admin_cannot_deactivate_another_admin(): void
    {
        $admin1 = $this->createAdmin();
        $admin2 = $this->createAdmin();

        $token = $this->tokenFor($admin1);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/users/' . $admin2->id . '/status', [
                'is_active' => false,
            ]);

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Cannot deactivate another admin account.');
    }

    // ========================
    // 8.5 - 8.9: Jobs
    // ========================

    public function test_admin_can_list_all_jobs(): void
    {
        $admin = $this->createAdmin();
        $employer = $this->createEmployer();
        $this->createJob($employer);
        $this->createJob($employer, ['status' => 'active', 'is_confirmed' => true]);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/jobs');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
    }

    public function test_admin_can_filter_jobs_by_status(): void
    {
        $admin = $this->createAdmin();
        $employer = $this->createEmployer();
        $this->createJob($employer);
        $this->createJob($employer, ['status' => 'active', 'is_confirmed' => true]);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/jobs?status=pending_review');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_admin_can_view_job_detail(): void
    {
        $admin = $this->createAdmin();
        $employer = $this->createEmployer();
        $job = $this->createJob($employer);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/jobs/' . $job->id);

        $response->assertOk();
        $response->assertJsonPath('data.job.id', $job->id);
    }

    public function test_admin_can_approve_job(): void
    {
        $admin = $this->createAdmin();
        $employer = $this->createEmployer();
        $job = $this->createJob($employer);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/jobs/' . $job->id . '/confirm');

        $response->assertOk();
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.is_confirmed', true);
    }

    public function test_admin_can_reject_job_with_reason(): void
    {
        $admin = $this->createAdmin();
        $employer = $this->createEmployer();
        $job = $this->createJob($employer);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/jobs/' . $job->id . '/reject', [
                'rejection_reason' => 'Inappropriate content.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'rejected');
    }

    public function test_admin_can_update_job_status(): void
    {
        $admin = $this->createAdmin();
        $employer = $this->createEmployer();
        $job = $this->createJob($employer, ['status' => 'active', 'is_confirmed' => true]);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/jobs/' . $job->id . '/status', [
                'status' => 'closed',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'closed');
    }

    public function test_admin_can_hard_delete_job(): void
    {
        $admin = $this->createAdmin();
        $employer = $this->createEmployer();
        $job = $this->createJob($employer);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/admin/jobs/' . $job->id);

        $response->assertOk();
        $this->assertDatabaseMissing('jobs', ['id' => $job->id]);
    }

    public function test_admin_can_create_job_on_behalf_of_employer(): void
    {
        $admin = $this->createAdmin();
        $employer = $this->createEmployer();

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/jobs', [
                'employer_id' => $employer->employer->id,
                'title' => 'Admin Created Job',
                'description' => 'Description',
                'requirements' => 'Requirements',
                'type' => 'full_time',
                'workplace_type' => 'remote',
                'experience_level' => 'mid',
                'location' => 'Cairo',
                'status' => 'active',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.is_confirmed', true);
    }

    // ========================
    // 8.10 - 8.12: Reviews
    // ========================

    public function test_admin_can_list_pending_reviews(): void
    {
        $admin = $this->createAdmin();
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();

        CompanyReview::create([
            'employer_id' => $employer->employer->id,
            'candidate_id' => $candidate->candidate->id,
            'rating_overall' => 5,
            'title' => 'Good',
            'is_approved' => false,
        ]);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/reviews');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_admin_can_approve_review_and_recalculate_aggregates(): void
    {
        $admin = $this->createAdmin();
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();

        $review = CompanyReview::create([
            'employer_id' => $employer->employer->id,
            'candidate_id' => $candidate->candidate->id,
            'rating_overall' => 4,
            'title' => 'Good',
            'is_approved' => false,
        ]);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/reviews/' . $review->id . '/approve');

        $response->assertOk();
        $response->assertJsonPath('data.is_approved', true);

        $employer->employer->refresh();
        $this->assertEquals(4.0, $employer->employer->average_rating);
        $this->assertEquals(1, $employer->employer->total_reviews);
    }

    public function test_admin_can_reject_review(): void
    {
        $admin = $this->createAdmin();
        $candidate = $this->createCandidate();
        $employer = $this->createEmployer();

        $review = CompanyReview::create([
            'employer_id' => $employer->employer->id,
            'candidate_id' => $candidate->candidate->id,
            'rating_overall' => 2,
            'title' => 'Bad',
            'is_approved' => false,
        ]);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/reviews/' . $review->id . '/reject');

        $response->assertOk();
        $response->assertJsonPath('data.is_approved', false);
        $this->assertNotNull($response->json('data.approved_at'));
    }

    // ========================
    // 8.13 - 8.18: Categories & Skills
    // ========================

    public function test_admin_can_list_categories(): void
    {
        $admin = $this->createAdmin();
        $token = $this->tokenFor($admin);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/categories');

        $response->assertOk();
    }

    public function test_admin_can_list_skills(): void
    {
        $admin = $this->createAdmin();
        $token = $this->tokenFor($admin);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/skills');

        $response->assertOk();
    }

    // ========================
    // 8.19 - 8.20: Reports
    // ========================

    public function test_admin_can_list_reports(): void
    {
        $admin = $this->createAdmin();
        $candidate = $this->createCandidate();

        Report::create([
            'reporter_id' => $candidate->id,
            'target_type' => 'job',
            'target_id' => \Illuminate\Support\Str::uuid(),
            'reason' => 'spam',
            'details' => 'This is spam',
            'status' => 'pending',
        ]);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/reports');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
    }

    public function test_admin_can_update_report_status(): void
    {
        $admin = $this->createAdmin();
        $candidate = $this->createCandidate();

        $report = Report::create([
            'reporter_id' => $candidate->id,
            'target_type' => 'job',
            'target_id' => \Illuminate\Support\Str::uuid(),
            'reason' => 'spam',
            'details' => 'This is spam',
            'status' => 'pending',
        ]);

        $token = $this->tokenFor($admin);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/admin/reports/' . $report->id, [
                'status' => 'resolved',
                'resolution_notes' => 'Investigated and resolved.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'resolved');
        $this->assertEquals($admin->id, $response->json('data.resolved_by_user_id'));
    }

    // ========================
    // 8.21 - 8.24: Notifications
    // ========================

    public function test_user_can_list_notifications(): void
    {
        $candidate = $this->createCandidate();

        Notification::create([
            'user_id' => $candidate->id,
            'type' => 'application_status_changed',
            'title' => 'Status updated',
            'message' => 'Your application was updated.',
        ]);

        $token = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/notifications');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.meta.unread_count', 1);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        $candidate = $this->createCandidate();

        $notification = Notification::create([
            'user_id' => $candidate->id,
            'type' => 'application_status_changed',
            'title' => 'Status updated',
            'message' => 'Your application was updated.',
            'is_read' => false,
        ]);

        $token = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/notifications/' . $notification->id . '/read');

        $response->assertOk();
        $response->assertJsonPath('data.is_read', true);
        $this->assertNotNull($response->json('data.read_at'));
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $candidate1 = $this->createCandidate();
        $candidate2 = $this->createCandidate();

        $notification = Notification::create([
            'user_id' => $candidate1->id,
            'type' => 'system_announcement',
            'title' => 'Announcement',
            'message' => 'System update.',
        ]);

        $token = $this->tokenFor($candidate2);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/notifications/' . $notification->id . '/read');

        $response->assertStatus(404);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $candidate = $this->createCandidate();

        Notification::create([
            'user_id' => $candidate->id,
            'type' => 'application_status_changed',
            'title' => 'Status updated',
            'message' => 'Your application was updated.',
            'is_read' => false,
        ]);

        Notification::create([
            'user_id' => $candidate->id,
            'type' => 'interview_scheduled',
            'title' => 'Interview scheduled',
            'message' => 'You have an interview.',
            'is_read' => false,
        ]);

        $token = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/notifications/read-all');

        $response->assertOk();

        $unreadCount = Notification::where('user_id', $candidate->id)->where('is_read', false)->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_user_can_get_unread_notification_count(): void
    {
        $candidate = $this->createCandidate();

        Notification::create([
            'user_id' => $candidate->id,
            'type' => 'application_status_changed',
            'title' => 'Status updated',
            'message' => 'Your application was updated.',
            'is_read' => false,
        ]);

        Notification::create([
            'user_id' => $candidate->id,
            'type' => 'interview_scheduled',
            'title' => 'Interview scheduled',
            'message' => 'You have an interview.',
            'is_read' => true,
            'read_at' => now(),
        ]);

        $token = $this->tokenFor($candidate);
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertOk();
        $response->assertJsonPath('data.unread_count', 1);
    }
}
