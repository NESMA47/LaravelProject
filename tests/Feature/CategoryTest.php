<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    private function seedCategories(): void
    {
        Category::create([
            'name' => 'Software Development',
            'slug' => 'software-development',
            'icon' => 'Code',
            'description' => 'Software dev roles',
            'display_order' => 1,
        ]);
        Category::create([
            'name' => 'UI/UX Design',
            'slug' => 'ui-ux-design',
            'icon' => 'Palette',
            'description' => 'Design roles',
            'display_order' => 2,
        ]);
        Category::create([
            'name' => 'Inactive Category',
            'slug' => 'inactive-category',
            'is_active' => false,
            'display_order' => 3,
        ]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
            'password_hash' => Hash::make('password'),
        ]);
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'role' => 'candidate',
            'password_hash' => Hash::make('password'),
        ]);
    }

    public function test_list_active_categories(): void
    {
        $this->seedCategories();

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.slug', 'software-development');
        $response->assertJsonPath('data.1.slug', 'ui-ux-design');
    }

    public function test_show_category_by_slug(): void
    {
        $this->seedCategories();

        $response = $this->getJson('/api/v1/categories/software-development');

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Software Development');
        $response->assertJsonPath('data.slug', 'software-development');
    }

    public function test_show_inactive_category_returns_404(): void
    {
        $this->seedCategories();

        $response = $this->getJson('/api/v1/categories/inactive-category');

        $response->assertNotFound();
    }

    public function test_admin_can_create_category(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Cybersecurity',
                'icon' => 'Shield',
                'description' => 'Security roles',
                'display_order' => 10,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'cybersecurity');
        $this->assertDatabaseHas('categories', ['name' => 'Cybersecurity']);
    }

    public function test_admin_can_create_category_with_explicit_slug(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Cybersecurity',
                'slug' => 'custom-slug',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'custom-slug');
    }

    public function test_non_admin_cannot_create_category(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/categories', [
                'name' => 'Cybersecurity',
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_category(): void
    {
        $category = Category::create([
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);
        $admin = $this->createAdmin();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/admin/categories/' . $category->id, [
                'name' => 'New Name',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
        // Slug should remain unchanged
        $response->assertJsonPath('data.slug', 'old-name');
    }

    public function test_admin_can_update_category_slug_explicitly(): void
    {
        $category = Category::create([
            'name' => 'Old Name',
            'slug' => 'old-name',
        ]);
        $admin = $this->createAdmin();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/admin/categories/' . $category->id, [
                'slug' => 'new-slug',
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.slug', 'new-slug');
    }

    public function test_admin_can_delete_category(): void
    {
        $category = Category::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
        ]);
        $admin = $this->createAdmin();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/admin/categories/' . $category->id);

        $response->assertOk();
        $this->assertDatabaseMissing('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_cannot_delete_category_with_jobs(): void
    {
        $category = Category::create([
            'name' => 'Has Jobs',
            'slug' => 'has-jobs',
        ]);
        $category->jobs_count = 5;
        $category->save();
        $admin = $this->createAdmin();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/admin/categories/' . $category->id);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Cannot delete category with existing jobs.');
    }
}
