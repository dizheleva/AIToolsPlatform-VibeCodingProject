<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Log an activity
     */
    public function log(
        string $action,
        ?Model $model = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model?->id,
            'description' => $description ?? $this->generateDescription($action, $model),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Generate a description from action and model
     */
    protected function generateDescription(string $action, ?Model $model): string
    {
        if (!$model) {
            return ucfirst($action);
        }

        $modelName = class_basename($model);
        
        return match ($action) {
            'created' => "Created {$modelName} #{$model->id}",
            'updated' => "Updated {$modelName} #{$model->id}",
            'deleted' => "Deleted {$modelName} #{$model->id}",
            'approved' => "Approved {$modelName} #{$model->id}",
            'rejected' => "Rejected {$modelName} #{$model->id}",
            default => ucfirst($action) . " {$modelName} #{$model->id}",
        };
    }

    /**
     * Get activity logs with filters
     */
    public function getLogs(array $filters = [], int $perPage = 20, ?string $sortBy = null, ?string $sortOrder = null)
    {
        $query = ActivityLog::with('user');

        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['model_type'])) {
            $query->where('model_type', $filters['model_type']);
        }

        if (isset($filters['model_id'])) {
            $query->where('model_id', $filters['model_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Apply sorting - validate against whitelist
        $allowedSortColumns = ['created_at', 'action', 'model_type'];
        $sortBy = $sortBy && in_array($sortBy, $allowedSortColumns) ? $sortBy : 'created_at';
        $sortOrder = $sortOrder && in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';
        
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }
}

