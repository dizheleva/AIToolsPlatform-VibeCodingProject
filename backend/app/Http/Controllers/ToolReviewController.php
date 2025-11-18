<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\ToolReview;
use App\Services\ActivityLogService;
use App\Http\Requests\StoreToolReviewRequest;
use App\Http\Requests\UpdateToolReviewRequest;
use App\Http\Resources\ToolReviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ToolReviewController extends Controller
{
    /**
     * Get reviews for a specific tool.
     */
    public function index(AiTool $aiTool, Request $request): JsonResponse
    {
        $query = $aiTool->reviews()->with('user');

        // Filter by rating
        if ($request->has('min_rating')) {
            $minRating = (int)$request->min_rating;
            if ($minRating >= 1 && $minRating <= 5) {
                $query->where('rating', '>=', $minRating);
            }
        }

        // Sorting - validate against whitelist to prevent SQL injection
        $allowedSortColumns = ['created_at', 'updated_at', 'rating'];
        $sortBy = $request->get('sort_by', 'created_at');
        $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'created_at';

        $sortOrder = strtolower($request->get('sort_order', 'desc'));
        $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination - validate and limit per_page
        $perPage = (int)$request->get('per_page', 10);
        $perPage = max(1, min($perPage, 100)); // Min 1, Max 100
        $reviews = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ToolReviewResource::collection($reviews->items()),
            'pagination' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
            'average_rating' => $aiTool->average_rating,
            'reviews_count' => $aiTool->reviews_count,
        ]);
    }

    /**
     * Store a new review for a tool.
     */
    public function store(StoreToolReviewRequest $request, AiTool $aiTool, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Check authorization using Policy
        if (!Gate::allows('create', ToolReview::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to write reviews.',
            ], 403);
        }

        // Check if user already reviewed this tool
        $existingReview = ToolReview::where('ai_tool_id', $aiTool->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this tool. You can update your existing review.',
            ], 409);
        }

        // Get validated data from Form Request
        $validated = $request->validated();

        // Use transaction to ensure data consistency
        $review = DB::transaction(function () use ($validated, $aiTool, $user) {
            return ToolReview::create([
                'ai_tool_id' => $aiTool->id,
                'user_id' => $user->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ]);
        });

        $review->load('user');

        // Log activity
        $activityLogService->log('created', $review, "Review created for tool: {$aiTool->name}");

        // Clear specific cache keys instead of flushing all
        $this->clearReviewsCache($aiTool);

        return response()->json([
            'success' => true,
            'message' => 'Review created successfully.',
            'data' => new ToolReviewResource($review),
        ], 201);
    }

    /**
     * Update an existing review.
     */
    public function update(UpdateToolReviewRequest $request, AiTool $aiTool, ToolReview $review, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Check if review belongs to the tool
        if ($review->ai_tool_id !== $aiTool->id) {
            return response()->json([
                'success' => false,
                'message' => 'Review does not belong to this tool.',
            ], 404);
        }

        // Check authorization using Policy
        if (!Gate::allows('update', $review)) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own reviews.',
            ], 403);
        }

        $oldValues = $review->toArray();

        // Get validated data from Form Request
        $validated = $request->validated();

        // Use transaction to ensure data consistency
        DB::transaction(function () use ($validated, $review) {
            $review->update($validated);
        });

        $review->load('user');

        // Log activity
        $activityLogService->log('updated', $review, "Review updated for tool: {$aiTool->name}", $oldValues, $review->fresh()->toArray());

        // Clear specific cache keys instead of flushing all
        $this->clearReviewsCache($aiTool);

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully.',
            'data' => new ToolReviewResource($review),
        ]);
    }

    /**
     * Delete a review.
     */
    public function destroy(AiTool $aiTool, ToolReview $review, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Check if review belongs to the tool
        if ($review->ai_tool_id !== $aiTool->id) {
            return response()->json([
                'success' => false,
                'message' => 'Review does not belong to this tool.',
            ], 404);
        }

        // Check authorization using Policy
        if (!Gate::allows('delete', $review)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this review.',
            ], 403);
        }

        // Log activity before deletion
        $activityLogService->log('deleted', $review, "Review deleted for tool: {$aiTool->name}");

        $review->delete();

        // Clear specific cache keys instead of flushing all
        $this->clearReviewsCache($aiTool);

        return response()->json([
            'success' => true,
            'message' => 'Review deleted successfully.',
        ]);
    }

    /**
     * Get review statistics for a tool.
     */
    public function statistics(AiTool $aiTool): JsonResponse
    {
        // Use cache for statistics to improve performance
        $cacheKey = "tool_reviews_stats_{$aiTool->id}";

        $stats = Cache::remember($cacheKey, 3600, function () use ($aiTool) {
            // Optimize: Use single query with aggregation instead of multiple queries
            $reviews = $aiTool->reviews();

            // Get all data in one query
            $ratingData = $reviews
                ->selectRaw('COUNT(*) as total, AVG(rating) as avg_rating')
                ->selectRaw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as rating_5')
                ->selectRaw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as rating_4')
                ->selectRaw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as rating_3')
                ->selectRaw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as rating_2')
                ->selectRaw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as rating_1')
                ->first();

            return [
                'total_reviews' => (int)($ratingData->total ?? 0),
                'average_rating' => round((float)($ratingData->avg_rating ?? 0), 2),
                'rating_distribution' => [
                    5 => (int)($ratingData->rating_5 ?? 0),
                    4 => (int)($ratingData->rating_4 ?? 0),
                    3 => (int)($ratingData->rating_3 ?? 0),
                    2 => (int)($ratingData->rating_2 ?? 0),
                    1 => (int)($ratingData->rating_1 ?? 0),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Clear reviews-related cache keys.
     * Instead of flushing all cache, we clear only relevant keys.
     */
    private function clearReviewsCache(AiTool $aiTool): void
    {
        $cacheDriver = config('cache.default');

        // If using Redis, try to use cache tags for more efficient clearing
        if ($cacheDriver === 'redis' && method_exists(Cache::getStore(), 'tags')) {
            try {
                Cache::tags(['reviews', "tool_{$aiTool->id}"])->flush();
                return;
            } catch (\Exception $e) {
                // If tags are not supported, fall back to manual clearing
                \Log::warning('Cache tags not available, falling back to manual cache clearing');
            }
        }

        // Manual cache clearing for other drivers
        $commonFilters = [
            [],
            ['min_rating' => 1],
            ['min_rating' => 3],
            ['min_rating' => 4],
            ['min_rating' => 5],
        ];

        foreach ($commonFilters as $filters) {
            $cacheKey = "tool_reviews_{$aiTool->id}_" . md5(json_encode($filters));
            Cache::forget($cacheKey);
        }

        // Clear statistics cache
        Cache::forget("tool_reviews_stats_{$aiTool->id}");
    }
}

