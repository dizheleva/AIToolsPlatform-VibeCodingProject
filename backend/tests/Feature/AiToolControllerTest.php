<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiToolControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $approvedUser;
    protected User $pendingUser;
    protected Category $category;

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

        $this->pendingUser = User::factory()->create([
            'role' => 'frontend',
            'status' => 'pending',
        ]);

        // Create test category
        $this->category = Category::factory()->create([
            'name' => 'Test Category',
            'slug' => 'test-category',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_can_list_ai_tools(): void
    {
        // Create some tools
        AiTool::factory()->count(3)->create([
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/tools');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'url',
                        'pricing_model',
                        'status',
                    ],
                ],
                'pagination',
            ]);
    }

    #[Test]
    public function it_can_filter_tools_by_status(): void
    {
        AiTool::factory()->create(['status' => 'active']);
        AiTool::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/tools?status=active');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('active', $response->json('data.0.status'));
    }

    #[Test]
    public function it_can_filter_tools_by_category(): void
    {
        $tool = AiTool::factory()->create(['status' => 'active']);
        $tool->categories()->attach($this->category->id);

        $response = $this->getJson("/api/tools?category_id={$this->category->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function it_can_search_tools(): void
    {
        AiTool::factory()->create([
            'name' => 'ChatGPT',
            'status' => 'active',
        ]);
        AiTool::factory()->create([
            'name' => 'GitHub Copilot',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/tools?search=ChatGPT');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertStringContainsString('ChatGPT', $response->json('data.0.name'));
    }

    #[Test]
    public function it_can_show_single_tool(): void
    {
        $tool = AiTool::factory()->create([
            'status' => 'active',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->getJson("/api/tools/{$tool->slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'url',
                ],
            ]);
    }

    #[Test]
    public function it_requires_authentication_to_create_tool(): void
    {
        $response = $this->postJson('/api/tools', [
            'name' => 'Test Tool',
            'url' => 'https://example.com',
            'pricing_model' => 'free',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_requires_approved_status_to_create_tool(): void
    {
        Auth::login($this->pendingUser);

        $response = $this->postJson('/api/tools', [
            'name' => 'Test Tool',
            'url' => 'https://example.com',
            'pricing_model' => 'free',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account must be approved to create AI tools.',
            ]);
    }

    #[Test]
    public function approved_user_can_create_tool(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->postJson('/api/tools', [
            'name' => 'New AI Tool',
            'description' => 'A test tool',
            'url' => 'https://example.com',
            'pricing_model' => 'free',
            'category_ids' => [$this->category->id],
            'roles' => ['backend', 'frontend'],
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

        $this->assertDatabaseHas('ai_tools', [
            'name' => 'New AI Tool',
            'created_by' => $this->approvedUser->id,
            'status' => 'pending_review', // Non-owners create with pending_review status
        ]);
    }

    #[Test]
    public function owner_can_create_tool_with_active_status(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson('/api/tools', [
            'name' => 'Owner Tool',
            'url' => 'https://example.com',
            'pricing_model' => 'free',
            'status' => 'active',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('ai_tools', [
            'name' => 'Owner Tool',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_validates_required_fields_when_creating(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->postJson('/api/tools', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'url', 'pricing_model']);
    }

    #[Test]
    public function creator_can_update_their_tool(): void
    {
        $tool = AiTool::factory()->create([
            'created_by' => $this->approvedUser->id,
            'status' => 'active',
        ]);

        Auth::login($this->approvedUser);

        $response = $this->putJson("/api/tools/{$tool->slug}", [
            'name' => 'Updated Tool Name',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('ai_tools', [
            'id' => $tool->id,
            'name' => 'Updated Tool Name',
        ]);
    }

    #[Test]
    public function owner_can_update_any_tool(): void
    {
        $tool = AiTool::factory()->create([
            'created_by' => $this->approvedUser->id,
        ]);

        Auth::login($this->owner);

        $response = $this->putJson("/api/tools/{$tool->slug}", [
            'name' => 'Owner Updated Tool',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('ai_tools', [
            'id' => $tool->id,
            'name' => 'Owner Updated Tool',
        ]);
    }

    #[Test]
    public function user_cannot_update_other_users_tool(): void
    {
        $tool = AiTool::factory()->create([
            'created_by' => $this->owner->id,
        ]);

        $otherUser = User::factory()->create([
            'role' => 'backend',
            'status' => 'approved',
        ]);

        Auth::login($otherUser);

        $response = $this->putJson("/api/tools/{$tool->slug}", [
            'name' => 'Unauthorized Update',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function creator_can_delete_their_tool(): void
    {
        $tool = AiTool::factory()->create([
            'created_by' => $this->approvedUser->id,
        ]);

        Auth::login($this->approvedUser);

        $response = $this->deleteJson("/api/tools/{$tool->slug}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('ai_tools', [
            'id' => $tool->id,
        ]);
    }

    #[Test]
    public function authenticated_user_can_like_tool(): void
    {
        $tool = AiTool::factory()->create(['status' => 'active']);

        Auth::login($this->approvedUser);

        $response = $this->postJson("/api/tools/{$tool->slug}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'liked' => true,
                ],
            ]);

        $this->assertDatabaseHas('ai_tool_likes', [
            'ai_tool_id' => $tool->id,
            'user_id' => $this->approvedUser->id,
        ]);
    }

    #[Test]
    public function user_can_unlike_tool(): void
    {
        $tool = AiTool::factory()->create(['status' => 'active']);
        $tool->likedBy()->attach($this->approvedUser->id);
        $tool->increment('likes_count');

        Auth::login($this->approvedUser);

        $response = $this->postJson("/api/tools/{$tool->slug}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'liked' => false,
                ],
            ]);

        $this->assertDatabaseMissing('ai_tool_likes', [
            'ai_tool_id' => $tool->id,
            'user_id' => $this->approvedUser->id,
        ]);
    }

    #[Test]
    public function it_respects_rate_limiting_on_like(): void
    {
        $tool = AiTool::factory()->create(['status' => 'active']);

        Auth::login($this->approvedUser);

        // Make 10 requests (the limit)
        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/tools/{$tool->slug}/like");
        }

        // 11th request should be rate limited
        $response = $this->postJson("/api/tools/{$tool->slug}/like");

        $response->assertStatus(429);
    }
}

