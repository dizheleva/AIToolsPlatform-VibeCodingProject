<?php

namespace Tests\Unit;

use App\Models\AiTool;
use App\Models\User;
use App\Policies\AiToolPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AiToolPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected AiToolPolicy $policy;
    protected User $owner;
    protected User $approvedUser;
    protected User $pendingUser;
    protected AiTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AiToolPolicy();

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

        $this->tool = AiTool::factory()->create([
            'created_by' => $this->approvedUser->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function everyone_can_view_active_tools(): void
    {
        $this->assertTrue($this->policy->view($this->approvedUser, $this->tool));
        $this->assertTrue($this->policy->view($this->pendingUser, $this->tool));
    }

    #[Test]
    public function only_owners_can_view_inactive_tools(): void
    {
        $inactiveTool = AiTool::factory()->create([
            'status' => 'inactive',
        ]);

        $this->assertTrue($this->policy->view($this->owner, $inactiveTool));
        $this->assertFalse($this->policy->view($this->approvedUser, $inactiveTool));
        $this->assertFalse($this->policy->view($this->pendingUser, $inactiveTool));
    }

    #[Test]
    public function only_approved_users_can_create_tools(): void
    {
        $this->assertTrue($this->policy->create($this->approvedUser));
        $this->assertTrue($this->policy->create($this->owner));
        $this->assertFalse($this->policy->create($this->pendingUser));
    }

    #[Test]
    public function owner_can_update_any_tool(): void
    {
        $this->assertTrue($this->policy->update($this->owner, $this->tool));
    }

    #[Test]
    public function creator_can_update_their_own_tool(): void
    {
        $this->assertTrue($this->policy->update($this->approvedUser, $this->tool));
    }

    #[Test]
    public function user_cannot_update_other_users_tool(): void
    {
        $otherUser = User::factory()->create([
            'role' => 'backend',
            'status' => 'approved',
        ]);

        $this->assertFalse($this->policy->update($otherUser, $this->tool));
    }

    #[Test]
    public function pending_user_cannot_update_tool(): void
    {
        $tool = AiTool::factory()->create([
            'created_by' => $this->pendingUser->id,
        ]);

        $this->assertFalse($this->policy->update($this->pendingUser, $tool));
    }

    #[Test]
    public function owner_can_delete_any_tool(): void
    {
        $this->assertTrue($this->policy->delete($this->owner, $this->tool));
    }

    #[Test]
    public function creator_can_delete_their_own_tool(): void
    {
        $this->assertTrue($this->policy->delete($this->approvedUser, $this->tool));
    }

    #[Test]
    public function only_owners_can_manage_status(): void
    {
        $this->assertTrue($this->policy->manageStatus($this->owner, $this->tool));
        $this->assertFalse($this->policy->manageStatus($this->approvedUser, $this->tool));
        $this->assertFalse($this->policy->manageStatus($this->pendingUser, $this->tool));
    }
}

