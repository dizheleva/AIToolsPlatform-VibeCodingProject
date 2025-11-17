<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\ToolReview;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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
            $query->where('rating', '>=', $request->min_rating);
        }

        // Sort by most recent by default
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 10);
        $reviews = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $reviews->items(),
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
    public function store(Request $request, AiTool $aiTool, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Check if user is approved
        if ($user->status !== 'approved') {
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

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review = ToolReview::create([
            'ai_tool_id' => $aiTool->id,
            'user_id' => $user->id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        $review->load('user');

        // Log activity
        $activityLogService->log('created', $review, "Review created for tool: {$aiTool->name}");

        // Clear cache
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Review created successfully.',
            'data' => $review,
        ], 201);
    }

    /**
     * Update an existing review.
     */
    public function update(Request $request, AiTool $aiTool, ToolReview $review, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Check if user owns this review
        if ($review->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You can only update your own reviews.',
            ], 403);
        }

        // Check if review belongs to the tool
        if ($review->ai_tool_id !== $aiTool->id) {
            return response()->json([
                'success' => false,
                'message' => 'Review does not belong to this tool.',
            ], 404);
        }

        $validated = $request->validate([
            'rating' => 'sometimes|required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $review->update($validated);
        $review->load('user');

        // Log activity
        $activityLogService->log('updated', $review, "Review updated for tool: {$aiTool->name}");

        // Clear cache
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => 'Review updated successfully.',
            'data' => $review,
        ]);
    }

    /**
     * Delete a review.
     */
    public function destroy(AiTool $aiTool, ToolReview $review, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Check permissions: user can delete their own review, owner can delete any review
        $isOwner = $user->role === 'owner' && $user->status === 'approved';
        $isReviewer = $review->user_id === $user->id;

        if (!$isOwner && !$isReviewer) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this review.',
            ], 403);
        }

        // Check if review belongs to the tool
        if ($review->ai_tool_id !== $aiTool->id) {
            return response()->json([
                'success' => false,
                'message' => 'Review does not belong to this tool.',
            ], 404);
        }

        // Log activity before deletion
        $activityLogService->log('deleted', $review, "Review deleted for tool: {$aiTool->name}");

        $review->delete();

        // Clear cache
        Cache::flush();

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
        $reviews = $aiTool->reviews();

        $stats = [
            'total_reviews' => $reviews->count(),
            'average_rating' => round($reviews->avg('rating') ?? 0, 2),
            'rating_distribution' => [
                5 => $reviews->where('rating', 5)->count(),
                4 => $reviews->where('rating', 4)->count(),
                3 => $reviews->where('rating', 3)->count(),
                2 => $reviews->where('rating', 2)->count(),
                1 => $reviews->where('rating', 1)->count(),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}

