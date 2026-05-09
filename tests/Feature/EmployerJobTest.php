<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Employer;
use App\Models\Job;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployerJobTest extends TestCase
{
    use RefreshDatabase;

    private function createEmployer(): User
    {
        $user = User::factory()->create([
            'role' => 'employer',
            'password_hash' => Hash::make('password'),
        ]);

        Employer::create([
            'user_id' => $user->id,
            'company_name' => 'Test Corp',
            'slug' => \Illuminate\Support\Str::slug($user->id . '-' . time()),
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

    private function createCandidate(): User
    {
        $user = User::factory()->create([
            'role' => 'candidate',
            'password_hash' => Hash::make('password'),
        ]);

        \App\Models\Candidate::create([
            'user_id' => $user->id,
            'headline' => '',
            'bio' => '',
            'location' => 'Egypt',
        ]);

        return $user->fresh();
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_public_employer_profile(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $response = $this->getJson('/api/v1/employers/' . $employer->slug);

        $response->assertOk();
        $response->assertJsonPath('data.company_name', 'Test Corp');
        $response->assertJsonPath('data.slug', $employer->slug);
    }

    public function test_public_employer_jobs(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Dev',
            'slug' => 'dev-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'active',
            'is_confirmed' => true,
        ]);

        Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Hidden',
            'slug' => 'hidden-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
            'is_confirmed' => false,
        ]);

        $response = $this->getJson('/api/v1/employers/' . $employer->slug . '/jobs');

        $response->assertOk();
        $this->assertStringContainsString('"title":"Dev"', $response->getContent());
        $this->assertStringNotContainsString('"title":"Hidden"', $response->getContent());
    }

    public function test_public_job_detail(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Senior Dev',
            'slug' => 'senior-dev-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'active',
            'is_confirmed' => true,
        ]);

        $response = $this->getJson('/api/v1/jobs/' . $job->id);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Senior Dev');
    }

    public function test_employer_can_view_own_profile(): void
    {
        $user = $this->createEmployer();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/profile');

        $response->assertOk();
        $response->assertJsonPath('data.company_name', 'Test Corp');
    }

    public function test_employer_can_update_profile(): void
    {
        $user = $this->createEmployer();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/employer/profile', [
                'company_name' => 'Updated Corp',
                'industry' => 'Tech',
                'website' => 'https://example.com',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.company_name', 'Updated Corp');
        $response->assertJsonPath('data.industry', 'Tech');
    }

    public function test_employer_can_create_job(): void
    {
        $user = $this->createEmployer();
        $token = $this->tokenFor($user);
        $category = Category::create(['name' => 'Dev', 'slug' => 'dev']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/jobs', [
                'title' => 'Frontend Developer',
                'category_id' => $category->id,
                'description' => 'Build UI components',
                'requirements' => '- Vue.js\n- CSS',
                'responsibilities' => 'Lead frontend',
                'benefits' => 'Health insurance',
                'type' => 'full_time',
                'workplace_type' => 'hybrid',
                'experience_level' => 'senior',
                'career_level' => 'Senior',
                'education_level' => 'bachelor',
                'salary_min' => 25000,
                'salary_max' => 40000,
                'location' => 'Cairo',
                'city' => 'Cairo',
                'vacancies' => 2,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.is_confirmed', false);
        $this->assertNotNull($response->json('data.slug'));
    }

    public function test_employer_can_list_own_jobs(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Dev',
            'slug' => 'dev-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/jobs');

        $response->assertOk();
        $this->assertStringContainsString('"title":"Dev"', $response->getContent());
    }

    public function test_employer_can_update_draft_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Old Title',
            'slug' => 'old-title-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/employer/jobs/' . $job->id, [
                'title' => 'New Title',
                'description' => 'New Desc',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New Title');
    }

    public function test_employer_cannot_update_active_job_title(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Active Job',
            'slug' => 'active-job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'active',
            'is_confirmed' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/employer/jobs/' . $job->id, [
                'title' => 'Hacked Title',
            ]);

        // Title is silently ignored for active jobs
        $response->assertOk();
        $this->assertDatabaseHas('jobs', ['id' => $job->id, 'title' => 'Active Job']);
    }

    public function test_employer_can_update_active_job_salary(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Active Job',
            'slug' => 'active-job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'active',
            'is_confirmed' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/employer/jobs/' . $job->id, [
                'salary_min' => 30000,
                'salary_max' => 50000,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.salary_min', 30000);
    }

    public function test_employer_cannot_toggle_unconfirmed_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Unconfirmed',
            'slug' => 'unconfirmed-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'pending_review',
            'is_confirmed' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/jobs/' . $job->id . '/status', [
                'status' => 'active',
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Job must be approved by admin before status can be changed.');
    }

    public function test_employer_can_submit_draft_to_pending_review(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Draft',
            'slug' => 'draft-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/jobs/' . $job->id . '/status', [
                'status' => 'pending_review',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('jobs', ['id' => $job->id, 'status' => 'pending_review']);
    }

    public function test_employer_can_toggle_confirmed_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Confirmed',
            'slug' => 'confirmed-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'active',
            'is_confirmed' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/employer/jobs/' . $job->id . '/status', [
                'status' => 'paused',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('jobs', ['id' => $job->id, 'status' => 'paused']);
    }

    public function test_employer_can_delete_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'To Delete',
            'slug' => 'to-delete-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/employer/jobs/' . $job->id);

        $response->assertOk();
        $this->assertDatabaseMissing('jobs', ['id' => $job->id, 'deleted_at' => null]);
    }

    public function test_admin_can_confirm_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $admin = $this->createAdmin();
        $adminToken = $this->tokenFor($admin);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Pending',
            'slug' => 'pending-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'pending_review',
            'is_confirmed' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->patchJson('/api/v1/admin/jobs/' . $job->id . '/confirm');

        $response->assertOk();
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'status' => 'active',
            'is_confirmed' => true,
        ]);
    }

    public function test_admin_can_reject_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $admin = $this->createAdmin();
        $adminToken = $this->tokenFor($admin);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Bad Job',
            'slug' => 'bad-job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'pending_review',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->patchJson('/api/v1/admin/jobs/' . $job->id . '/reject', [
                'rejection_reason' => 'Insufficient details provided.',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient details provided.',
        ]);
    }

    public function test_admin_can_update_any_job_status(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $admin = $this->createAdmin();
        $adminToken = $this->tokenFor($admin);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Job',
            'slug' => 'job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'pending_review',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->patchJson('/api/v1/admin/jobs/' . $job->id . '/status', [
                'status' => 'active',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('jobs', [
            'id' => $job->id,
            'status' => 'active',
            'is_confirmed' => true,
        ]);
    }

    public function test_candidate_cannot_access_employer_endpoints(): void
    {
        $candidate = $this->createCandidate();
        $token = $this->tokenFor($candidate);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/employer/profile');

        $response->assertForbidden();
    }

    public function test_employer_cannot_access_other_employer_job(): void
    {
        $user1 = $this->createEmployer();
        $user2 = User::factory()->create([
            'role' => 'employer',
            'password_hash' => Hash::make('password'),
        ]);
        $employer2 = Employer::create([
            'user_id' => $user2->id,
            'company_name' => 'Other Corp',
            'slug' => \Illuminate\Support\Str::slug($user2->id . '-' . time()),
        ]);

        $job = Job::create([
            'employer_id' => $employer2->id,
            'posted_by_user_id' => $user2->id,
            'title' => 'Other Job',
            'slug' => 'other-job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
        ]);

        $token = $this->tokenFor($user1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/employer/jobs/' . $job->id, [
                'title' => 'Hacked',
            ]);

        $response->assertForbidden();
    }

    public function test_job_requires_salary_max_gte_min(): void
    {
        $user = $this->createEmployer();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/jobs', [
                'title' => 'Dev',
                'description' => 'Desc',
                'requirements' => 'Req',
                'type' => 'full_time',
                'workplace_type' => 'remote',
                'experience_level' => 'mid',
                'location' => 'Cairo',
                'salary_min' => 50000,
                'salary_max' => 30000,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('salary_max');
    }

    public function test_job_slug_auto_generation_with_collision(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $response1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/jobs', [
                'title' => 'Frontend Dev',
                'description' => 'Desc',
                'requirements' => 'Req',
                'type' => 'full_time',
                'workplace_type' => 'remote',
                'experience_level' => 'mid',
                'location' => 'Cairo',
            ]);

        $response1->assertCreated();
        $slug1 = $response1->json('data.slug');
        $this->assertNotNull($slug1);

        $response2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/jobs', [
                'title' => 'Frontend Dev',
                'description' => 'Desc 2',
                'requirements' => 'Req 2',
                'type' => 'full_time',
                'workplace_type' => 'remote',
                'experience_level' => 'mid',
                'location' => 'Cairo',
            ]);

        $response2->assertCreated();
        $slug2 = $response2->json('data.slug');
        $this->assertNotNull($slug2);

        $this->assertNotEquals($slug1, $slug2);
    }

    public function test_job_creation_with_skills(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);
        $category = Category::create(['name' => 'Dev', 'slug' => 'dev']);
        $skill = Skill::create(['name' => 'Vue.js', 'slug' => 'vue-js', 'category_id' => $category->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/employer/jobs', [
                'title' => 'Frontend Dev',
                'category_id' => $category->id,
                'description' => 'Desc',
                'requirements' => 'Req',
                'type' => 'full_time',
                'workplace_type' => 'remote',
                'experience_level' => 'mid',
                'location' => 'Cairo',
                'skills' => [
                    ['skill_id' => $skill->id, 'is_required' => true, 'min_proficiency' => 'expert'],
                ],
            ]);

        $response->assertCreated();
        $response->assertJsonCount(1, 'data.skills');
        $response->assertJsonPath('data.skills.0.name', 'Vue.js');
    }

    public function test_employer_cannot_edit_closed_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Closed',
            'slug' => 'closed-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'closed',
            'is_confirmed' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/employer/jobs/' . $job->id, [
                'description' => 'New desc',
            ]);

        $response->assertStatus(409);
    }

    public function test_non_admin_cannot_confirm_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $employerToken = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Job',
            'slug' => 'job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'pending_review',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $employerToken)
            ->patchJson('/api/v1/admin/jobs/' . $job->id . '/confirm');

        $response->assertForbidden();
    }
}
