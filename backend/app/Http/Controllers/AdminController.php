<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Http\Requests\ApproveToolRequest;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\ApproveUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\AiToolResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function __construct()
    {
        //
    }

    /**
     * Get all tools with filters for admin panel
     */
    public function tools(Request $request): JsonResponse
    {
        // Check authorization
        if (!Gate::allows('manageTools', User::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only owners can access admin tools.',
            ], 403);
        }

        try {
            $query = AiTool::query();

            // Eager load relationships only if they exist
            $query->with(['creator', 'updater', 'categories', 'toolRoles']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
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

            // Filter by creator
            if ($request->has('created_by')) {
                $query->where('created_by', $request->created_by);
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

            // Sorting - validate sort_by against whitelist
            $allowedSortFields = ['created_at', 'updated_at', 'name', 'status', 'views_count', 'likes_count'];
            $sortBy = $request->get('sort_by', 'created_at');
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            $sortOrder = $request->get('sort_order', 'desc');
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                $sortOrder = 'desc';
            }
            $query->orderBy($sortBy, $sortOrder);

            // Pagination - validate per_page
            $perPage = min(max((int) $request->get('per_page', 20), 1), 100);
            $tools = $query->paginate($perPage);

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
        } catch (\Exception $e) {
            \Log::error('AdminController::tools error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve or reject a tool
     */
    public function approveTool(ApproveToolRequest $request, AiTool $aiTool, ActivityLogService $activityLogService): JsonResponse
    {
        $oldStatus = $aiTool->status;

        DB::transaction(function () use ($request, $aiTool, $oldStatus, $activityLogService) {
            $aiTool->status = $request->status;
            $aiTool->updated_by = auth()->id();
            $aiTool->save();

            // Log activity
            $action = $request->status === 'active' ? 'approved' : ($request->status === 'inactive' ? 'rejected' : 'updated');
            $activityLogService->log($action, $aiTool, "Tool status changed from {$oldStatus} to {$request->status}");
        });

        // Clear cache intelligently
        $this->clearToolsCache();

        return response()->json([
            'success' => true,
            'message' => "Tool {$request->status} successfully",
            'data' => new AiToolResource($aiTool->load(['creator', 'updater', 'categories', 'toolRoles'])),
        ]);
    }

    /**
     * Get all pending tools
     */
    public function pendingTools(): JsonResponse
    {
        // Check authorization
        if (!Gate::allows('manageTools', User::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only owners can view pending tools.',
            ], 403);
        }

        $tools = AiTool::where('status', 'pending_review')
            ->with(['creator', 'categories', 'toolRoles'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AiToolResource::collection($tools),
            'count' => $tools->count(),
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics(): JsonResponse
    {
        // Check authorization
        if (!Gate::allows('viewStatistics', User::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only owners can view statistics.',
            ], 403);
        }

        $stats = Cache::remember('admin_statistics', 300, function () {
            return [
                'total_tools' => AiTool::count(),
                'active_tools' => AiTool::where('status', 'active')->count(),
                'pending_tools' => AiTool::where('status', 'pending_review')->count(),
                'inactive_tools' => AiTool::where('status', 'inactive')->count(),
                'total_categories' => Category::where('is_active', true)->count(),
                'total_users' => User::count(),
                'approved_users' => User::where('status', 'approved')->count(),
                'pending_users' => User::where('status', 'pending')->count(),
                'rejected_users' => User::where('status', 'rejected')->count(),
                'tools_by_status' => AiTool::select('status', DB::raw('count(*) as count'))
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'tools_by_category' => Category::withCount('tools')
                    ->where('is_active', true)
                    ->orderBy('tools_count', 'desc')
                    ->limit(10)
                    ->get(['id', 'name', 'tools_count']),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get all users with filters
     */
    public function users(Request $request): JsonResponse
    {
        // Check authorization
        if (!Gate::allows('manageUsers', User::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only owners can manage users.',
            ], 403);
        }

        $query = User::query();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Sorting - validate sort_by against whitelist
        $allowedSortFields = ['created_at', 'updated_at', 'name', 'email', 'role', 'status'];
        $sortBy = $request->get('sort_by', 'created_at');
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'created_at';
        }
        $sortOrder = $request->get('sort_order', 'desc');
        if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }
        $query->orderBy($sortBy, $sortOrder);

        // Pagination - validate per_page
        $perPage = min(max((int) $request->get('per_page', 20), 1), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => UserResource::collection($users->items()),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Create a new user (admin only)
     */
    public function createUser(CreateUserRequest $request, ActivityLogService $activityLogService): JsonResponse
    {
        $user = DB::transaction(function () use ($request, $activityLogService) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'status' => $request->status ?? 'approved', // Default to approved when created by admin
                'email_verified_at' => now(),
            ]);

            // Log activity
            $activityLogService->log('created', $user, "User created by admin");

            return $user;
        });

        // Clear admin cache
        $this->clearAdminCache();

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Get user details
     */
    public function userDetails(User $user): JsonResponse
    {
        // Check authorization
        if (!Gate::allows('manageUsers', User::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only owners can view user details.',
            ], 403);
        }

        $user->load(['createdTools', 'likedTools']);

        return response()->json([
            'success' => true,
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Approve or reject a user
     */
    public function approveUser(ApproveUserRequest $request, User $user, ActivityLogService $activityLogService): JsonResponse
    {
        $oldStatus = $user->status;

        DB::transaction(function () use ($request, $user, $oldStatus, $activityLogService) {
            $user->status = $request->status;
            $user->save();

            // Log activity
            try {
                $action = $request->status === 'approved' ? 'approved' : ($request->status === 'rejected' ? 'rejected' : 'updated');
                $activityLogService->log($action, $user, "User status changed from {$oldStatus} to {$request->status}");
            } catch (\Exception $e) {
                \Log::warning('Failed to log activity', [
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the transaction if logging fails
            }
        });

        // Refresh user to get latest data
        $user->refresh();

        // Send email notification (only in production, skip in tests)
        if (!app()->environment('testing')) {
            try {
                if ($request->status === 'approved') {
                    Mail::send('emails.user-approved', ['user' => $user], function ($message) use ($user) {
                        $message->to($user->email, $user->name)
                                ->subject('Вашият акаунт е одобрен - AI Tools Platform');
                    });
                } elseif ($request->status === 'rejected') {
                    Mail::send('emails.user-rejected', ['user' => $user], function ($message) use ($user) {
                        $message->to($user->email, $user->name)
                                ->subject('Вашият акаунт е отхвърлен - AI Tools Platform');
                    });
                }
            } catch (\Exception $e) {
                \Log::warning('Failed to send user status email', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the request if email fails
            }
        }

        // Clear admin cache
        $this->clearAdminCache();

        try {
            return response()->json([
                'success' => true,
                'message' => "User {$request->status} successfully",
                'data' => new UserResource($user),
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to return user resource', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Fallback response if UserResource fails
            return response()->json([
                'success' => true,
                'message' => "User {$request->status} successfully",
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                ],
            ]);
        }
    }

    /**
     * Update user role
     */
    public function updateUserRole(UpdateUserRoleRequest $request, User $user, ActivityLogService $activityLogService): JsonResponse
    {
        $oldRole = $user->role;

        DB::transaction(function () use ($request, $user, $oldRole, $activityLogService) {
            $user->role = $request->role;
            $user->save();

            // Log activity
            $activityLogService->log('updated', $user, "User role changed from {$oldRole} to {$request->role}");
        });

        // Clear admin cache
        $this->clearAdminCache();

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Export users to CSV
     */
    public function exportUsers(Request $request)
    {
        // Check authorization
        if (!Gate::allows('exportData', User::class)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only owners can export data.',
            ], 403);
        }

        $query = User::query();

        // Apply same filters as users() method
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        $filename = 'users_export_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($users) {
            $file = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Headers
            fputcsv($file, ['ID', 'Име', 'Email', 'Роля', 'Статус', 'Регистриран на', 'Обновен на']);

            // Data
            foreach ($users as $user) {
                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->role,
                    $user->status,
                    $user->created_at->format('Y-m-d H:i:s'),
                    $user->updated_at->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Clear tools cache intelligently
     */
    private function clearToolsCache(): void
    {
        try {
            // Try to use cache tags if available (Redis)
            if (method_exists(Cache::getStore(), 'tags')) {
                Cache::tags(['tools'])->flush();
            } else {
                // Fallback: clear common cache keys
                $commonFilters = [
                    [],
                    ['status' => 'active'],
                    ['status' => 'pending_review'],
                    ['status' => 'inactive'],
                ];

                foreach ($commonFilters as $filters) {
                    $cacheKey = 'tools_count_' . md5(json_encode($filters));
                    Cache::forget($cacheKey);
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to clear tools cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear admin cache intelligently
     */
    private function clearAdminCache(): void
    {
        try {
            // Clear admin statistics cache
            Cache::forget('admin_statistics');

            // Also clear tools cache since user changes might affect tool visibility
            $this->clearToolsCache();
        } catch (\Exception $e) {
            \Log::warning('Failed to clear admin cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

