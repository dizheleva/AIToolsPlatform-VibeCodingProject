<?php

namespace Tests\Unit;

use App\Http\Requests\StoreAiToolRequest;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreAiToolRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $request = new StoreAiToolRequest();
        $rules = $request->rules();

        $validator = Validator::make([], $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
        $this->assertArrayHasKey('url', $validator->errors()->toArray());
        $this->assertArrayHasKey('pricing_model', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_name_max_length(): void
    {
        $request = new StoreAiToolRequest();
        $rules = $request->rules();

        $data = [
            'name' => str_repeat('a', 256), // Exceeds max 255
            'url' => 'https://example.com',
            'pricing_model' => 'free',
        ];

        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_url_format(): void
    {
        $request = new StoreAiToolRequest();
        $rules = $request->rules();

        $data = [
            'name' => 'Test Tool',
            'url' => 'not-a-valid-url',
            'pricing_model' => 'free',
        ];

        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('url', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_pricing_model_enum(): void
    {
        $request = new StoreAiToolRequest();
        $rules = $request->rules();

        $data = [
            'name' => 'Test Tool',
            'url' => 'https://example.com',
            'pricing_model' => 'invalid-model',
        ];

        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pricing_model', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_category_ids_exist(): void
    {
        $request = new StoreAiToolRequest();
        $rules = $request->rules();

        $data = [
            'name' => 'Test Tool',
            'url' => 'https://example.com',
            'pricing_model' => 'free',
            'category_ids' => [99999], // Non-existent category
        ];

        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category_ids.0', $validator->errors()->toArray());
    }

    #[Test]
    public function it_validates_roles_enum(): void
    {
        $request = new StoreAiToolRequest();
        $rules = $request->rules();

        $data = [
            'name' => 'Test Tool',
            'url' => 'https://example.com',
            'pricing_model' => 'free',
            'roles' => ['invalid-role'],
        ];

        $validator = Validator::make($data, $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('roles.0', $validator->errors()->toArray());
    }

    #[Test]
    public function it_accepts_valid_data(): void
    {
        $category = Category::factory()->create();

        $request = new StoreAiToolRequest();
        $rules = $request->rules();

        $data = [
            'name' => 'Test Tool',
            'description' => 'A test tool description',
            'short_description' => 'Short desc',
            'url' => 'https://example.com',
            'logo_url' => 'https://example.com/logo.png',
            'pricing_model' => 'free',
            'status' => 'active',
            'featured' => false,
            'category_ids' => [$category->id],
            'roles' => ['backend', 'frontend'],
            'tags' => ['ai', 'code'],
            'documentation_url' => 'https://docs.example.com',
            'github_url' => 'https://github.com/example',
        ];

        $validator = Validator::make($data, $rules);

        $this->assertFalse($validator->fails());
    }
}

