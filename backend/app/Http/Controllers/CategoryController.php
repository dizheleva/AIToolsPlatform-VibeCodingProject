<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ActivityLogService;
use App\Http\Resources\CategoryResource;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): JsonResponse
    {
        // Build cache key based on filters
        $cacheKey = 'categories_list_' . md5(json_encode($request->all()));

        $categories = Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = Category::query();

            // Filter by active status
            if ($request->has('active')) {
                $query->where('is_active', $request->boolean('active'));
            } else {
                // Show only active categories by default
                $query->where('is_active', true);
            }

            // Filter by parent (root categories or children)
            if ($request->has('parent_id')) {
                if ($request->parent_id === 'null' || $request->parent_id === null) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $request->parent_id);
                }
            }

            // Include tools count
            if ($request->boolean('with_counts')) {
                $query->withCount('tools');
            }

            // Sorting
            $query->orderBy('order')->orderBy('name');

            return $query->get();
        });

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(StoreCategoryRequest $request, ActivityLogService $activityLogService): JsonResponse
    {
        $validated = $request->validated();

        // Generate slug from name
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $category = DB::transaction(function () use ($validated, $slug, $activityLogService) {
            $category = Category::create([
                ...$validated,
                'slug' => $slug,
                'order' => $validated['order'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Log activity
            try {
                $activityLogService->log('created', $category);
            } catch (\Exception $e) {
                \Log::warning('Failed to log activity', [
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the transaction if logging fails
            }

            return $category;
        });

        // Clear cache - use intelligent cache clearing
        $this->clearCategoriesCache();

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => new CategoryResource($category),
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category): JsonResponse
    {
        $category->load(['parent', 'children', 'tools']);

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(UpdateCategoryRequest $request, Category $category, ActivityLogService $activityLogService): JsonResponse
    {
        $oldValues = $category->toArray();
        $validated = $request->validated();

        // Update slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $category->name) {
            $slug = Str::slug($validated['name']);
            $originalSlug = $slug;
            $counter = 1;
            while (Category::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        // Prevent circular reference in parent_id
        if (isset($validated['parent_id']) && $validated['parent_id'] == $category->id) {
            return response()->json([
                'success' => false,
                'message' => 'Category cannot be its own parent.',
            ], 422);
        }

        DB::transaction(function () use ($category, $validated, $oldValues, $activityLogService) {
            $category->update($validated);

            // Log activity
            try {
                $activityLogService->log('updated', $category, null, $oldValues, $category->fresh()->toArray());
            } catch (\Exception $e) {
                \Log::warning('Failed to log activity', [
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the transaction if logging fails
            }
        });

        // Refresh category to get latest data
        $category->refresh();

        // Clear cache - use intelligent cache clearing
        $this->clearCategoriesCache();

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category, ActivityLogService $activityLogService): JsonResponse
    {
        // Check authorization using Policy
        if (!Gate::allows('delete', $category)) {
            return response()->json([
                'success' => false,
                'message' => 'Only owners can delete categories.',
            ], 403);
        }

        // Check if category has tools
        if ($category->tools()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with associated tools.',
            ], 422);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with subcategories.',
            ], 422);
        }

        // Log activity before deletion
        $activityLogService->log('deleted', $category);

        $category->delete();

        // Clear cache - use intelligent cache clearing
        $this->clearCategoriesCache();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }

    /**
     * Clear categories-related cache keys.
     * Instead of flushing all cache, we clear only relevant keys.
     */
    private function clearCategoriesCache(): void
    {
        $cacheDriver = config('cache.default');

        // If using Redis, try to use cache tags for more efficient clearing
        if ($cacheDriver === 'redis' && method_exists(Cache::getStore(), 'tags')) {
            try {
                Cache::tags(['categories'])->flush();
                return;
            } catch (\Exception $e) {
                // If tags are not supported, fall back to manual clearing
                \Log::warning('Cache tags not available, falling back to manual cache clearing');
            }
        }

        // Manual cache clearing for other drivers
        $commonFilters = [
            [],
            ['active' => true],
            ['active' => false],
            ['parent_id' => 'null'],
            ['with_counts' => true],
        ];

        foreach ($commonFilters as $filters) {
            $cacheKey = 'categories_list_' . md5(json_encode($filters));
            Cache::forget($cacheKey);
        }
    }
}

