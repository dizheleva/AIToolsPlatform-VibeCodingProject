<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiToolResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'url' => $this->url,
            'logo_url' => $this->logo_url,
            'pricing_model' => $this->pricing_model,
            'status' => $this->status,
            'featured' => $this->featured,
            'views_count' => $this->views_count,
            'likes_count' => $this->likes_count,
            'documentation_url' => $this->documentation_url,
            'github_url' => $this->github_url,
            'tags' => $this->tags,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
            'updater' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email,
                ];
            }),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'roles' => $this->whenLoaded('toolRoles', function () {
                return $this->toolRoles->pluck('role')->toArray();
            }),
            
            // Additional computed fields
            'is_liked' => $this->when(isset($this->is_liked), $this->is_liked),
            'average_rating' => $this->when(
                $this->relationLoaded('reviews'),
                fn() => round($this->reviews->avg('rating') ?? 0, 2)
            ),
            'reviews_count' => $this->when(
                $this->relationLoaded('reviews'),
                fn() => $this->reviews->count()
            ),
        ];
    }
}

