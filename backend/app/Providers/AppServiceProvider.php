<?php

namespace App\Providers;

use App\Models\AiTool;
use App\Models\Category;
use App\Models\ToolReview;
use App\Models\User;
use App\Policies\AdminPolicy;
use App\Policies\AiToolPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ToolReviewPolicy;
use Illuminate\Support\Facades\Gate;
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
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        AiTool::class => AiToolPolicy::class,
        Category::class => CategoryPolicy::class,
        ToolReview::class => ToolReviewPolicy::class,
    ];

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(AiTool::class, AiToolPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(ToolReview::class, ToolReviewPolicy::class);

        // Register AdminPolicy abilities
        $adminPolicy = new AdminPolicy();
        Gate::define('manageTools', [$adminPolicy, 'manageTools']);
        Gate::define('manageUsers', [$adminPolicy, 'manageUsers']);
        Gate::define('createUser', [$adminPolicy, 'createUser']);
        Gate::define('updateUserRole', [$adminPolicy, 'updateUserRole']);
        Gate::define('approveUser', [$adminPolicy, 'approveUser']);
        Gate::define('viewStatistics', [$adminPolicy, 'viewStatistics']);
        Gate::define('exportData', [$adminPolicy, 'exportData']);

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
