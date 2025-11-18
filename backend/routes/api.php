<?php

use App\Models\AiTool;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Route model binding for API routes
Route::bind('aiTool', function ($value) {
    return AiTool::where('slug', $value)->orWhere('id', $value)->firstOrFail();
});

Route::bind('user', function ($value) {
    return \App\Models\User::findOrFail($value);
});

Route::bind('review', function ($value) {
    return \App\Models\ToolReview::findOrFail($value);
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth API routes (using web middleware for session support)
// Rate limiting: 5 attempts per minute per IP (brute force protection)
Route::middleware(['web', 'guest'])->group(function () {
    Route::post('/login', [AuthController::class, 'apiLogin'])
        ->middleware('throttle:5,1');
    Route::post('/register', [AuthController::class, 'apiRegister'])
        ->middleware('throttle:5,1');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'success' => true,
            'user' => new \App\Http\Resources\UserResource($user),
        ]);
    });
    Route::get('/dashboard', [AuthController::class, 'apiDashboard']);
    Route::get('/dashboard/stats', [AuthController::class, 'apiDashboardStats']);
    Route::get('/user/stats', [AuthController::class, 'apiUserStats']);
    Route::put('/user/profile', [AuthController::class, 'apiUpdateProfile']);
    Route::post('/user/avatar', [AuthController::class, 'apiUploadAvatar']);
    Route::delete('/user/avatar', [AuthController::class, 'apiDeleteAvatar']);
    Route::post('/user/change-password', [AuthController::class, 'apiChangePassword']);
    Route::post('/logout', [AuthController::class, 'apiLogout']);

    // 2FA routes
    Route::prefix('2fa')->group(function () {
        Route::get('/status', [\App\Http\Controllers\TwoFactorController::class, 'status']);
        Route::post('/setup', [\App\Http\Controllers\TwoFactorController::class, 'setup']);
        Route::post('/verify', [\App\Http\Controllers\TwoFactorController::class, 'verify']);
        Route::post('/disable', [\App\Http\Controllers\TwoFactorController::class, 'disable']);
        Route::post('/resend-code', [\App\Http\Controllers\TwoFactorController::class, 'resendCode']);
    });

    // Admin routes
    Route::prefix('admin')->middleware('role:owner')->group(function () {
        Route::get('/tools', [\App\Http\Controllers\AdminController::class, 'tools']);
        Route::get('/tools/pending', [\App\Http\Controllers\AdminController::class, 'pendingTools']);
        Route::post('/tools/{aiTool}/approve', [\App\Http\Controllers\AdminController::class, 'approveTool']);
        Route::get('/users', [\App\Http\Controllers\AdminController::class, 'users']);
        Route::post('/users', [\App\Http\Controllers\AdminController::class, 'createUser']);
        Route::get('/users/export', [\App\Http\Controllers\AdminController::class, 'exportUsers']);
        Route::get('/users/{user}', [\App\Http\Controllers\AdminController::class, 'userDetails']);
        Route::post('/users/{user}/approve', [\App\Http\Controllers\AdminController::class, 'approveUser']);
        Route::put('/users/{user}/role', [\App\Http\Controllers\AdminController::class, 'updateUserRole']);
        Route::get('/statistics', [\App\Http\Controllers\AdminController::class, 'statistics']);
        Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index']);
    });
});
