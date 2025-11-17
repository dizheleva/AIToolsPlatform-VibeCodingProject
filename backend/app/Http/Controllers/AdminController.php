<?php

namespace App\Http\Controllers;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 20);
            $tools = $query->paginate($perPage);

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
        } catch (\Exception $e) {
            \Log::error('AdminController::tools error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Approve or reject a tool
     */
    public function approveTool(Request $request, AiTool $aiTool, ActivityLogService $activityLogService): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:active,inactive,pending_review',
        ]);

        $oldStatus = $aiTool->status;
        $aiTool->status = $request->status;
        $aiTool->updated_by = auth()->id();
        $aiTool->save();

        // Log activity
        $action = $request->status === 'active' ? 'approved' : ($request->status === 'inactive' ? 'rejected' : 'updated');
        $activityLogService->log($action, $aiTool, "Tool status changed from {$oldStatus} to {$request->status}");

        // Clear cache
        Cache::flush();

        return response()->json([
            'success' => true,
            'message' => "Tool {$request->status} successfully",
            'data' => $aiTool->load(['creator', 'updater', 'categories', 'toolRoles']),
        ]);
    }

    /**
     * Get all pending tools
     */
    public function pendingTools(): JsonResponse
    {
        $tools = AiTool::where('status', 'pending_review')
            ->with(['creator', 'categories', 'toolRoles'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tools,
            'count' => $tools->count(),
        ]);
    }

    /**
     * Get statistics
     */
    public function statistics(): JsonResponse
    {
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

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 20);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $users->items(),
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
    public function createUser(Request $request, ActivityLogService $activityLogService): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:employee,backend,frontend,qa,pm,designer,owner',
            'status' => 'nullable|in:pending,approved,rejected',
        ]);

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

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'created_at' => $user->created_at,
            ],
        ]);
    }

    /**
     * Get user details
     */
    public function userDetails(User $user): JsonResponse
    {
        $user->load(['createdTools', 'likedTools']);
        
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'created_tools_count' => $user->createdTools()->count(),
                'liked_tools_count' => $user->likedTools()->count(),
                'created_tools' => $user->createdTools()->latest()->limit(10)->get(['id', 'name', 'slug', 'status', 'created_at']),
            ],
        ]);
    }

    /**
     * Approve or reject a user
     */
    public function approveUser(Request $request, User $user, ActivityLogService $activityLogService): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:approved,rejected,pending',
        ]);

        $oldStatus = $user->status;
        $user->status = $request->status;
        $user->save();

        // Log activity
        $action = $request->status === 'approved' ? 'approved' : ($request->status === 'rejected' ? 'rejected' : 'updated');
        $activityLogService->log($action, $user, "User status changed from {$oldStatus} to {$request->status}");

        // Send email notification
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
        }

        return response()->json([
            'success' => true,
            'message' => "User {$request->status} successfully",
            'data' => $user,
        ]);
    }

    /**
     * Update user role
     */
    public function updateUserRole(Request $request, User $user, ActivityLogService $activityLogService): JsonResponse
    {
        $request->validate([
            'role' => 'required|in:employee,backend,frontend,qa,pm,designer,owner',
        ]);

        $oldRole = $user->role;
        $user->role = $request->role;
        $user->save();

        // Log activity
        $activityLogService->log('updated', $user, "User role changed from {$oldRole} to {$request->role}");

        return response()->json([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => $user,
        ]);
    }

    /**
     * Export users to CSV
     */
    public function exportUsers(Request $request)
    {
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
}

