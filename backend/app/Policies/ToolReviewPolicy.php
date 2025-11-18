<?php

namespace App\Policies;

use App\Models\ToolReview;
use App\Models\User;

class ToolReviewPolicy
{
    /**
     * Determine whether the user can view any reviews.
     */
    public function viewAny(User $user): bool
    {
        return true; // All users can view reviews
    }

    /**
     * Determine whether the user can view the review.
     */
    public function view(User $user, ToolReview $review): bool
    {
        return true; // All users can view reviews
    }

    /**
     * Determine whether the user can create reviews.
     */
    public function create(User $user): bool
    {
        // Only approved users can create reviews
        return $user->status === 'approved';
    }

    /**
     * Determine whether the user can update the review.
     */
    public function update(User $user, ToolReview $review): bool
    {
        // User can update their own review
        return $review->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the review.
     */
    public function delete(User $user, ToolReview $review): bool
    {
        // Owner can delete any review
        if ($user->role === 'owner' && $user->status === 'approved') {
            return true;
        }

        // User can delete their own review
        return $review->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the review.
     */
    public function restore(User $user, ToolReview $review): bool
    {
        // Only owners can restore
        return $user->role === 'owner' && $user->status === 'approved';
    }

    /**
     * Determine whether the user can permanently delete the review.
     */
    public function forceDelete(User $user, ToolReview $review): bool
    {
        // Only owners can permanently delete
        return $user->role === 'owner' && $user->status === 'approved';
    }
}

