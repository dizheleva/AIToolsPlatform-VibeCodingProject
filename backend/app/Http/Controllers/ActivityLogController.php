<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use App\Http\Resources\ActivityLogResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityLogController extends Controller
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * Get activity logs with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate filters
            $validated = $request->validate([
                'user_id' => 'nullable|integer|exists:users,id',
                'action' => 'nullable|string|max:50',
                'model_type' => 'nullable|string|max:255',
                'model_id' => 'nullable|integer',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
                'sort_by' => 'nullable|string|in:created_at,action,model_type',
                'sort_order' => 'nullable|string|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Additional validation: date_to must be after or equal to date_from if both are provided
            if (isset($validated['date_from']) && isset($validated['date_to'])) {
                if (strtotime($validated['date_to']) < strtotime($validated['date_from'])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => [
                            'date_to' => ['The date_to must be after or equal to date_from.'],
                        ],
                    ], 422);
                }
            }

            // Get filters
            $filters = $request->only(['user_id', 'action', 'model_type', 'model_id', 'date_from', 'date_to']);
            
            // Validate and limit per_page
            $perPage = isset($validated['per_page']) 
                ? max(1, min($validated['per_page'], 100)) 
                : 20;

            // Get sort parameters
            $sortBy = $validated['sort_by'] ?? null;
            $sortOrder = $validated['sort_order'] ?? null;

            // Get logs with validated per_page and sorting
            $logs = $this->activityLogService->getLogs($filters, $perPage, $sortBy, $sortOrder);

            return response()->json([
                'success' => true,
                'data' => ActivityLogResource::collection($logs->items()),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            \Log::error('ActivityLogController::index error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

