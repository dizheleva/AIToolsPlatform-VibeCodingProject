<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\Category;
use App\Services\ActivityLogService;
use App\Http\Requests\StoreAiToolRequest;
use App\Http\Requests\UpdateAiToolRequest;
use App\Http\Resources\AiToolResource;
use App\Jobs\IncrementToolViews;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
            'data' => AiToolResource::collection($tools->items()),
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
    public function store(StoreAiToolRequest $request, ActivityLogService $activityLogService): JsonResponse
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
        if (!Gate::allows('create', AiTool::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Your account must be approved to create AI tools.',
            ], 403);
        }

        // Get validated data from Form Request
        $validated = $request->validated();

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
            'data' => new AiToolResource($tool),
        ], 201);
    }

    /**
     * Display the specified AI tool.
     */
    public function show(AiTool $aiTool): JsonResponse
    {
        // Increment views asynchronously using queue job for better performance
        // This prevents blocking the request and improves response time
        IncrementToolViews::dispatch($aiTool->id);

        $aiTool->load(['creator', 'updater', 'categories', 'toolRoles']);

        // Check if current user has liked this tool - use relationship instead of DB::table
        $isLiked = false;
        if (Auth::check()) {
            $isLiked = $aiTool->likedBy()->where('user_id', Auth::id())->exists();
        }

        // Add is_liked to the resource
        $aiTool->is_liked = $isLiked;

        return response()->json([
            'success' => true,
            'data' => new AiToolResource($aiTool),
        ]);
    }

    /**
     * Update the specified AI tool.
     */
    public function update(UpdateAiToolRequest $request, AiTool $aiTool, ActivityLogService $activityLogService): JsonResponse
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
        if (!Gate::allows('update', $aiTool)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this tool.',
            ], 403);
        }

        $oldValues = $aiTool->toArray();

        // Get validated data from Form Request
        $validated = $request->validated();

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

            // Only owners can change status and featured (using Policy)
            if (!Gate::allows('manageStatus', $aiTool)) {
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
            'data' => new AiToolResource($aiTool),
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

        // Check authorization using Policy
        if (!Gate::allows('delete', $aiTool)) {
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
     * Uses cache tags if Redis is configured, otherwise clears common keys.
     */
    private function clearToolsCache(): void
    {
        $cacheDriver = config('cache.default');

        // If using Redis, try to use cache tags for more efficient clearing
        if ($cacheDriver === 'redis' && method_exists(Cache::getStore(), 'tags')) {
            try {
                Cache::tags(['ai_tools'])->flush();
                return;
            } catch (\Exception $e) {
                // If tags are not supported, fall back to manual clearing
                \Log::warning('Cache tags not available, falling back to manual cache clearing');
            }
        }

        // Manual cache clearing for other drivers or if tags fail
        // Clear common filter combinations
        $commonFilters = [
            [],
            ['status' => 'active'],
            ['status' => 'inactive'],
            ['status' => 'pending_review'],
            ['featured' => true],
            ['featured' => false],
            // Common category filters
            ['category_id' => 1],
            ['category_id' => 2],
            ['category_id' => 3],
            // Common role filters
            ['role' => 'backend'],
            ['role' => 'frontend'],
            ['role' => 'qa'],
            ['role' => 'pm'],
            ['role' => 'designer'],
        ];

        foreach ($commonFilters as $filters) {
            $cacheKey = 'tools_count_' . md5(json_encode($filters));
            Cache::forget($cacheKey);
        }

        // Also clear categories cache if it exists
        Cache::forget('categories_list_' . md5(json_encode([])));
        Cache::forget('categories_list_' . md5(json_encode(['active' => true])));
    }
}

