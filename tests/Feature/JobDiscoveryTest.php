<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Category;
use App\Models\CompanyReview;
use App\Models\Employer;
use App\Models\Job;
use App\Models\JobSkill;
use App\Models\SavedJob;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class JobDiscoveryTest extends TestCase
{
    use RefreshDatabase;

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

    private function createCandidate(): User
    {
        $user = User::factory()->create([
            'role' => 'candidate',
            'password_hash' => Hash::make('password'),
        ]);

        Candidate::create([
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

    private function createActiveJob(Employer $employer, array $overrides = []): Job
    {
        $defaults = [
            'employer_id' => $employer->id,
            'posted_by_user_id' => $employer->user_id,
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

    // --- 5.1 List Jobs ---

    public function test_list_jobs_returns_only_active_non_expired(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $this->createActiveJob($employer, ['title' => 'Active Job', 'slug' => 'active-job-1']);
        $this->createActiveJob($employer, ['title' => 'Expired Job', 'slug' => 'expired-job-1', 'expires_at' => now()->subDay()]);
        Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Draft Job',
            'slug' => 'draft-job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/jobs');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'Active Job');
    }

    public function test_list_jobs_search(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $this->createActiveJob($employer, ['title' => 'Vue Frontend Dev', 'slug' => 'vue-frontend-1']);
        $this->createActiveJob($employer, ['title' => 'Backend Dev', 'slug' => 'backend-1']);

        $response = $this->getJson('/api/v1/jobs?search=vue');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'Vue Frontend Dev');
    }

    public function test_list_jobs_filter_by_category(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $category = Category::create(['name' => 'Software', 'slug' => 'software']);

        $this->createActiveJob($employer, ['title' => 'Software Job', 'category_id' => $category->id, 'slug' => 'software-job-1']);
        $this->createActiveJob($employer, ['title' => 'Other Job', 'slug' => 'other-job-1']);

        $response = $this->getJson('/api/v1/jobs?category=software');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'Software Job');
    }

    public function test_list_jobs_filter_by_type_and_workplace(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $this->createActiveJob($employer, ['title' => 'Remote Full Time', 'type' => 'full_time', 'workplace_type' => 'remote', 'slug' => 'remote-ft-1']);
        $this->createActiveJob($employer, ['title' => 'On Site Part Time', 'type' => 'part_time', 'workplace_type' => 'on_site', 'slug' => 'onsite-pt-1']);

        $response = $this->getJson('/api/v1/jobs?type=full_time&workplace=remote');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'Remote Full Time');
    }

    public function test_list_jobs_filter_by_experience(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $this->createActiveJob($employer, ['title' => 'Senior Job', 'experience_level' => 'senior', 'slug' => 'senior-job-1']);
        $this->createActiveJob($employer, ['title' => 'Junior Job', 'experience_level' => 'junior', 'slug' => 'junior-job-1']);

        $response = $this->getJson('/api/v1/jobs?experience=senior');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'Senior Job');
    }

    public function test_list_jobs_filter_by_location(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $this->createActiveJob($employer, ['title' => 'Cairo Job', 'city' => 'Cairo', 'location' => 'Downtown', 'slug' => 'cairo-job-1']);
        $this->createActiveJob($employer, ['title' => 'Alex Job', 'city' => 'Alexandria', 'location' => 'Corniche', 'slug' => 'alex-job-1']);

        $response = $this->getJson('/api/v1/jobs?location=Cairo');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'Cairo Job');
    }

    public function test_list_jobs_filter_by_salary_range(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $this->createActiveJob($employer, ['title' => 'High Salary', 'salary_min' => 30000, 'salary_max' => 50000, 'slug' => 'high-salary-1']);
        $this->createActiveJob($employer, ['title' => 'Low Salary', 'salary_min' => 5000, 'salary_max' => 10000, 'slug' => 'low-salary-1']);

        $response = $this->getJson('/api/v1/jobs?salary_min=20000');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'High Salary');
    }

    public function test_list_jobs_filter_by_skills(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $skill = Skill::create(['name' => 'Vue.js', 'slug' => 'vue-js']);

        $jobWithSkill = $this->createActiveJob($employer, ['title' => 'Vue Job', 'slug' => 'vue-job-1']);
        JobSkill::create(['job_id' => $jobWithSkill->id, 'skill_id' => $skill->id, 'is_required' => true]);

        $jobWithoutSkill = $this->createActiveJob($employer, ['title' => 'Other Job', 'slug' => 'other-job-1']);

        $response = $this->getJson('/api/v1/jobs?skills[]=' . $skill->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'Vue Job');
    }

    public function test_list_jobs_sort_by_salary_max_desc(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $this->createActiveJob($employer, ['title' => 'Low', 'salary_max' => 10000, 'slug' => 'low-1']);
        $this->createActiveJob($employer, ['title' => 'High', 'salary_max' => 50000, 'slug' => 'high-1']);

        $response = $this->getJson('/api/v1/jobs?sort=salary_max:desc');

        $response->assertOk();
        $response->assertJsonPath('data.data.0.title', 'High');
        $response->assertJsonPath('data.data.1.title', 'Low');
    }

    public function test_list_jobs_invalid_id_returns_404(): void
    {
        $response = $this->getJson('/api/v1/jobs/' . \Illuminate\Support\Str::uuid());

        $response->assertStatus(404);
    }

    public function test_list_jobs_closed_job_returns_404(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Closed Job',
            'slug' => 'closed-job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'closed',
            'is_confirmed' => true,
        ]);

        $response = $this->getJson('/api/v1/jobs/' . $job->id);

        $response->assertStatus(404);
    }

    public function test_job_detail_increments_views_count(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $job = $this->createActiveJob($employer, ['title' => 'Viewed Job', 'slug' => 'viewed-job-1', 'views_count' => 10]);

        $this->getJson('/api/v1/jobs/' . $job->id);

        $job->refresh();
        $this->assertEquals(11, $job->views_count);
    }

    public function test_job_detail_hides_salary_when_not_visible(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $job = $this->createActiveJob($employer, [
            'title' => 'Hidden Salary',
            'slug' => 'hidden-salary-1',
            'salary_min' => 10000,
            'salary_max' => 20000,
            'is_salary_visible' => false,
        ]);

        $response = $this->getJson('/api/v1/jobs/' . $job->id);

        $response->assertOk();
        $response->assertJsonPath('data.salary_min', null);
        $response->assertJsonPath('data.salary_max', null);
    }

    public function test_employer_can_view_own_draft_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $token = $this->tokenFor($user);

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Draft Job',
            'slug' => 'draft-job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/jobs/' . $job->id);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Draft Job');
    }

    public function test_non_owner_cannot_view_draft_job(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;

        $job = Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Draft Job',
            'slug' => 'draft-job-2',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/jobs/' . $job->id);

        $response->assertStatus(404);
    }

    // --- 5.3 List Employers ---

    public function test_list_employers(): void
    {
        $this->createEmployer('Company A');
        $this->createEmployer('Company B');

        $response = $this->getJson('/api/v1/employers');

        $response->assertOk();
        $response->assertJsonCount(2, 'data.data');
    }

    // --- 5.4 Employer Public Profile ---

    public function test_employer_public_profile_with_jobs_and_reviews(): void
    {
        $user = $this->createEmployer('Tech Corp');
        $employer = $user->employer;

        $this->createActiveJob($employer, ['title' => 'Active Job', 'slug' => 'active-job-1']);

        Job::create([
            'employer_id' => $employer->id,
            'posted_by_user_id' => $user->id,
            'title' => 'Draft Job',
            'slug' => 'draft-job-1',
            'description' => 'Desc',
            'requirements' => 'Req',
            'type' => 'full_time',
            'workplace_type' => 'remote',
            'experience_level' => 'mid',
            'location' => 'Cairo',
            'status' => 'draft',
        ]);

        $candidateUser1 = $this->createCandidate();
        CompanyReview::create([
            'employer_id' => $employer->id,
            'candidate_id' => $candidateUser1->candidate->id,
            'rating_overall' => 5,
            'title' => 'Great place',
            'pros' => 'Good team',
            'cons' => 'None',
            'is_approved' => true,
        ]);

        $candidateUser2 = $this->createCandidate();
        CompanyReview::create([
            'employer_id' => $employer->id,
            'candidate_id' => $candidateUser2->candidate->id,
            'rating_overall' => 3,
            'title' => 'Okay',
            'pros' => 'Salary',
            'cons' => 'Long hours',
            'is_approved' => false,
        ]);

        $response = $this->getJson('/api/v1/employers/' . $employer->slug);

        $response->assertOk();
        $response->assertJsonPath('data.company_name', 'Tech Corp');
        $response->assertJsonCount(1, 'data.active_jobs');
        $response->assertJsonPath('data.active_jobs.0.title', 'Active Job');
        $response->assertJsonCount(1, 'data.recent_reviews');
        $response->assertJsonPath('data.recent_reviews.0.title', 'Great place');
    }

    // --- 5.5 Employer Reviews ---

    public function test_employer_reviews_list(): void
    {
        $user = $this->createEmployer();
        $employer = $user->employer;
        $candidateUser1 = $this->createCandidate();

        CompanyReview::create([
            'employer_id' => $employer->id,
            'candidate_id' => $candidateUser1->candidate->id,
            'rating_overall' => 4,
            'title' => 'Review 1',
            'pros' => 'Good',
            'cons' => 'Bad',
            'is_approved' => true,
        ]);

        $candidateUser2 = $this->createCandidate();
        CompanyReview::create([
            'employer_id' => $employer->id,
            'candidate_id' => $candidateUser2->candidate->id,
            'rating_overall' => 2,
            'title' => 'Review 2',
            'pros' => 'None',
            'cons' => 'Many',
            'is_approved' => false,
        ]);

        $response = $this->getJson('/api/v1/employers/' . $employer->slug . '/reviews');

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.title', 'Review 1');
    }

    // --- 5.6 Saved Jobs ---

    public function test_candidate_can_save_job(): void
    {
        $employerUser = $this->createEmployer();
        $employer = $employerUser->employer;
        $job = $this->createActiveJob($employer, ['title' => 'Save Me', 'slug' => 'save-me-1']);

        $candidate = $this->createCandidate();
        $token = $this->tokenFor($candidate);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/saved-jobs', [
                'job_id' => $job->id,
                'notes' => 'Apply before Friday',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.job.title', 'Save Me');
        $response->assertJsonPath('data.notes', 'Apply before Friday');
        $this->assertDatabaseHas('saved_jobs', [
            'candidate_id' => $candidate->candidate->id,
            'job_id' => $job->id,
        ]);
    }

    public function test_duplicate_save_is_idempotent(): void
    {
        $employerUser = $this->createEmployer();
        $employer = $employerUser->employer;
        $job = $this->createActiveJob($employer, ['title' => 'Save Me', 'slug' => 'save-me-2']);

        $candidate = $this->createCandidate();
        $token = $this->tokenFor($candidate);

        SavedJob::create([
            'candidate_id' => $candidate->candidate->id,
            'job_id' => $job->id,
            'saved_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/saved-jobs', [
                'job_id' => $job->id,
                'notes' => 'Updated notes',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('saved_jobs', [
            'candidate_id' => $candidate->candidate->id,
            'job_id' => $job->id,
            'notes' => 'Updated notes',
        ]);
        $this->assertEquals(1, SavedJob::where('candidate_id', $candidate->candidate->id)->count());
    }

    public function test_candidate_can_list_saved_jobs(): void
    {
        $employerUser = $this->createEmployer();
        $employer = $employerUser->employer;
        $job = $this->createActiveJob($employer, ['title' => 'Saved Job', 'slug' => 'saved-job-1']);

        $candidate = $this->createCandidate();
        SavedJob::create([
            'candidate_id' => $candidate->candidate->id,
            'job_id' => $job->id,
            'saved_at' => now(),
        ]);

        $token = $this->tokenFor($candidate);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/candidate/saved-jobs');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.job.title', 'Saved Job');
    }

    public function test_candidate_can_unsave_job(): void
    {
        $employerUser = $this->createEmployer();
        $employer = $employerUser->employer;
        $job = $this->createActiveJob($employer, ['title' => 'Unsave Me', 'slug' => 'unsave-me-1']);

        $candidate = $this->createCandidate();
        SavedJob::create([
            'candidate_id' => $candidate->candidate->id,
            'job_id' => $job->id,
            'saved_at' => now(),
        ]);

        $token = $this->tokenFor($candidate);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/candidate/saved-jobs/' . $job->id);

        $response->assertOk();
        $this->assertDatabaseMissing('saved_jobs', [
            'candidate_id' => $candidate->candidate->id,
            'job_id' => $job->id,
        ]);
    }

    public function test_unsave_nonexistent_job_returns_404(): void
    {
        $candidate = $this->createCandidate();
        $token = $this->tokenFor($candidate);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/candidate/saved-jobs/' . \Illuminate\Support\Str::uuid());

        $response->assertStatus(404);
    }

    public function test_saved_job_hides_salary_when_not_visible(): void
    {
        $employerUser = $this->createEmployer();
        $employer = $employerUser->employer;
        $job = $this->createActiveJob($employer, [
            'title' => 'Hidden Salary',
            'slug' => 'hidden-salary-2',
            'salary_min' => 10000,
            'salary_max' => 20000,
            'is_salary_visible' => false,
        ]);

        $candidate = $this->createCandidate();
        SavedJob::create([
            'candidate_id' => $candidate->candidate->id,
            'job_id' => $job->id,
            'saved_at' => now(),
        ]);

        $token = $this->tokenFor($candidate);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/candidate/saved-jobs');

        $response->assertOk();
        $response->assertJsonPath('data.0.job.salary_min', null);
        $response->assertJsonPath('data.0.job.salary_max', null);
    }
}
