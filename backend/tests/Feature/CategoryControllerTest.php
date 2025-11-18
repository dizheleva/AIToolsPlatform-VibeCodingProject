<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $approvedUser;
    protected Category $category;
    protected Category $parentCategory;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->owner = User::factory()->create([
            'role' => 'owner',
            'status' => 'approved',
        ]);

        $this->approvedUser = User::factory()->create([
            'role' => 'backend',
            'status' => 'approved',
        ]);

        // Create test categories
        $this->parentCategory = Category::factory()->create([
            'name' => 'Parent Category',
            'slug' => 'parent-category',
            'is_active' => true,
        ]);

        $this->category = Category::factory()->create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_can_list_categories(): void
    {
        Category::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'is_active',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_shows_only_active_categories_by_default(): void
    {
        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $category) {
            $this->assertTrue($category['is_active']);
        }
    }

    #[Test]
    public function it_can_filter_categories_by_active_status(): void
    {
        Category::factory()->create(['is_active' => true]);
        Category::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/categories?active=false');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $category) {
            $this->assertFalse($category['is_active']);
        }
    }

    #[Test]
    public function it_can_filter_categories_by_parent_id(): void
    {
        $parent = Category::factory()->create();
        $child1 = Category::factory()->create(['parent_id' => $parent->id]);
        $child2 = Category::factory()->create(['parent_id' => $parent->id]);
        Category::factory()->create(['parent_id' => null]);

        $response = $this->getJson("/api/categories?parent_id={$parent->id}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        foreach ($data as $category) {
            $this->assertEquals($parent->id, $category['parent_id']);
        }
    }

    #[Test]
    public function it_can_filter_root_categories(): void
    {
        $root1 = Category::factory()->create(['parent_id' => null]);
        $root2 = Category::factory()->create(['parent_id' => null]);
        Category::factory()->create(['parent_id' => $this->parentCategory->id]);

        $response = $this->getJson('/api/categories?parent_id=null');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $category) {
            $this->assertNull($category['parent_id']);
        }
    }

    #[Test]
    public function it_can_include_tools_count(): void
    {
        $category = Category::factory()->create();
        AiTool::factory()->count(3)->create()->each(function ($tool) use ($category) {
            $tool->categories()->attach($category->id);
        });

        $response = $this->getJson("/api/categories?with_counts=true");

        $response->assertStatus(200);
        $data = $response->json('data');
        $foundCategory = collect($data)->firstWhere('id', $category->id);
        $this->assertNotNull($foundCategory);
        $this->assertEquals(3, $foundCategory['tools_count']);
    }

    #[Test]
    public function it_can_show_single_category(): void
    {
        $response = $this->getJson("/api/categories/{$this->category->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'parent',
                    'children',
                ],
            ]);
    }

    #[Test]
    public function it_requires_authentication_to_create_category(): void
    {
        $response = $this->postJson('/api/categories', [
            'name' => 'New Category',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_requires_owner_role_to_create_category(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->postJson('/api/categories', [
            'name' => 'New Category',
            'description' => 'Test description',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Only owners can create categories.',
            ]);
    }

    #[Test]
    public function owner_can_create_category(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson('/api/categories', [
            'name' => 'New Category',
            'description' => 'Category description',
            'icon' => '🎨',
            'color' => '#FF5733',
            'order' => 5,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'slug',
                ],
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_validates_required_fields_when_creating(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson('/api/categories', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_validates_color_format(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson('/api/categories', [
            'name' => 'Test Category',
            'color' => 'invalid-color',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    #[Test]
    public function it_validates_parent_id_exists(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson('/api/categories', [
            'name' => 'Test Category',
            'parent_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    #[Test]
    public function owner_can_update_category(): void
    {
        Auth::login($this->owner);

        $response = $this->putJson("/api/categories/{$this->category->slug}", [
            'name' => 'Updated Category Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('categories', [
            'id' => $this->category->id,
            'name' => 'Updated Category Name',
        ]);
    }

    #[Test]
    public function it_updates_slug_when_name_changes(): void
    {
        Auth::login($this->owner);

        $response = $this->putJson("/api/categories/{$this->category->slug}", [
            'name' => 'Completely New Name',
        ]);

        $response->assertStatus(200);

        $this->category->refresh();
        $this->assertEquals('completely-new-name', $this->category->slug);
    }

    #[Test]
    public function it_prevents_circular_reference_in_parent_id(): void
    {
        Auth::login($this->owner);

        $response = $this->putJson("/api/categories/{$this->category->slug}", [
            'parent_id' => $this->category->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Category cannot be its own parent.',
            ]);
    }

    #[Test]
    public function non_owner_cannot_update_category(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->putJson("/api/categories/{$this->category->slug}", [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function owner_can_delete_category(): void
    {
        $category = Category::factory()->create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
        ]);

        Auth::login($this->owner);

        $response = $this->deleteJson("/api/categories/{$category->slug}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }

    #[Test]
    public function it_prevents_deleting_category_with_tools(): void
    {
        $category = Category::factory()->create();
        $tool = AiTool::factory()->create();
        $tool->categories()->attach($category->id);

        Auth::login($this->owner);

        $response = $this->deleteJson("/api/categories/{$category->slug}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete category with associated tools.',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    #[Test]
    public function it_prevents_deleting_category_with_children(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        Auth::login($this->owner);

        $response = $this->deleteJson("/api/categories/{$parent->slug}");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete category with subcategories.',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $parent->id,
        ]);
    }

    #[Test]
    public function non_owner_cannot_delete_category(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->deleteJson("/api/categories/{$this->category->slug}");

        $response->assertStatus(403);
    }
}

