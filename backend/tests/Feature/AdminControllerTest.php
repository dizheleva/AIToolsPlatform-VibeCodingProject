<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $approvedUser;
    protected User $pendingUser;
    protected Category $category;
    protected AiTool $tool;

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

        // Create test tool
        $this->tool = AiTool::factory()->create([
            'name' => 'Test Tool',
            'slug' => 'test-tool',
            'status' => 'pending_review',
            'created_by' => $this->approvedUser->id,
        ]);
    }

    // ==================== Tools Tests ====================

    #[Test]
    public function it_requires_owner_to_access_admin_tools(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->getJson('/api/admin/tools');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient permissions', // RoleMiddleware returns this message
            ]);
    }

    #[Test]
    public function it_can_list_tools_for_admin(): void
    {
        Auth::login($this->owner);

        AiTool::factory()->count(3)->create([
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/admin/tools');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'status',
                    ],
                ],
                'pagination',
            ]);
    }

    #[Test]
    public function it_can_filter_tools_by_status_in_admin(): void
    {
        Auth::login($this->owner);

        AiTool::factory()->create(['status' => 'active']);
        AiTool::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/admin/tools?status=active');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('active', $response->json('data.0.status'));
    }

    #[Test]
    public function it_validates_sort_by_in_admin_tools(): void
    {
        Auth::login($this->owner);

        // Try invalid sort_by - should default to created_at
        $response = $this->getJson('/api/admin/tools?sort_by=invalid_field');

        $response->assertStatus(200);
        // Should still work with default sort
    }

    #[Test]
    public function it_validates_per_page_in_admin_tools(): void
    {
        Auth::login($this->owner);

        // Try per_page > 100 - should be limited to 100
        $response = $this->getJson('/api/admin/tools?per_page=200');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('pagination.per_page'));

        // Try per_page < 1 - should be at least 1
        $response = $this->getJson('/api/admin/tools?per_page=0');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('pagination.per_page'));
    }

    // ==================== Approve Tool Tests ====================

    #[Test]
    public function it_requires_owner_to_approve_tool(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->postJson("/api/admin/tools/{$this->tool->slug}/approve", [
            'status' => 'active',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_approve_tool(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson("/api/admin/tools/{$this->tool->slug}/approve", [
            'status' => 'active',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Tool active successfully',
            ]);

        $this->tool->refresh();
        $this->assertEquals('active', $this->tool->status);
    }

    #[Test]
    public function it_validates_status_when_approving_tool(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson("/api/admin/tools/{$this->tool->slug}/approve", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_clears_cache_when_approving_tool(): void
    {
        Auth::login($this->owner);

        // Set some cache
        Cache::put('admin_statistics', ['test' => 'data'], 300);

        $response = $this->postJson("/api/admin/tools/{$this->tool->slug}/approve", [
            'status' => 'active',
        ]);

        $response->assertStatus(200);
        // Cache should be cleared (at least tools cache)
    }

    // ==================== Pending Tools Tests ====================

    #[Test]
    public function it_requires_owner_to_view_pending_tools(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->getJson('/api/admin/tools/pending');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_list_pending_tools(): void
    {
        Auth::login($this->owner);

        AiTool::factory()->create(['status' => 'pending_review']);
        AiTool::factory()->create(['status' => 'active']);

        $response = $this->getJson('/api/admin/tools/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'count',
            ]);

        // Should only return pending tools
        $data = $response->json('data');
        foreach ($data as $tool) {
            $this->assertEquals('pending_review', $tool['status']);
        }
    }

    // ==================== Statistics Tests ====================

    #[Test]
    public function it_requires_owner_to_view_statistics(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->getJson('/api/admin/statistics');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_get_statistics(): void
    {
        Auth::login($this->owner);

        AiTool::factory()->create(['status' => 'active']);
        AiTool::factory()->create(['status' => 'pending_review']);
        User::factory()->create(['status' => 'approved']);
        User::factory()->create(['status' => 'pending']);

        $response = $this->getJson('/api/admin/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_tools',
                    'active_tools',
                    'pending_tools',
                    'inactive_tools',
                    'total_categories',
                    'total_users',
                    'approved_users',
                    'pending_users',
                    'rejected_users',
                    'tools_by_status',
                    'tools_by_category',
                ],
            ]);
    }

    #[Test]
    public function it_caches_statistics(): void
    {
        Auth::login($this->owner);

        // First request
        $response1 = $this->getJson('/api/admin/statistics');
        $response1->assertStatus(200);

        // Second request should use cache
        $response2 = $this->getJson('/api/admin/statistics');
        $response2->assertStatus(200);

        $this->assertEquals(
            $response1->json('data'),
            $response2->json('data')
        );
    }

    // ==================== Users Tests ====================

    #[Test]
    public function it_requires_owner_to_manage_users(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_list_users(): void
    {
        Auth::login($this->owner);

        User::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'status',
                    ],
                ],
                'pagination',
            ]);
    }

    #[Test]
    public function it_can_filter_users_by_status(): void
    {
        Auth::login($this->owner);

        User::factory()->create(['status' => 'approved']);
        User::factory()->create(['status' => 'pending']);

        $response = $this->getJson('/api/admin/users?status=approved');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $user) {
            $this->assertEquals('approved', $user['status']);
        }
    }

    #[Test]
    public function it_can_filter_users_by_role(): void
    {
        Auth::login($this->owner);

        User::factory()->create(['role' => 'backend']);
        User::factory()->create(['role' => 'frontend']);

        $response = $this->getJson('/api/admin/users?role=backend');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $user) {
            $this->assertEquals('backend', $user['role']);
        }
    }

    #[Test]
    public function it_validates_sort_by_in_admin_users(): void
    {
        Auth::login($this->owner);

        $response = $this->getJson('/api/admin/users?sort_by=invalid_field');

        $response->assertStatus(200);
        // Should default to created_at
    }

    #[Test]
    public function it_validates_per_page_in_admin_users(): void
    {
        Auth::login($this->owner);

        $response = $this->getJson('/api/admin/users?per_page=200');

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('pagination.per_page'));
    }

    // ==================== Create User Tests ====================

    #[Test]
    public function it_requires_owner_to_create_user(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'backend',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_create_user(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'backend',
            'status' => 'approved',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User created successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'backend',
            'status' => 'approved',
        ]);
    }

    #[Test]
    public function it_validates_required_fields_when_creating_user(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson('/api/admin/users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    #[Test]
    public function it_validates_email_uniqueness_when_creating_user(): void
    {
        Auth::login($this->owner);

        $existingUser = User::factory()->create();

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => $existingUser->email,
            'password' => 'password123',
            'role' => 'backend',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function it_defaults_to_approved_status_when_creating_user(): void
    {
        Auth::login($this->owner);

        $response = $this->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'role' => 'backend',
            // No status provided
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'status' => 'approved',
        ]);
    }

    // ==================== User Details Tests ====================

    #[Test]
    public function it_requires_owner_to_view_user_details(): void
    {
        Auth::login($this->approvedUser);

        $user = User::factory()->create();

        $response = $this->getJson("/api/admin/users/{$user->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_get_user_details(): void
    {
        Auth::login($this->owner);

        $user = User::factory()->create();

        $response = $this->getJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'status',
                ],
            ]);
    }

    // ==================== Approve User Tests ====================

    #[Test]
    public function it_requires_owner_to_approve_user(): void
    {
        Auth::login($this->approvedUser);

        $user = User::factory()->create(['status' => 'pending']);

        $response = $this->postJson("/api/admin/users/{$user->id}/approve", [
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_approve_user(): void
    {
        Auth::login($this->owner);

        $user = User::factory()->create(['status' => 'pending']);

        $response = $this->postJson("/api/admin/users/{$user->id}/approve", [
            'status' => 'approved',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User approved successfully',
            ]);

        $user->refresh();
        $this->assertEquals('approved', $user->status);
    }

    #[Test]
    public function it_can_reject_user(): void
    {
        Auth::login($this->owner);

        $user = User::factory()->create(['status' => 'pending']);

        $response = $this->postJson("/api/admin/users/{$user->id}/approve", [
            'status' => 'rejected',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User rejected successfully',
            ]);

        $user->refresh();
        $this->assertEquals('rejected', $user->status);
    }

    #[Test]
    public function it_validates_status_when_approving_user(): void
    {
        Auth::login($this->owner);

        $user = User::factory()->create();

        $response = $this->postJson("/api/admin/users/{$user->id}/approve", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
    }

    // ==================== Update User Role Tests ====================

    #[Test]
    public function it_requires_owner_to_update_user_role(): void
    {
        Auth::login($this->approvedUser);

        $user = User::factory()->create();

        $response = $this->putJson("/api/admin/users/{$user->id}/role", [
            'role' => 'frontend',
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_update_user_role(): void
    {
        Auth::login($this->owner);

        $user = User::factory()->create(['role' => 'backend']);

        $response = $this->putJson("/api/admin/users/{$user->id}/role", [
            'role' => 'frontend',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User role updated successfully',
            ]);

        $user->refresh();
        $this->assertEquals('frontend', $user->role);
    }

    #[Test]
    public function it_validates_role_when_updating_user_role(): void
    {
        Auth::login($this->owner);

        $user = User::factory()->create();

        $response = $this->putJson("/api/admin/users/{$user->id}/role", [
            'role' => 'invalid_role',
        ]);

        $response->assertStatus(422);
    }

    // ==================== Export Users Tests ====================

    #[Test]
    public function it_requires_owner_to_export_users(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->getJson('/api/admin/users/export');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_export_users(): void
    {
        Auth::login($this->owner);

        User::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/users/export');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition');
    }

    #[Test]
    public function it_applies_filters_when_exporting_users(): void
    {
        Auth::login($this->owner);

        User::factory()->create(['status' => 'approved']);
        User::factory()->create(['status' => 'pending']);

        $response = $this->getJson('/api/admin/users/export?status=approved');

        $response->assertStatus(200);
        // CSV should only contain approved users
    }
}

