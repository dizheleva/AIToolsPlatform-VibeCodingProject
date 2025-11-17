<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => 'approved',
            'role' => 'owner',
        ]);

        // Create regular user
        $this->regularUser = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'status' => 'approved',
            'role' => 'employee',
        ]);
    }

    public function test_admin_can_access_admin_tools_list(): void
    {
        // Create some tools
        AiTool::factory()->count(5)->create([
            'status' => 'active',
        ]);

        AiTool::factory()->count(3)->create([
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/admin/tools');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);
    }

    public function test_admin_can_filter_tools_by_status(): void
    {
        AiTool::factory()->count(3)->create(['status' => 'active']);
        AiTool::factory()->count(2)->create(['status' => 'pending_review']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/admin/tools?status=pending_review');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $tool) {
            $this->assertEquals('pending_review', $tool['status']);
        }
    }

    public function test_admin_can_get_pending_tools(): void
    {
        AiTool::factory()->count(5)->create(['status' => 'pending_review']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/admin/tools/pending');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'count',
            ]);

        $this->assertEquals(5, $response->json('count'));
    }

    public function test_admin_can_approve_tool(): void
    {
        $tool = AiTool::factory()->create([
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson("/api/admin/tools/{$tool->id}/approve", [
                'status' => 'active',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);

        $tool->refresh();
        $this->assertEquals('active', $tool->status);
    }

    public function test_admin_can_reject_tool(): void
    {
        $tool = AiTool::factory()->create([
            'status' => 'pending_review',
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson("/api/admin/tools/{$tool->id}/approve", [
                'status' => 'inactive',
            ]);

        $response->assertStatus(200);

        $tool->refresh();
        $this->assertEquals('inactive', $tool->status);
    }

    public function test_admin_can_get_users_list(): void
    {
        User::factory()->count(5)->create();

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);
    }

    public function test_admin_can_filter_users_by_status(): void
    {
        User::factory()->count(3)->create(['status' => 'approved']);
        User::factory()->count(2)->create(['status' => 'pending']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/admin/users?status=pending');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        foreach ($data as $user) {
            $this->assertEquals('pending', $user['status']);
        }
    }

    public function test_admin_can_approve_user(): void
    {
        $user = User::factory()->create([
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin, 'web')
            ->postJson("/api/admin/users/{$user->id}/approve", [
                'status' => 'approved',
            ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('approved', $user->status);
    }

    public function test_admin_can_get_statistics(): void
    {
        AiTool::factory()->count(10)->create(['status' => 'active']);
        AiTool::factory()->count(5)->create(['status' => 'pending_review']);
        Category::factory()->count(3)->create(['is_active' => true]);
        User::factory()->count(8)->create(['status' => 'approved']);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/admin/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_tools',
                    'active_tools',
                    'pending_tools',
                    'total_categories',
                    'total_users',
                    'approved_users',
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total_tools']);
        $this->assertGreaterThan(0, $data['total_categories']);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $response = $this->actingAs($this->regularUser, 'web')
            ->getJson('/api/admin/tools');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_admin_routes(): void
    {
        $response = $this->getJson('/api/admin/tools');

        $response->assertStatus(401);
    }
}

