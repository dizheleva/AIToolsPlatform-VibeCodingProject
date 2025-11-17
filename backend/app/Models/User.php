<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'status',
        'password',
        'two_factor_type',
        'two_factor_secret',
        'telegram_chat_id',
        'two_factor_enabled',
        'two_factor_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_enabled' => 'boolean',
            'two_factor_verified_at' => 'datetime',
        ];
    }

    /**
     * Get the AI tools created by this user.
     */
    public function createdTools(): HasMany
    {
        return $this->hasMany(AiTool::class, 'created_by');
    }

    /**
     * Get the AI tools liked by this user.
     */
    public function likedTools(): BelongsToMany
    {
        return $this->belongsToMany(AiTool::class, 'ai_tool_likes')
                    ->withTimestamps();
    }

    /**
     * Get the reviews written by this user.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ToolReview::class);
    }

    /**
     * Get the effective display role based on status.
     */
    public function getDisplayRoleAttribute(): string
    {
        return $this->status === 'approved' ? $this->role : 'employee';
    }
}
