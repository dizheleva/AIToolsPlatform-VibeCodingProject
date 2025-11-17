<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            'data' => $categories,
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Only owners can create categories
        if ($user->role !== 'owner' || $user->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only owners can create categories.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'parent_id' => 'nullable|exists:categories,id',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        // Generate slug from name
        $slug = Str::slug($validated['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $category = Category::create([
            ...$validated,
            'slug' => $slug,
            'order' => $validated['order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Log activity
        $activityLogService->log('created', $category);

        // Clear cache
        Cache::flush(); // Clear all category caches

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => $category,
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
            'data' => $category,
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Only owners can update categories
        if ($user->role !== 'owner' || $user->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only owners can update categories.',
            ], 403);
        }

        $oldValues = $category->toArray();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'parent_id' => 'nullable|exists:categories,id',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

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

        $category->update($validated);

        // Log activity
        $activityLogService->log('updated', $category, null, $oldValues, $category->fresh()->toArray());

        // Clear cache
        Cache::flush(); // Clear all category caches

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => $category,
        ]);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category, ActivityLogService $activityLogService): JsonResponse
    {
        $user = Auth::user();

        // Only owners can delete categories
        if ($user->role !== 'owner' || $user->status !== 'approved') {
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

        // Clear cache
        Cache::flush(); // Clear all category caches

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.',
        ]);
    }
}

