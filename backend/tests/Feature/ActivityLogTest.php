<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected ActivityLogService $activityLogService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'status' => 'approved',
            'role' => 'owner',
        ]);

        $this->activityLogService = app(ActivityLogService::class);
    }

    public function test_activity_is_logged_when_tool_is_created(): void
    {
        $this->actingAs($this->admin, 'web');

        $tool = AiTool::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->activityLogService->log('created', $tool);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id,
            'action' => 'created',
            'model_type' => get_class($tool),
            'model_id' => $tool->id,
        ]);
    }

    public function test_activity_is_logged_when_tool_is_updated(): void
    {
        $this->actingAs($this->admin, 'web');

        $tool = AiTool::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $oldValues = $tool->toArray();
        $tool->update(['name' => 'Updated Name']);
        $newValues = $tool->fresh()->toArray();

        $this->activityLogService->log('updated', $tool, null, $oldValues, $newValues);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id,
            'action' => 'updated',
            'model_type' => get_class($tool),
            'model_id' => $tool->id,
        ]);
    }

    public function test_activity_is_logged_when_tool_is_deleted(): void
    {
        $this->actingAs($this->admin, 'web');

        $tool = AiTool::factory()->create([
            'created_by' => $this->admin->id,
        ]);

        $this->activityLogService->log('deleted', $tool);
        $tool->delete();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $this->admin->id,
            'action' => 'deleted',
            'model_type' => get_class($tool),
            'model_id' => $tool->id,
        ]);
    }

    public function test_activity_is_logged_when_tool_is_approved(): void
    {
        $tool = AiTool::factory()->create([
            'status' => 'pending_review',
        ]);

        $this->activityLogService->log('approved', $tool, 'Tool approved by admin');

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'approved',
            'model_type' => get_class($tool),
            'model_id' => $tool->id,
            'description' => 'Tool approved by admin',
        ]);
    }

    public function test_admin_can_view_activity_logs(): void
    {
        // Create some activity logs
        $tool1 = AiTool::factory()->create();
        $tool2 = AiTool::factory()->create();

        $this->activityLogService->log('created', $tool1);
        $this->activityLogService->log('updated', $tool2);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/admin/activity-logs');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination',
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
    }

    public function test_admin_can_filter_activity_logs_by_action(): void
    {
        $tool1 = AiTool::factory()->create();
        $tool2 = AiTool::factory()->create();

        $this->activityLogService->log('created', $tool1);
        $this->activityLogService->log('updated', $tool2);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson('/api/admin/activity-logs?action=created');

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $log) {
            $this->assertEquals('created', $log['action']);
        }
    }

    public function test_admin_can_filter_activity_logs_by_user(): void
    {
        $otherUser = User::factory()->create();
        $tool1 = AiTool::factory()->create(['created_by' => $this->admin->id]);
        $tool2 = AiTool::factory()->create(['created_by' => $otherUser->id]);

        $this->activityLogService->log('created', $tool1);

        // Log as other user
        $this->actingAs($otherUser, 'web');
        $this->activityLogService->log('created', $tool2);

        $response = $this->actingAs($this->admin, 'web')
            ->getJson("/api/admin/activity-logs?user_id={$this->admin->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        foreach ($data as $log) {
            $this->assertEquals($this->admin->id, $log['user_id']);
        }
    }

    public function test_activity_logs_contain_ip_and_user_agent(): void
    {
        $tool = AiTool::factory()->create();
        $this->activityLogService->log('created', $tool);

        $log = \App\Models\ActivityLog::where('model_id', $tool->id)->first();

        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->user_agent);
    }
}

