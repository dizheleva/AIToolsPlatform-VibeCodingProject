<?php

namespace Tests\Feature;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\ToolReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ToolReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $approvedUser;
    protected User $pendingUser;
    protected AiTool $tool;
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

        // Create test tool
        $this->tool = AiTool::factory()->create([
            'name' => 'Test Tool',
            'slug' => 'test-tool',
            'status' => 'active',
        ]);
    }

    #[Test]
    public function it_can_list_reviews_for_a_tool(): void
    {
        ToolReview::factory()->count(3)->create([
            'ai_tool_id' => $this->tool->id,
        ]);

        $response = $this->getJson("/api/tools/{$this->tool->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'rating',
                        'comment',
                        'user',
                    ],
                ],
                'pagination',
                'average_rating',
                'reviews_count',
            ]);
    }

    #[Test]
    public function it_can_filter_reviews_by_min_rating(): void
    {
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 5,
        ]);
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 3,
        ]);
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 1,
        ]);

        $response = $this->getJson("/api/tools/{$this->tool->slug}/reviews?min_rating=4");

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $review) {
            $this->assertGreaterThanOrEqual(4, $review['rating']);
        }
    }

    #[Test]
    public function it_can_sort_reviews_by_rating(): void
    {
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 3,
        ]);
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 5,
        ]);
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 1,
        ]);

        $response = $this->getJson("/api/tools/{$this->tool->slug}/reviews?sort_by=rating&sort_order=desc");

        $response->assertStatus(200);
        $data = $response->json('data');
        $ratings = array_column($data, 'rating');
        $this->assertEquals([5, 3, 1], $ratings);
    }

    #[Test]
    public function it_validates_sort_by_against_whitelist(): void
    {
        $response = $this->getJson("/api/tools/{$this->tool->slug}/reviews?sort_by=invalid_column");

        $response->assertStatus(200);
        // Should default to 'created_at' instead of using invalid column
    }

    #[Test]
    public function it_validates_per_page_limits(): void
    {
        ToolReview::factory()->count(5)->create([
            'ai_tool_id' => $this->tool->id,
        ]);

        // Test max limit
        $response = $this->getJson("/api/tools/{$this->tool->slug}/reviews?per_page=200");
        $response->assertStatus(200);
        $this->assertLessThanOrEqual(100, $response->json('pagination.per_page'));

        // Test min limit
        $response = $this->getJson("/api/tools/{$this->tool->slug}/reviews?per_page=0");
        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('pagination.per_page'));
    }

    #[Test]
    public function it_requires_authentication_to_create_review(): void
    {
        $response = $this->postJson("/api/tools/{$this->tool->slug}/reviews", [
            'rating' => 5,
            'comment' => 'Great tool!',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function it_requires_approved_status_to_create_review(): void
    {
        Auth::login($this->pendingUser);

        $response = $this->postJson("/api/tools/{$this->tool->slug}/reviews", [
            'rating' => 5,
            'comment' => 'Great tool!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account must be approved to write reviews.',
            ]);
    }

    #[Test]
    public function approved_user_can_create_review(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->postJson("/api/tools/{$this->tool->slug}/reviews", [
            'rating' => 5,
            'comment' => 'Great tool!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'rating',
                    'comment',
                ],
            ]);

        $this->assertDatabaseHas('tool_reviews', [
            'ai_tool_id' => $this->tool->id,
            'user_id' => $this->approvedUser->id,
            'rating' => 5,
        ]);
    }

    #[Test]
    public function it_validates_required_fields_when_creating(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->postJson("/api/tools/{$this->tool->slug}/reviews", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    #[Test]
    public function it_validates_rating_range(): void
    {
        Auth::login($this->approvedUser);

        // Test rating too high
        $response = $this->postJson("/api/tools/{$this->tool->slug}/reviews", [
            'rating' => 6,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Test rating too low
        $response = $this->postJson("/api/tools/{$this->tool->slug}/reviews", [
            'rating' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    #[Test]
    public function it_validates_comment_max_length(): void
    {
        Auth::login($this->approvedUser);

        $response = $this->postJson("/api/tools/{$this->tool->slug}/reviews", [
            'rating' => 5,
            'comment' => str_repeat('a', 2001), // Exceeds max length
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['comment']);
    }

    #[Test]
    public function it_prevents_duplicate_reviews(): void
    {
        Auth::login($this->approvedUser);

        // Create first review
        $this->postJson("/api/tools/{$this->tool->slug}/reviews", [
            'rating' => 5,
            'comment' => 'First review',
        ]);

        // Try to create second review
        $response = $this->postJson("/api/tools/{$this->tool->slug}/reviews", [
            'rating' => 4,
            'comment' => 'Second review',
        ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'You have already reviewed this tool. You can update your existing review.',
            ]);
    }

    #[Test]
    public function user_can_update_their_own_review(): void
    {
        Auth::login($this->approvedUser);

        $review = ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'user_id' => $this->approvedUser->id,
            'rating' => 3,
            'comment' => 'Original comment',
        ]);

        $response = $this->putJson("/api/tools/{$this->tool->slug}/reviews/{$review->id}", [
            'rating' => 5,
            'comment' => 'Updated comment',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tool_reviews', [
            'id' => $review->id,
            'rating' => 5,
            'comment' => 'Updated comment',
        ]);
    }

    #[Test]
    public function user_cannot_update_other_users_review(): void
    {
        $otherUser = User::factory()->create(['status' => 'approved']);
        $review = ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'user_id' => $otherUser->id,
        ]);

        Auth::login($this->approvedUser);

        $response = $this->putJson("/api/tools/{$this->tool->slug}/reviews/{$review->id}", [
            'rating' => 1,
        ]);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_validates_review_belongs_to_tool(): void
    {
        $otherTool = AiTool::factory()->create();
        $review = ToolReview::factory()->create([
            'ai_tool_id' => $otherTool->id,
            'user_id' => $this->approvedUser->id,
        ]);

        Auth::login($this->approvedUser);

        $response = $this->putJson("/api/tools/{$this->tool->slug}/reviews/{$review->id}", [
            'rating' => 5,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Review does not belong to this tool.',
            ]);
    }

    #[Test]
    public function user_can_delete_their_own_review(): void
    {
        Auth::login($this->approvedUser);

        $review = ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'user_id' => $this->approvedUser->id,
        ]);

        $response = $this->deleteJson("/api/tools/{$this->tool->slug}/reviews/{$review->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('tool_reviews', [
            'id' => $review->id,
        ]);
    }

    #[Test]
    public function owner_can_delete_any_review(): void
    {
        $review = ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'user_id' => $this->approvedUser->id,
        ]);

        Auth::login($this->owner);

        $response = $this->deleteJson("/api/tools/{$this->tool->slug}/reviews/{$review->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('tool_reviews', [
            'id' => $review->id,
        ]);
    }

    #[Test]
    public function user_cannot_delete_other_users_review(): void
    {
        $otherUser = User::factory()->create(['status' => 'approved']);
        $review = ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'user_id' => $otherUser->id,
        ]);

        Auth::login($this->approvedUser);

        $response = $this->deleteJson("/api/tools/{$this->tool->slug}/reviews/{$review->id}");

        $response->assertStatus(403);
    }

    #[Test]
    public function it_can_get_review_statistics(): void
    {
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 5,
        ]);
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 4,
        ]);
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 3,
        ]);

        $response = $this->getJson("/api/tools/{$this->tool->slug}/reviews/statistics");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_reviews',
                    'average_rating',
                    'rating_distribution' => [
                        '5',
                        '4',
                        '3',
                        '2',
                        '1',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(3, $data['total_reviews']);
        $this->assertEquals(1, $data['rating_distribution']['5']);
        $this->assertEquals(1, $data['rating_distribution']['4']);
        $this->assertEquals(1, $data['rating_distribution']['3']);
    }

    #[Test]
    public function statistics_calculates_average_rating_correctly(): void
    {
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 5,
        ]);
        ToolReview::factory()->create([
            'ai_tool_id' => $this->tool->id,
            'rating' => 3,
        ]);

        $response = $this->getJson("/api/tools/{$this->tool->slug}/reviews/statistics");

        $data = $response->json('data');
        $this->assertEquals(4.0, $data['average_rating']); // (5 + 3) / 2 = 4
    }
}

