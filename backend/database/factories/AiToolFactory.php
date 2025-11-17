<?php

namespace Database\Factories;

use App\Models\AiTool;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiTool>
 */
class AiToolFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);
        
        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->randomNumber(4),
            'description' => fake()->paragraph(),
            'short_description' => fake()->sentence(),
            'url' => fake()->url(),
            'logo_url' => fake()->imageUrl(200, 200),
            'pricing_model' => fake()->randomElement(['free', 'freemium', 'paid', 'enterprise']),
            'status' => fake()->randomElement(['active', 'inactive', 'pending_review']),
            'featured' => fake()->boolean(20), // 20% chance of being featured
            'created_by' => User::factory(),
            'updated_by' => null,
            'views_count' => fake()->numberBetween(0, 10000),
            'likes_count' => fake()->numberBetween(0, 500),
            'documentation_url' => fake()->optional()->url(),
            'github_url' => fake()->optional()->url(),
            'tags' => fake()->words(5),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the tool is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the tool is pending review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_review',
        ]);
    }

    /**
     * Indicate that the tool is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'featured' => true,
        ]);
    }
}

