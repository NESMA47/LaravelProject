<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateEducation;
use App\Models\CandidateExperience;
use App\Models\CandidateSkill;
use App\Models\Category;
use App\Models\File;
use App\Models\Resume;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidateProfileTest extends TestCase
{
    use RefreshDatabase;

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

    private function createEmployer(): User
    {
        $user = User::factory()->create([
            'role' => 'employer',
            'password_hash' => Hash::make('password'),
        ]);

        \App\Models\Employer::create([
            'user_id' => $user->id,
            'company_name' => 'Test Corp',
            'slug' => \Illuminate\Support\Str::slug($user->id . '-' . time()),
        ]);

        return $user->fresh();
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_get_profile_as_candidate(): void
    {
        $user = $this->createCandidate();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/candidate/profile');

        $response->assertOk();
        $response->assertJsonPath('data.user_id', $user->id);
        $response->assertJsonPath('data.education', []);
        $response->assertJsonPath('data.experience', []);
        $response->assertJsonPath('data.skills', []);
        $response->assertJsonPath('data.resumes', []);
    }

    public function test_get_profile_as_employer_returns_403(): void
    {
        $user = $this->createEmployer();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/candidate/profile');

        $response->assertForbidden();
    }

    public function test_update_profile(): void
    {
        $user = $this->createCandidate();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/candidate/profile', [
                'headline' => 'Senior Developer',
                'bio' => 'Experienced full-stack developer',
                'location' => 'Cairo, Egypt',
                'city' => 'Cairo',
                'country' => 'EG',
                'experience_years' => 5,
                'education_level' => 'bachelor',
                'linkedin_url' => 'https://linkedin.com/in/test',
                'is_open_to_work' => true,
                'preferred_job_type' => 'full_time',
                'preferred_locations' => ['Cairo', 'Remote'],
                'expected_salary_min' => 25000,
                'expected_salary_max' => 40000,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.headline', 'Senior Developer');
        $response->assertJsonPath('data.bio', 'Experienced full-stack developer');
        $response->assertJsonPath('data.profile_completion_score', 40); // headline(10) + bio(10) + location(5) + linkedin(10) + salary(5)
    }

    public function test_update_profile_rejects_salary_max_less_than_min(): void
    {
        $user = $this->createCandidate();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/candidate/profile', [
                'expected_salary_min' => 50000,
                'expected_salary_max' => 30000,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('expected_salary_max');
    }

    public function test_add_education(): void
    {
        $user = $this->createCandidate();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/education', [
                'degree' => 'Bachelor of Science',
                'institution' => 'Cairo University',
                'field_of_study' => 'Computer Science',
                'start_year' => 2015,
                'end_year' => 2019,
                'grade' => 'Very Good',
                'is_current' => false,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.degree', 'Bachelor of Science');

        $candidate = Candidate::where('user_id', $user->id)->first();
        // location='Egypt' gives 5, education gives 15
        $this->assertEquals(20, $candidate->profile_completion_score);
    }

    public function test_update_education(): void
    {
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $education = CandidateEducation::create([
            'candidate_id' => $candidate->id,
            'degree' => 'Old Degree',
            'institution' => 'Old Uni',
            'field_of_study' => 'CS',
            'start_year' => 2015,
        ]);
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/candidate/education/' . $education->id, [
                'degree' => 'Updated Degree',
                'institution' => 'Cairo University',
                'field_of_study' => 'CS',
                'start_year' => 2015,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.degree', 'Updated Degree');
    }

    public function test_delete_education(): void
    {
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $education = CandidateEducation::create([
            'candidate_id' => $candidate->id,
            'degree' => 'Bachelor',
            'institution' => 'Cairo University',
            'field_of_study' => 'CS',
            'start_year' => 2015,
        ]);
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/candidate/education/' . $education->id);

        $response->assertOk();
        $this->assertDatabaseMissing('candidate_education', ['id' => $education->id]);

        $candidate->refresh();
        // location='Egypt' still gives 5
        $this->assertEquals(5, $candidate->profile_completion_score);
    }

    public function test_add_experience(): void
    {
        $user = $this->createCandidate();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/experience', [
                'title' => 'Software Engineer',
                'company_name' => 'Tech Corp',
                'location' => 'Cairo',
                'employment_type' => 'full_time',
                'start_date' => '2020-01-15',
                'end_date' => null,
                'is_current' => true,
                'description' => 'Developed web applications',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Software Engineer');
        $response->assertJsonPath('data.is_current', true);

        $candidate = Candidate::where('user_id', $user->id)->first();
        // location='Egypt' gives 5, experience gives 20
        $this->assertEquals(25, $candidate->profile_completion_score);
    }

    public function test_update_experience(): void
    {
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $experience = CandidateExperience::create([
            'candidate_id' => $candidate->id,
            'title' => 'Old Title',
            'company_name' => 'Old Corp',
            'employment_type' => 'full_time',
            'start_date' => '2019-01-01',
        ]);
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/candidate/experience/' . $experience->id, [
                'title' => 'Senior Developer',
                'company_name' => 'New Corp',
                'employment_type' => 'full_time',
                'start_date' => '2019-01-01',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'Senior Developer');
    }

    public function test_delete_experience(): void
    {
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $experience = CandidateExperience::create([
            'candidate_id' => $candidate->id,
            'title' => 'Developer',
            'company_name' => 'Corp',
            'employment_type' => 'full_time',
            'start_date' => '2019-01-01',
        ]);
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/candidate/experience/' . $experience->id);

        $response->assertOk();
        $this->assertDatabaseMissing('candidate_experience', ['id' => $experience->id]);
    }

    public function test_sync_skills(): void
    {
        $user = $this->createCandidate();
        $category = Category::create(['name' => 'Dev', 'slug' => 'dev']);
        $skill1 = Skill::create(['name' => 'Vue.js', 'slug' => 'vue-js', 'category_id' => $category->id]);
        $skill2 = Skill::create(['name' => 'Laravel', 'slug' => 'laravel', 'category_id' => $category->id]);
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/skills', [
                'skills' => [
                    ['skill_id' => $skill1->id, 'proficiency_level' => 'expert', 'years_experience' => 5],
                    ['skill_id' => $skill2->id, 'proficiency_level' => 'advanced'],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $candidate = Candidate::where('user_id', $user->id)->first();
        // location='Egypt' gives 5, skills give 15
        $this->assertEquals(20, $candidate->profile_completion_score);
    }

    public function test_sync_skills_replaces_existing(): void
    {
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $category = Category::create(['name' => 'Dev', 'slug' => 'dev']);
        $skill1 = Skill::create(['name' => 'Vue.js', 'slug' => 'vue-js', 'category_id' => $category->id]);
        $skill2 = Skill::create(['name' => 'Laravel', 'slug' => 'laravel', 'category_id' => $category->id]);

        CandidateSkill::create(['candidate_id' => $candidate->id, 'skill_id' => $skill1->id, 'proficiency_level' => 'beginner']);
        $token = $this->tokenFor($user);

        // Sync with only skill2
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/skills', [
                'skills' => [
                    ['skill_id' => $skill2->id, 'proficiency_level' => 'intermediate'],
                ],
            ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertDatabaseMissing('candidate_skills', ['skill_id' => $skill1->id]);
    }

    public function test_sync_skills_with_invalid_skill_id(): void
    {
        $user = $this->createCandidate();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/skills', [
                'skills' => [
                    ['skill_id' => \Illuminate\Support\Str::uuid(), 'proficiency_level' => 'expert'],
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('skills.0.skill_id');
    }

    public function test_upload_resume(): void
    {
        Storage::fake('local');
        $user = $this->createCandidate();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/resumes', [
                'title' => 'My CV',
                'file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
                'is_default' => true,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'My CV');
        $response->assertJsonPath('data.is_default', true);
    }

    public function test_first_resume_auto_sets_default(): void
    {
        Storage::fake('local');
        $user = $this->createCandidate();
        $token = $this->tokenFor($user);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/resumes', [
                'title' => 'First CV',
                'file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.is_default', true);
    }

    public function test_upload_second_resume_with_default_unsets_first(): void
    {
        Storage::fake('local');
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $token = $this->tokenFor($user);

        // Create first resume
        $file1 = File::create([
            'owner_id' => $user->id,
            'file_name' => 'r1.pdf',
            'original_name' => 'r1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'storage_path' => 'resume/r1.pdf',
            'url' => '/storage/resume/r1.pdf',
            'file_type' => 'resume',
        ]);
        $resume1 = Resume::create(['candidate_id' => $candidate->id, 'title' => 'First', 'file_id' => $file1->id, 'is_default' => true]);

        // Upload second with is_default
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/resumes', [
                'title' => 'Second CV',
                'file' => UploadedFile::fake()->create('resume2.pdf', 100, 'application/pdf'),
                'is_default' => true,
            ]);

        $response->assertCreated();

        $resume1->refresh();
        $this->assertFalse($resume1->is_default);
    }

    public function test_list_resumes(): void
    {
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $token = $this->tokenFor($user);

        $file = File::create([
            'owner_id' => $user->id,
            'file_name' => 'r1.pdf',
            'original_name' => 'r1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'storage_path' => 'resume/r1.pdf',
            'url' => '/storage/resume/r1.pdf',
            'file_type' => 'resume',
        ]);
        Resume::create(['candidate_id' => $candidate->id, 'title' => 'My CV', 'file_id' => $file->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/candidate/resumes');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_update_resume_title(): void
    {
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $token = $this->tokenFor($user);

        $file = File::create([
            'owner_id' => $user->id,
            'file_name' => 'r1.pdf',
            'original_name' => 'r1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'storage_path' => 'resume/r1.pdf',
            'url' => '/storage/resume/r1.pdf',
            'file_type' => 'resume',
        ]);
        $resume = Resume::create(['candidate_id' => $candidate->id, 'title' => 'Old Title', 'file_id' => $file->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/candidate/resumes/' . $resume->id, [
                'title' => 'New Title',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.title', 'New Title');
    }

    public function test_delete_resume(): void
    {
        Storage::fake('local');
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $token = $this->tokenFor($user);

        $file = File::create([
            'owner_id' => $user->id,
            'file_name' => 'r1.pdf',
            'original_name' => 'r1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'storage_path' => 'resume/r1.pdf',
            'url' => '/storage/resume/r1.pdf',
            'file_type' => 'resume',
        ]);
        $resume = Resume::create(['candidate_id' => $candidate->id, 'title' => 'To Delete', 'file_id' => $file->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/candidate/resumes/' . $resume->id);

        $response->assertOk();
        $this->assertDatabaseMissing('resumes', ['id' => $resume->id]);
    }

    public function test_set_default_resume(): void
    {
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $token = $this->tokenFor($user);

        $file1 = File::create([
            'owner_id' => $user->id,
            'file_name' => 'r1.pdf',
            'original_name' => 'r1.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'storage_path' => 'resume/r1.pdf',
            'url' => '/storage/resume/r1.pdf',
            'file_type' => 'resume',
        ]);
        $file2 = File::create([
            'owner_id' => $user->id,
            'file_name' => 'r2.pdf',
            'original_name' => 'r2.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'storage_path' => 'resume/r2.pdf',
            'url' => '/storage/resume/r2.pdf',
            'file_type' => 'resume',
        ]);
        $resume1 = Resume::create(['candidate_id' => $candidate->id, 'title' => 'First', 'file_id' => $file1->id, 'is_default' => true]);
        $resume2 = Resume::create(['candidate_id' => $candidate->id, 'title' => 'Second', 'file_id' => $file2->id, 'is_default' => false]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->patchJson('/api/v1/candidate/resumes/' . $resume2->id . '/default');

        $response->assertOk();
        $resume1->refresh();
        $resume2->refresh();
        $this->assertFalse($resume1->is_default);
        $this->assertTrue($resume2->is_default);
    }

    public function test_cannot_access_other_candidates_education(): void
    {
        $user1 = $this->createCandidate();
        $user2 = User::factory()->create([
            'role' => 'candidate',
            'password_hash' => Hash::make('password'),
        ]);
        $candidate2 = Candidate::create([
            'user_id' => $user2->id,
            'headline' => '',
            'bio' => '',
            'location' => 'Egypt',
        ]);
        $education = CandidateEducation::create([
            'candidate_id' => $candidate2->id,
            'degree' => 'Bachelor',
            'institution' => 'Uni',
            'field_of_study' => 'CS',
            'start_year' => 2015,
        ]);

        $token = $this->tokenFor($user1);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/candidate/education/' . $education->id, [
                'degree' => 'Hacked',
                'institution' => 'Uni',
                'field_of_study' => 'CS',
                'start_year' => 2015,
            ]);

        $response->assertNotFound();
    }

    public function test_profile_completion_score_recalculates(): void
    {
        $user = $this->createCandidate();
        $candidate = $user->candidate;
        $token = $this->tokenFor($user);

        // Start with headline and bio
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/candidate/profile', [
                'headline' => 'Dev',
                'bio' => 'Bio',
            ]);

        $candidate->refresh();
        // headline(10) + bio(10) + location(5) already from creation
        $this->assertEquals(25, $candidate->profile_completion_score);

        // Add linkedin
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/candidate/profile', [
                'linkedin_url' => 'https://linkedin.com/in/test',
            ]);

        $candidate->refresh();
        $this->assertEquals(35, $candidate->profile_completion_score); // + social(10)

        // Add education
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/education', [
                'degree' => 'Bachelor',
                'institution' => 'Cairo University',
                'field_of_study' => 'CS',
                'start_year' => 2015,
            ]);

        $candidate->refresh();
        // + education(15)
        $this->assertEquals(50, $candidate->profile_completion_score);

        // Add experience
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/candidate/experience', [
                'title' => 'Dev',
                'company_name' => 'Corp',
                'employment_type' => 'full_time',
                'start_date' => '2020-01-01',
            ]);

        $candidate->refresh();
        // + experience(20)
        $this->assertEquals(70, $candidate->profile_completion_score);

        // Add salary
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/candidate/profile', [
                'expected_salary_min' => 10000,
            ]);

        $candidate->refresh();
        // + salary(5)
        $this->assertEquals(75, $candidate->profile_completion_score);
    }
}
