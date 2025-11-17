<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_routes_are_loaded(): void
    {
        // Test if routes are registered
        $routes = \Illuminate\Support\Facades\Route::getRoutes();
        
        $has2faRoute = false;
        $hasAdminRoute = false;
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            if (str_contains($uri, '2fa')) {
                $has2faRoute = true;
            }
            if (str_contains($uri, 'admin')) {
                $hasAdminRoute = true;
            }
        }
        
        $this->assertTrue($has2faRoute, '2FA routes are not registered');
        $this->assertTrue($hasAdminRoute, 'Admin routes are not registered');
    }
}

