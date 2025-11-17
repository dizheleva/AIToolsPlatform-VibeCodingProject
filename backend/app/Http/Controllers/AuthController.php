<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\AiTool;
use App\Services\TwoFactorService;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

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

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:employee,backend,frontend,qa,pm,designer',
        ]);

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
    public function apiLogin(Request $request, TwoFactorService $twoFactorService)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'two_factor_code' => 'nullable|string|size:6',
        ]);

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

            // Determine display role based on status
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
                    'created_at' => $user->created_at,
                ],
                'message' => 'Login successful'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid credentials'
        ], 401);
    }

    public function apiRegister(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:employee,backend,frontend,qa,pm,designer',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => 'pending',
            'email_verified_at' => now(),
        ]);

        Auth::login($user);

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
                'created_at' => $user->created_at,
            ],
            'message' => 'Registration successful. Your account is pending approval.'
        ]);
    }

    public function apiDashboard(Request $request)
    {
        $user = $request->user();
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
                'created_at' => $user->created_at,
            ]
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

    public function apiLogout(Request $request)
    {
        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }
}
