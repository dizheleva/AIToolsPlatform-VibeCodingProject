<?php

namespace Database\Seeders;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AiToolsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create a user for testing
        $user = User::where('email', 'ivan@admin.local')->first();

        if (!$user) {
            $user = User::first();
        }

        if (!$user) {
            $this->command->error('No users found. Please run UserSeeder first.');
            return;
        }

        // Create categories
        $categories = [
            [
                'name' => 'Code Generation',
                'slug' => 'code-generation',
                'description' => 'Tools for generating and assisting with code',
                'icon' => '💻',
                'color' => '#3B82F6',
                'order' => 1,
            ],
            [
                'name' => 'Text Processing',
                'slug' => 'text-processing',
                'description' => 'Tools for text analysis and processing',
                'icon' => '📝',
                'color' => '#10B981',
                'order' => 2,
            ],
            [
                'name' => 'Image Generation',
                'slug' => 'image-generation',
                'description' => 'AI tools for creating and editing images',
                'icon' => '🎨',
                'color' => '#F59E0B',
                'order' => 3,
            ],
            [
                'name' => 'Testing & QA',
                'slug' => 'testing-qa',
                'description' => 'Tools for testing and quality assurance',
                'icon' => '🧪',
                'color' => '#EF4444',
                'order' => 4,
            ],
            [
                'name' => 'Design Tools',
                'slug' => 'design-tools',
                'description' => 'AI-powered design and UI/UX tools',
                'icon' => '✨',
                'color' => '#8B5CF6',
                'order' => 5,
            ],
        ];

        $createdCategories = [];
        foreach ($categories as $categoryData) {
            $category = Category::updateOrCreate(
                ['slug' => $categoryData['slug']],
                $categoryData
            );
            $createdCategories[$category->slug] = $category;
        }

        // Create sample AI tools
        $tools = [
            [
                'name' => 'GitHub Copilot',
                'slug' => 'github-copilot',
                'description' => 'AI pair programmer that helps you write code faster',
                'short_description' => 'AI-powered code completion tool',
                'url' => 'https://github.com/features/copilot',
                'logo_url' => null,
                'pricing_model' => 'freemium',
                'status' => 'active',
                'featured' => true,
                'categories' => ['code-generation'],
                'roles' => ['backend', 'frontend'],
                'tags' => ['code', 'completion', 'github'],
            ],
            [
                'name' => 'ChatGPT',
                'slug' => 'chatgpt',
                'description' => 'Advanced AI language model for text generation and conversation',
                'short_description' => 'AI chatbot and text generation tool',
                'url' => 'https://chat.openai.com',
                'logo_url' => null,
                'pricing_model' => 'freemium',
                'status' => 'active',
                'featured' => true,
                'categories' => ['text-processing'],
                'roles' => ['backend', 'frontend', 'pm', 'qa'],
                'tags' => ['chat', 'text', 'generation'],
            ],
            [
                'name' => 'Midjourney',
                'slug' => 'midjourney',
                'description' => 'AI art generator for creating stunning images from text prompts',
                'short_description' => 'AI image generation tool',
                'url' => 'https://www.midjourney.com',
                'logo_url' => null,
                'pricing_model' => 'paid',
                'status' => 'active',
                'featured' => false,
                'categories' => ['image-generation', 'design-tools'],
                'roles' => ['designer', 'frontend'],
                'tags' => ['image', 'art', 'generation'],
            ],
            [
                'name' => 'Selenium',
                'slug' => 'selenium',
                'description' => 'Automated testing framework for web applications',
                'short_description' => 'Web automation testing tool',
                'url' => 'https://www.selenium.dev',
                'logo_url' => null,
                'pricing_model' => 'free',
                'status' => 'active',
                'featured' => false,
                'categories' => ['testing-qa'],
                'roles' => ['qa', 'backend'],
                'tags' => ['testing', 'automation', 'qa'],
            ],
        ];

        foreach ($tools as $toolData) {
            $categories = $toolData['categories'];
            $roles = $toolData['roles'];
            $slug = $toolData['slug'];
            unset($toolData['categories'], $toolData['roles']);

            // Create or update the tool
            $tool = AiTool::updateOrCreate(
                ['slug' => $slug],
                [
                    ...$toolData,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]
            );

            // Sync categories (replace existing)
            $categoryIds = [];
            foreach ($categories as $categorySlug) {
                if (isset($createdCategories[$categorySlug])) {
                    $categoryIds[] = $createdCategories[$categorySlug]->id;
                }
            }
            $tool->categories()->sync($categoryIds);

            // Sync roles (replace existing)
            $tool->syncRoles($roles);

            $this->command->info("Created/Updated tool: {$tool->name}");
        }

        $this->command->info('AI Tools seeded successfully!');
    }
}

