<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\Employer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function createCandidateUser(): User
    {
        $user = User::factory()->create([
            'first_name' => 'Candidate',
            'last_name' => 'User',
            'email' => 'candidate@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'candidate',
        ]);

        Candidate::create([
            'user_id' => $user->id,
            'headline' => '',
            'bio' => '',
            'location' => 'Egypt',
        ]);

        return $user->fresh();
    }

    private function createEmployerUser(): User
    {
        $user = User::factory()->create([
            'first_name' => 'Employer',
            'last_name' => 'User',
            'email' => 'employer@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'employer',
        ]);

        Employer::create([
            'user_id' => $user->id,
            'company_name' => 'Test Corp',
            'slug' => \Illuminate\Support\Str::slug($user->id . '-' . time()),
        ]);

        return $user->fresh();
    }

    public function test_candidate_can_upload_avatar(): void
    {
        $user = $this->createCandidateUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->image('avatar.jpg', 100, 100),
                'file_type' => 'avatar',
            ]);

        $response->assertCreated();
        $this->assertNotNull($user->fresh()->avatar_url);
        $this->assertNotNull($user->fresh()->avatar_file_id);
    }

    public function test_candidate_can_upload_resume(): void
    {
        $user = $this->createCandidateUser();
        $candidate = $user->candidate;
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
                'file_type' => 'resume',
                'entity_type' => 'candidate',
                'entity_id' => $candidate->id,
            ]);

        $response->assertCreated();
    }

    public function test_candidate_cannot_upload_company_logo(): void
    {
        $user = $this->createCandidateUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->image('logo.jpg', 100, 100),
                'file_type' => 'company_logo',
                'entity_type' => 'employer',
                'entity_id' => \Illuminate\Support\Str::uuid(),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file_type');
    }

    public function test_employer_can_upload_company_logo(): void
    {
        $user = $this->createEmployerUser();
        $employer = $user->employer;
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->image('logo.jpg', 100, 100),
                'file_type' => 'company_logo',
                'entity_type' => 'employer',
                'entity_id' => $employer->id,
            ]);

        $response->assertCreated();
        $this->assertNotNull($employer->fresh()->logo_url);
        $this->assertNotNull($employer->fresh()->logo_file_id);
    }

    public function test_employer_can_upload_company_cover(): void
    {
        $user = $this->createEmployerUser();
        $employer = $user->employer;
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->image('cover.jpg', 100, 100),
                'file_type' => 'company_cover',
                'entity_type' => 'employer',
                'entity_id' => $employer->id,
            ]);

        $response->assertCreated();
        $this->assertNotNull($employer->fresh()->cover_image_url);
        $this->assertNotNull($employer->fresh()->cover_image_file_id);
    }

    public function test_employer_cannot_upload_resume(): void
    {
        $user = $this->createEmployerUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
                'file_type' => 'resume',
                'entity_type' => 'candidate',
                'entity_id' => \Illuminate\Support\Str::uuid(),
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file_type');
    }

    public function test_rejects_non_image_for_avatar(): void
    {
        $user = $this->createCandidateUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
                'file_type' => 'avatar',
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_rejects_image_for_resume(): void
    {
        $user = $this->createCandidateUser();
        $candidate = $user->candidate;
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->image('resume.jpg', 100, 100),
                'file_type' => 'resume',
                'entity_type' => 'candidate',
                'entity_id' => $candidate->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
    }

    public function test_rejects_unauthorized_candidate_entity(): void
    {
        $user1 = $this->createCandidateUser();
        $user2 = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Candidate',
            'email' => 'other-candidate@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'candidate',
        ]);
        Candidate::create([
            'user_id' => $user2->id,
            'headline' => '',
            'bio' => '',
            'location' => 'Egypt',
        ]);
        $otherCandidate = $user2->fresh()->candidate;

        $token = $user1->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
                'file_type' => 'resume',
                'entity_type' => 'candidate',
                'entity_id' => $otherCandidate->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('entity_id');
    }

    public function test_rejects_unauthorized_employer_entity(): void
    {
        $user1 = $this->createEmployerUser();
        $user2 = User::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Employer',
            'email' => 'other-employer@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'employer',
        ]);
        Employer::create([
            'user_id' => $user2->id,
            'company_name' => 'Other Corp',
            'slug' => \Illuminate\Support\Str::slug($user2->id . '-' . time()),
        ]);
        $otherEmployer = $user2->fresh()->employer;

        $token = $user1->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->image('logo.jpg', 100, 100),
                'file_type' => 'company_logo',
                'entity_type' => 'employer',
                'entity_id' => $otherEmployer->id,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('entity_id');
    }

    public function test_avatar_replaces_old_file(): void
    {
        $user = $this->createCandidateUser();
        $token = $user->createToken('test')->plainTextToken;

        // First upload
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->image('avatar1.jpg', 100, 100),
                'file_type' => 'avatar',
            ]);

        $firstFileId = $user->fresh()->avatar_file_id;

        // Second upload should replace
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/files/upload', [
                'file' => UploadedFile::fake()->image('avatar2.jpg', 100, 100),
                'file_type' => 'avatar',
            ]);

        $response->assertCreated();
        $this->assertNotEquals($firstFileId, $user->fresh()->avatar_file_id);
        $this->assertDatabaseMissing('files', ['id' => $firstFileId]);
    }
}
