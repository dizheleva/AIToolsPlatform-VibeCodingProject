<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ToolReview;

class AiTool extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AiToolFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'url',
        'logo_url',
        'pricing_model',
        'status',
        'featured',
        'created_by',
        'updated_by',
        'views_count',
        'likes_count',
        'documentation_url',
        'github_url',
        'tags',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get the user who created this tool.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this tool.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the categories for this tool.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'ai_tool_category')
                    ->withTimestamps();
    }

    /**
     * Get the roles associated with this tool.
     * Note: This uses a custom pivot table with role as string, not a model.
     * We'll use a direct relationship to the pivot table.
     */
    public function toolRoles()
    {
        return $this->hasMany(AiToolRole::class, 'ai_tool_id');
    }

    /**
     * Get the role names as an array.
     */
    public function getRolesAttribute(): array
    {
        return $this->toolRoles()->pluck('role')->toArray();
    }

    /**
     * Sync roles for this tool.
     * Optimized to only add/remove roles that actually changed.
     */
    public function syncRoles(array $roles): void
    {
        $existingRoles = $this->toolRoles()->pluck('role')->toArray();
        $rolesToAdd = array_diff($roles, $existingRoles);
        $rolesToRemove = array_diff($existingRoles, $roles);

        // Only remove roles that need to be removed
        if (!empty($rolesToRemove)) {
            $this->toolRoles()->whereIn('role', $rolesToRemove)->delete();
        }

        // Only add roles that need to be added
        foreach ($rolesToAdd as $role) {
            AiToolRole::create([
                'ai_tool_id' => $this->id,
                'role' => $role,
            ]);
        }
    }

    /**
     * Get the users who liked this tool.
     */
    public function likedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ai_tool_likes')
                    ->withTimestamps();
    }

    /**
     * Get the reviews for this tool.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ToolReview::class, 'ai_tool_id');
    }

    /**
     * Get the average rating for this tool.
     */
    public function getAverageRatingAttribute(): float
    {
        return $this->reviews()->avg('rating') ?? 0.0;
    }

    /**
     * Get the total number of reviews for this tool.
     */
    public function getReviewsCountAttribute(): int
    {
        return $this->reviews()->count();
    }

    /**
     * Check if a specific role is associated with this tool.
     */
    public function hasRole(string $role): bool
    {
        return $this->toolRoles()->where('role', $role)->exists();
    }

    /**
     * Scope a query to only include active tools.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include featured tools.
     */
    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    /**
     * Scope a query to filter by role.
     */
    public function scopeForRole($query, string $role)
    {
        return $query->whereHas('toolRoles', function ($q) use ($role) {
            $q->where('role', $role);
        });
    }

    /**
     * Increment views count.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
        $this->refresh();
    }

    /**
     * Increment likes count.
     */
    public function incrementLikes(): void
    {
        $this->increment('likes_count');
        $this->refresh();
    }

    /**
     * Decrement likes count.
     */
    public function decrementLikes(): void
    {
        $this->decrement('likes_count');
        $this->refresh();
    }
}

