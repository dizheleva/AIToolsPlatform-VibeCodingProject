<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $displayRole = $this->status === 'approved' ? $this->role : 'employee';

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'display_role' => $displayRole,
            'status' => $this->status,
            'avatar_url' => $this->avatar_url,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'two_factor_enabled' => $this->two_factor_enabled ?? false,
            'two_factor_type' => $this->two_factor_type ?? 'none',
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Conditional fields (only for admin or own profile)
            'created_tools_count' => $this->when(
                isset($this->created_tools_count) || $this->relationLoaded('createdTools'),
                isset($this->created_tools_count)
                    ? $this->created_tools_count
                    : ($this->relationLoaded('createdTools') ? $this->createdTools->count() : null)
            ),
            'liked_tools_count' => $this->when(
                isset($this->liked_tools_count) || $this->relationLoaded('likedTools'),
                isset($this->liked_tools_count)
                    ? $this->liked_tools_count
                    : ($this->relationLoaded('likedTools') ? $this->likedTools->count() : null)
            ),
            'created_tools' => $this->when(
                $this->relationLoaded('createdTools'),
                fn() => \App\Http\Resources\AiToolResource::collection($this->createdTools)
            ),
            'liked_tools' => $this->when(
                $this->relationLoaded('likedTools'),
                fn() => \App\Http\Resources\AiToolResource::collection($this->likedTools)
            ),
        ];
    }
}

