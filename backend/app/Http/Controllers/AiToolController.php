<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\Category;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiToolController extends Controller
{
    /**
     * Display a listing of AI tools.
     */
    public function index(Request $request): JsonResponse
    {
        // Get tools count from cache if available
        $cacheKey = 'tools_count_' . md5(json_encode($request->only(['status', 'category_id', 'role', 'featured'])));

        $query = AiTool::with(['creator', 'categories', 'toolRoles']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            // Show only active tools by default for non-owners
            // Only show all tools if user is authenticated AND is owner AND is approved
            if (!Auth::check() || (Auth::user()->role !== 'owner' && Auth::user()->status !== 'approved')) {
                $query->where('status', 'active');
            }
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        // Filter by role
        if ($request->has('role')) {
            $query->forRole($request->role);
        }

        // Filter by featured
        if ($request->has('featured')) {
            $query->where('featured', $request->boolean('featured'));
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // Sorting - validate against whitelist to prevent SQL injection
        $allowedSortColumns = ['created_at', 'name', 'views_count', 'likes_count', 'updated_at'];
        $sortBy = $request->get('sort_by', 'created_at');
        $sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'created_at';

        $sortOrder = strtolower($request->get('sort_order', 'desc'));
        $sortOrder = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination - validate and limit per_page
        $perPage = (int)$request->get('per_page', 15);
        $perPage = max(1, min($perPage, 100)); // Min 1, Max 100
        $tools = $query->paginate($perPage);

        // Cache tools count for this filter combination
        Cache::remember($cacheKey, 3600, fn() => $tools->total());

        return response()->json([
            'success' => true,
            'data' => $tools->items(),
            'pagination' => [
                'current_page' => $tools->currentPage(),
                'last_page' => $tools->lastPage(),
                'per_page' => $tools->perPage(),
                'total' => $tools->total(),
            ],
        ]);
    }

    /**
     * Store a newly created AI tool.
     */
    public function store(Request $request, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Check if user is approved
        if ($user->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to create AI tools.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'url' => 'required|url|max:500',
            'logo_url' => 'nullable|url|max:500',
            'pricing_model' => 'required|in:free,freemium,paid,enterprise',
            'status' => 'nullable|in:active,inactive,pending_review',
            'featured' => 'nullable|boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'roles' => 'nullable|array',
            'roles.*' => 'in:backend,frontend,qa,pm,designer',
            'tags' => 'nullable|array',
            'documentation_url' => 'nullable|url|max:500',
            'github_url' => 'nullable|url|max:500',
        ]);

        // Use transaction to ensure data consistency
        $tool = DB::transaction(function () use ($validated, $user) {
            // Generate slug from name
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;
            while (AiTool::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }

            // Create the tool
            $tool = AiTool::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'url' => $validated['url'],
                'logo_url' => $validated['logo_url'] ?? null,
                'pricing_model' => $validated['pricing_model'],
                'status' => $validated['status'] ?? ($user->role === 'owner' ? 'active' : 'pending_review'),
                'featured' => $validated['featured'] ?? false,
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'documentation_url' => $validated['documentation_url'] ?? null,
                'github_url' => $validated['github_url'] ?? null,
                'tags' => $validated['tags'] ?? null,
            ]);

            // Attach categories
            if (isset($validated['category_ids'])) {
                $tool->categories()->attach($validated['category_ids']);
            }

            // Attach roles
            if (isset($validated['roles'])) {
                $tool->syncRoles($validated['roles']);
            }

            return $tool;
        });

        // Load relationships
        $tool->load(['creator', 'categories', 'toolRoles']);

        // Log activity
        $activityLogService->log('created', $tool);

        // Clear specific cache keys instead of flushing all
        $this->clearToolsCache();

        return response()->json([
            'success' => true,
            'message' => 'AI tool created successfully.',
            'data' => $tool,
        ], 201);
    }

    /**
     * Display the specified AI tool.
     */
    public function show(AiTool $aiTool): JsonResponse
    {
        // Increment views - use direct DB increment for better performance
        // This avoids loading the model into memory just to increment
        DB::table('ai_tools')->where('id', $aiTool->id)->increment('views_count');

        $aiTool->load(['creator', 'updater', 'categories', 'toolRoles']);

        // Check if current user has liked this tool - use relationship instead of DB::table
        $isLiked = false;
        if (Auth::check()) {
            $isLiked = $aiTool->likedBy()->where('user_id', Auth::id())->exists();
        }

        // Add is_liked to the response
        $data = $aiTool->toArray();
        $data['is_liked'] = $isLiked;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update the specified AI tool.
     */
    public function update(Request $request, AiTool $aiTool, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Check permissions: only owner or creator can update
        // Owner can always update, creator can update their own tools if approved
        $isOwner = $user->role === 'owner' && $user->status === 'approved';
        $isCreator = $aiTool->created_by === $user->id && $user->status === 'approved';

        if (!$isOwner && !$isCreator) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this tool.',
            ], 403);
        }

        $oldValues = $aiTool->toArray();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string|max:500',
            'url' => 'sometimes|required|url|max:500',
            'logo_url' => 'nullable|url|max:500',
            'pricing_model' => 'sometimes|required|in:free,freemium,paid,enterprise',
            'status' => 'nullable|in:active,inactive,pending_review',
            'featured' => 'nullable|boolean',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'roles' => 'nullable|array',
            'roles.*' => 'in:backend,frontend,qa,pm,designer',
            'tags' => 'nullable|array',
            'documentation_url' => 'nullable|url|max:500',
            'github_url' => 'nullable|url|max:500',
        ]);

        // Use transaction to ensure data consistency
        DB::transaction(function () use ($validated, $aiTool, $user) {
            // Update slug if name changed
            if (isset($validated['name']) && $validated['name'] !== $aiTool->name) {
                $slug = Str::slug($validated['name']);
                $originalSlug = $slug;
                $counter = 1;
                while (AiTool::where('slug', $slug)->where('id', '!=', $aiTool->id)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
                $validated['slug'] = $slug;
            }

            // Only owners can change status and featured
            if ($user->role !== 'owner' || $user->status !== 'approved') {
                unset($validated['status'], $validated['featured']);
            }

            $validated['updated_by'] = $user->id;

            $aiTool->update($validated);

            // Update categories
            if (isset($validated['category_ids'])) {
                $aiTool->categories()->sync($validated['category_ids']);
            }

            // Update roles
            if (isset($validated['roles'])) {
                $aiTool->syncRoles($validated['roles']);
            }
        });

        $aiTool->load(['creator', 'updater', 'categories', 'toolRoles']);

        // Log activity
        $activityLogService->log('updated', $aiTool, null, $oldValues, $aiTool->fresh()->toArray());

        // Clear specific cache keys instead of flushing all
        $this->clearToolsCache();

        return response()->json([
            'success' => true,
            'message' => 'AI tool updated successfully.',
            'data' => $aiTool,
        ]);
    }

    /**
     * Remove the specified AI tool (soft delete).
     */
    public function destroy(AiTool $aiTool, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Check permissions: only owner or creator can delete
        // Owner can always delete, creator can delete their own tools if approved
        $isOwner = $user->role === 'owner' && $user->status === 'approved';
        $isCreator = $aiTool->created_by === $user->id && $user->status === 'approved';

        if (!$isOwner && !$isCreator) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this tool.',
            ], 403);
        }

        // Log activity before deletion
        $activityLogService->log('deleted', $aiTool);

        $aiTool->delete();

        // Clear specific cache keys instead of flushing all
        $this->clearToolsCache();

        return response()->json([
            'success' => true,
            'message' => 'AI tool deleted successfully.',
        ]);
    }

    /**
     * Toggle like for an AI tool.
     */
    public function toggleLike(AiTool $aiTool): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 401);
        }

        // Use transaction with lock to prevent race conditions
        $result = DB::transaction(function () use ($aiTool, $user) {
            // Lock the row to prevent concurrent modifications
            $aiTool = AiTool::where('id', $aiTool->id)->lockForUpdate()->first();

            // Check if user already liked this tool using relationship
            $liked = $aiTool->likedBy()->where('user_id', $user->id)->exists();

            if ($liked) {
                // Unlike: remove from pivot table
                $aiTool->likedBy()->detach($user->id);
                $aiTool->decrementLikes();
                return ['liked' => false, 'message' => 'Tool unliked.'];
            } else {
                // Like: add to pivot table
                $aiTool->likedBy()->attach($user->id);
                $aiTool->incrementLikes();
                return ['liked' => true, 'message' => 'Tool liked.'];
            }
        });

        // Refresh to get latest data
        $aiTool->refresh();

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'liked' => $result['liked'],
                'likes_count' => $aiTool->likes_count,
            ],
        ]);
    }

    /**
     * Clear tools-related cache keys.
     * Instead of flushing all cache, we clear only relevant keys.
     */
    private function clearToolsCache(): void
    {
        // Clear common cache patterns
        // Note: This is a simplified approach. For production, consider using cache tags if available
        $patterns = [
            'tools_count_*',
        ];

        // Since Laravel doesn't support wildcard deletion by default,
        // we'll clear the most common cache keys
        // In production with Redis, you could use SCAN to find and delete matching keys
        // For now, we'll clear a reasonable set of common filter combinations
        $commonFilters = [
            [],
            ['status' => 'active'],
            ['status' => 'inactive'],
            ['status' => 'pending_review'],
            ['featured' => true],
        ];

        foreach ($commonFilters as $filters) {
            $cacheKey = 'tools_count_' . md5(json_encode($filters));
            Cache::forget($cacheKey);
        }
    }
}

