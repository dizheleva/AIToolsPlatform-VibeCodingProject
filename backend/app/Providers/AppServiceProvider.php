<?php

namespace App\Providers;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Route model binding for API routes
        Route::bind('aiTool', function ($value) {
            return AiTool::where('slug', $value)->orWhere('id', $value)->firstOrFail();
        });

        Route::bind('category', function ($value) {
            return Category::where('slug', $value)->orWhere('id', $value)->firstOrFail();
        });

        Route::bind('user', function ($value) {
            return User::where('id', $value)->firstOrFail();
        });
    }
}
