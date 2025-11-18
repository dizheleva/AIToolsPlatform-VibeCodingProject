<?php

namespace App\Policies;

use App\Models\AiTool;
use App\Models\User;

class AiToolPolicy
{
    /**
     * Determine if the user can view any tools.
     */
    public function viewAny(User $user): bool
    {
        // Everyone can view tools (handled by route middleware)
        return true;
    }

    /**
     * Determine if the user can view the tool.
     */
    public function view(User $user, AiTool $aiTool): bool
    {
        // Everyone can view active tools
        // Only owners can view all tools regardless of status
        if ($aiTool->status === 'active') {
            return true;
        }

        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine if the user can create tools.
     */
    public function create(User $user): bool
    {
        // Only approved users can create tools
        return $user->status === 'approved';
    }

    /**
     * Determine if the user can update the tool.
     */
    public function update(User $user, AiTool $aiTool): bool
    {
        // Owner can always update
        if ($user->role === 'owner' && $user->status === 'approved') {
            return true;
        }

        // Creator can update their own tools if approved
        return $aiTool->created_by === $user->id && $user->status === 'approved';
    }

    /**
     * Determine if the user can delete the tool.
     */
    public function delete(User $user, AiTool $aiTool): bool
    {
        // Owner can always delete
        if ($user->role === 'owner' && $user->status === 'approved') {
            return true;
        }

        // Creator can delete their own tools if approved
        return $aiTool->created_by === $user->id && $user->status === 'approved';
    }

    /**
     * Determine if the user can restore the tool.
     */
    public function restore(User $user, AiTool $aiTool): bool
    {
        // Only owners can restore
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine if the user can permanently delete the tool.
     */
    public function forceDelete(User $user, AiTool $aiTool): bool
    {
        // Only owners can permanently delete
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine if the user can change status and featured.
     */
    public function manageStatus(User $user, AiTool $aiTool): bool
    {
        // Only owners can change status and featured
        return $user->role === 'owner' && $user->status === 'approved';
    }
}

