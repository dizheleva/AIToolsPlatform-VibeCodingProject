<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\AiTool;
use App\Services\TwoFactorService;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ApiLoginRequest;
use App\Http\Requests\ApiRegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/dashboard');
        }

        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => 'pending', // Новите потребители са pending до одобрение
        ]);

        Auth::login($user);

        return redirect('/dashboard');
    }

    public function dashboard()
    {
        $user = Auth::user();

        // Определи ролята за показване
        $displayRole = $user->status === 'approved' ? $user->role : 'employee';

        return view('dashboard', compact('user', 'displayRole'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    // API Methods for Next.js frontend
    public function apiLogin(ApiLoginRequest $request, TwoFactorService $twoFactorService): JsonResponse
    {
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();

            // Check if 2FA is enabled
            if ($user->two_factor_enabled) {
                // If 2FA code is provided, verify it
                if ($request->has('two_factor_code')) {
                    $valid = $twoFactorService->verifyCode($user, $request->two_factor_code);

                    if (!$valid) {
                        Auth::logout();
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid 2FA code',
                            'requires_2fa' => true,
                        ], 401);
                    }
                } else {
                    // Generate and send code
                    try {
                        $twoFactorService->generateCode($user);

                        Auth::logout();
                        return response()->json([
                            'success' => false,
                            'message' => '2FA code required',
                            'requires_2fa' => true,
                            'two_factor_type' => $user->two_factor_type,
                        ], 200);
                    } catch (\Exception $e) {
                        Auth::logout();
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to send 2FA code: ' . $e->getMessage(),
                            'requires_2fa' => true,
                        ], 500);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'user' => new UserResource($user),
                'message' => 'Login successful'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    public function apiRegister(ApiRegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => 'pending',
            'email_verified_at' => now(),
        ]);

        Auth::login($user);

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
            'message' => 'Registration successful. Your account is pending approval.'
        ], 201);
    }

    public function apiDashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'user' => new UserResource($user),
        ]);
    }

    public function apiDashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $isOwner = $user->role === 'owner' && $user->status === 'approved';

        // Get total tools count (all for owner, only active for others)
        $totalToolsQuery = AiTool::query();
        if (!$isOwner) {
            $totalToolsQuery->where('status', 'active');
        }
        $totalTools = $totalToolsQuery->count();

        // Get total views (sum of views_count)
        $totalViews = AiTool::sum('views_count') ?? 0;

        // Get total likes (sum of likes_count)
        $totalLikes = AiTool::sum('likes_count') ?? 0;

        // Get last activity (most recently created or updated tool)
        $lastActivity = AiTool::orderBy('updated_at', 'desc')
            ->first();

        $lastActivityText = 'Няма активност';
        if ($lastActivity) {
            $lastActivityDate = $lastActivity->updated_at;
            $daysAgo = (int) floor(now()->diffInDays($lastActivityDate, false));

            // If negative, it means the date is in the future (shouldn't happen, but handle it)
            if ($daysAgo < 0) {
                $lastActivityText = 'Днес';
            } elseif ($daysAgo === 0) {
                $lastActivityText = 'Днес';
            } elseif ($daysAgo === 1) {
                $lastActivityText = 'Вчера';
            } elseif ($daysAgo < 7) {
                $lastActivityText = "Преди {$daysAgo} дни";
            } elseif ($daysAgo < 30) {
                $weeksAgo = (int) floor($daysAgo / 7);
                $lastActivityText = "Преди {$weeksAgo} " . ($weeksAgo === 1 ? 'седмица' : 'седмици');
            } else {
                $monthsAgo = (int) floor($daysAgo / 30);
                $lastActivityText = "Преди {$monthsAgo} " . ($monthsAgo === 1 ? 'месец' : 'месеца');
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_tools' => $totalTools,
                'total_views' => (int) $totalViews,
                'total_likes' => (int) $totalLikes,
                'last_activity' => $lastActivityText,
            ]
        ]);
    }

    public function apiUserStats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get tools created by this user
        $createdToolsCount = AiTool::where('created_by', $user->id)->count();

        // Get tools liked by this user
        $likedToolsCount = $user->likedTools()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'created_tools' => $createdToolsCount,
                'liked_tools' => $likedToolsCount,
            ]
        ]);
    }

    public function apiLogout(Request $request): JsonResponse
    {
        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Update user profile
     */
    public function apiUpdateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Update only provided fields
        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }

        $user->save();
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Профилът беше актуализиран успешно.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Upload avatar
     */
    public function apiUploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // Max 2MB
        ], [
            'avatar.required' => 'Моля, изберете изображение.',
            'avatar.image' => 'Файлът трябва да е изображение.',
            'avatar.mimes' => 'Изображението трябва да е в формат: jpeg, png, jpg, gif или webp.',
            'avatar.max' => 'Изображението не може да бъде по-голямо от 2MB.',
        ]);

        $user = $request->user();

        // Delete old avatar if exists
        if ($user->avatar_url) {
            // Extract path from URL (remove domain if present)
            $oldPath = parse_url($user->avatar_url, PHP_URL_PATH);
            if ($oldPath && str_starts_with($oldPath, '/storage/avatars/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $oldPath));
            }
        }

        // Store new avatar
        $avatarPath = $request->file('avatar')->store('avatars', 'public');
        $avatarUrl = Storage::url($avatarPath);

        // Update user
        $user->avatar_url = $avatarUrl;
        $user->save();
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Аватарът беше качен успешно.',
            'avatar_url' => $avatarUrl,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Delete avatar
     */
    public function apiDeleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_url) {
            // Extract path from URL
            $oldPath = parse_url($user->avatar_url, PHP_URL_PATH);
            if ($oldPath && str_starts_with($oldPath, '/storage/avatars/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $oldPath));
            }

            $user->avatar_url = null;
            $user->save();
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Аватарът беше изтрит успешно.',
                'user' => new UserResource($user),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Няма аватар за изтриване.',
        ], 404);
    }

    /**
     * Change password
     */
    public function apiChangePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Verify current password
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Текущата парола е неправилна.',
                'errors' => [
                    'current_password' => ['Текущата парола е неправилна.'],
                ],
            ], 422);
        }

        // Update password
        $user->password = $validated['password']; // Will be hashed automatically by the model
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Паролата беше променена успешно.',
        ]);
    }
}
