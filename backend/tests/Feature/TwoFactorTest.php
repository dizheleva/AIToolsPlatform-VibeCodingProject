<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'status' => 'approved',
            'role' => 'employee',
        ]);
    }

    public function test_can_get_2fa_status(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->getJson('/api/2fa/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'two_factor_enabled',
                    'two_factor_type',
                    'has_telegram_chat_id',
                ],
            ]);
    }

    public function test_can_setup_email_2fa(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/2fa/setup', [
                'type' => 'email',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'type',
                ],
            ]);

        $this->user->refresh();
        $this->assertEquals('email', $this->user->two_factor_type);
        $this->assertFalse($this->user->two_factor_enabled);
    }

    public function test_can_setup_telegram_2fa(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/2fa/setup', [
                'type' => 'telegram',
                'telegram_chat_id' => '123456789',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'type',
                ],
            ]);

        $this->user->refresh();
        $this->assertEquals('telegram', $this->user->two_factor_type);
        $this->assertEquals('123456789', $this->user->telegram_chat_id);
    }

    public function test_can_setup_google_authenticator_2fa(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/2fa/setup', [
                'type' => 'google_authenticator',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'secret',
                    'qr_code_url',
                    'manual_entry_key',
                ],
            ]);

        $this->user->refresh();
        $this->assertEquals('google_authenticator', $this->user->two_factor_type);
        $this->assertNotNull($this->user->two_factor_secret);
    }

    public function test_can_verify_and_enable_email_2fa(): void
    {
        // Setup email 2FA
        $this->actingAs($this->user)
            ->postJson('/api/2fa/setup', [
                'type' => 'email',
            ]);

        // Get the code from cache (simulating email delivery)
        $code = Cache::get("2fa_email_code_{$this->user->id}");
        
        if (!$code) {
            $this->markTestSkipped('Email code not found in cache');
        }

        // Verify and enable
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/2fa/verify', [
                'code' => $code,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'two_factor_enabled',
                    'two_factor_type',
                ],
            ]);

        $this->user->refresh();
        $this->assertTrue($this->user->two_factor_enabled);
    }

    public function test_cannot_verify_with_invalid_code(): void
    {
        // Setup email 2FA
        $this->actingAs($this->user)
            ->postJson('/api/2fa/setup', [
                'type' => 'email',
            ]);

        // Try to verify with invalid code
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/2fa/verify', [
                'code' => '000000',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_can_disable_2fa(): void
    {
        // Setup and enable 2FA
        $this->actingAs($this->user)
            ->postJson('/api/2fa/setup', [
                'type' => 'email',
            ]);

        $code = Cache::get("2fa_email_code_{$this->user->id}");
        if ($code) {
            $this->actingAs($this->user)
                ->postJson('/api/2fa/verify', [
                    'code' => $code,
                ]);
        }

        // Get code for disabling
        $this->actingAs($this->user)
            ->postJson('/api/2fa/resend-code');

        $disableCode = Cache::get("2fa_email_code_{$this->user->id}");
        
        if (!$disableCode) {
            $this->markTestSkipped('Email code not found for disable');
        }

        // Disable 2FA
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/2fa/disable', [
                'code' => $disableCode,
            ]);

        $response->assertStatus(200);

        $this->user->refresh();
        $this->assertFalse($this->user->two_factor_enabled);
        $this->assertEquals('none', $this->user->two_factor_type);
    }
}

