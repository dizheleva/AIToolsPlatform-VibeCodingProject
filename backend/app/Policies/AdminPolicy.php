<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    /**
     * Determine whether the user can access admin panel.
     */
    public function accessAdmin(User $user): bool
    {
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine whether the user can manage tools (approve/reject).
     */
    public function manageTools(User $user): bool
    {
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine whether the user can manage users.
     */
    public function manageUsers(User $user): bool
    {
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine whether the user can create users.
     */
    public function createUser(User $user): bool
    {
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine whether the user can update user roles.
     */
    public function updateUserRole(User $user): bool
    {
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine whether the user can approve/reject users.
     */
    public function approveUser(User $user): bool
    {
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine whether the user can view statistics.
     */
    public function viewStatistics(User $user): bool
    {
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine whether the user can export data.
     */
    public function exportData(User $user): bool
    {
        return $user->role === 'owner' && $user->status === 'approved';
    }
}

