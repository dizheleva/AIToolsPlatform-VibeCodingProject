<?php

use App\Models\AiTool;
use App\Models\Category;
use Illuminate\Support\Facades\Route;

// Route model binding - use slug instead of id
Route::bind('aiTool', function ($value) {
    return AiTool::where('slug', $value)->orWhere('id', $value)->firstOrFail();
});

Route::bind('category', function ($value) {
    return Category::where('slug', $value)->orWhere('id', $value)->firstOrFail();
});

Route::bind('review', function ($value) {
    return \App\Models\ToolReview::findOrFail($value);
});

Route::get('/', function () {
    return 'Hello from Laravel!';
});

// Test route
Route::get('/test', function () {
    return 'Test route works!';
});

// API routes as per requirements
Route::prefix('api')->group(function () {
    Route::get('/status', function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'API is working',
            'timestamp' => now()
        ]);
    });

    Route::get('/user', function (Illuminate\Http\Request $request) {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $displayRole = $user->status === 'approved' ? $user->role : 'employee';

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'display_role' => $displayRole,
                'status' => $user->status,
            ]
        ]);
    });

    // Auth routes
    Route::post('/login', function (Illuminate\Http\Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = \App\Models\User::where('email', $request->email)->first();

        if ($user && \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            \Illuminate\Support\Facades\Auth::login($user);

            $displayRole = $user->status === 'approved' ? $user->role : 'employee';

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'display_role' => $displayRole,
                    'status' => $user->status,
                ],
                'message' => 'Login successful'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    });

    Route::post('/logout', function (Illuminate\Http\Request $request) {
        \Illuminate\Support\Facades\Auth::logout();
        return response()->json(['success' => true, 'message' => 'Logged out']);
    });

    // Public AI Tools routes (read-only)
    Route::prefix('tools')->group(function () {
        Route::get('/', [\App\Http\Controllers\AiToolController::class, 'index']);
        Route::get('/{aiTool}', [\App\Http\Controllers\AiToolController::class, 'show']);
    });

    // Public Categories routes (read-only)
    Route::prefix('categories')->group(function () {
        Route::get('/', [\App\Http\Controllers\CategoryController::class, 'index']);
        Route::get('/{category}', [\App\Http\Controllers\CategoryController::class, 'show']);
    });

    // Public Reviews routes (read-only)
    Route::prefix('tools/{aiTool}/reviews')->group(function () {
        Route::get('/', [\App\Http\Controllers\ToolReviewController::class, 'index']);
        Route::get('/statistics', [\App\Http\Controllers\ToolReviewController::class, 'statistics']);
    });

    // Protected routes (require authentication)
    Route::middleware('auth')->group(function () {
        // AI Tools protected routes
        Route::prefix('tools')->group(function () {
            Route::post('/', [\App\Http\Controllers\AiToolController::class, 'store']);
            Route::put('/{aiTool}', [\App\Http\Controllers\AiToolController::class, 'update']);
            Route::delete('/{aiTool}', [\App\Http\Controllers\AiToolController::class, 'destroy']);
            // Rate limit like/unlike to prevent spam (10 requests per minute per user)
            Route::post('/{aiTool}/like', [\App\Http\Controllers\AiToolController::class, 'toggleLike'])
                ->middleware('throttle:10,1');
        });

        // Categories protected routes
        Route::prefix('categories')->group(function () {
            Route::post('/', [\App\Http\Controllers\CategoryController::class, 'store']);
            Route::put('/{category}', [\App\Http\Controllers\CategoryController::class, 'update']);
            Route::delete('/{category}', [\App\Http\Controllers\CategoryController::class, 'destroy']);
        });

        // Reviews protected routes
        Route::prefix('tools/{aiTool}/reviews')->group(function () {
            Route::post('/', [\App\Http\Controllers\ToolReviewController::class, 'store']);
            Route::put('/{review}', [\App\Http\Controllers\ToolReviewController::class, 'update']);
            Route::delete('/{review}', [\App\Http\Controllers\ToolReviewController::class, 'destroy']);
        });
    });
});
