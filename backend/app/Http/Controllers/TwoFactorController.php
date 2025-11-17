<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    protected TwoFactorService $twoFactorService;

    public function __construct(TwoFactorService $twoFactorService)
    {
        $this->twoFactorService = $twoFactorService;
    }

    /**
     * Setup 2FA for the authenticated user
     */
    public function setup(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|in:email,telegram,google_authenticator',
                'telegram_chat_id' => 'required_if:type,telegram|nullable|string',
            ]);

            $user = Auth::user();
            $type = $request->type;

            $user->two_factor_type = $type;
            $user->two_factor_enabled = false;
            $user->two_factor_verified_at = null;

            if ($type === 'google_authenticator') {
                $secret = $this->twoFactorService->generateGoogleSecret($user);
                $user->two_factor_secret = $secret;
                $qrCodeUrl = $this->twoFactorService->getQRCodeUrl($user, $secret);
                $user->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Google Authenticator setup initiated',
                    'data' => [
                        'secret' => $secret,
                        'qr_code_url' => $qrCodeUrl,
                        'manual_entry_key' => $secret,
                    ],
                ]);
            }

            if ($type === 'telegram') {
                $user->telegram_chat_id = $request->telegram_chat_id;
            }

            $user->two_factor_secret = null;
            $user->save();

            // Generate and send code for verification
            try {
                $code = $this->twoFactorService->generateCode($user);
                
                return response()->json([
                    'success' => true,
                    'message' => match ($type) {
                        'email' => 'Verification code sent to your email',
                        'telegram' => 'Verification code sent to your Telegram',
                        default => 'Code generated',
                    },
                    'data' => [
                        'type' => $type,
                    ],
                ]);
            } catch (\Exception $e) {
                \Log::error('TwoFactorController::setup error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'type' => $type ?? 'unknown',
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send verification code: ' . $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to setup 2FA: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify and enable 2FA
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();

        if (!$user->two_factor_type || $user->two_factor_type === 'none') {
            return response()->json([
                'success' => false,
                'message' => '2FA is not set up. Please set it up first.',
            ], 400);
        }

        $valid = $this->twoFactorService->verifyCode($user, $request->code);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        $user->two_factor_enabled = true;
        $user->two_factor_verified_at = now();
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '2FA enabled successfully',
            'data' => [
                'two_factor_enabled' => true,
                'two_factor_type' => $user->two_factor_type,
            ],
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = Auth::user();

        if (!$user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled.',
            ], 400);
        }

        // Verify code before disabling
        $valid = $this->twoFactorService->verifyCode($user, $request->code);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        $user->two_factor_enabled = false;
        $user->two_factor_type = 'none';
        $user->two_factor_secret = null;
        $user->telegram_chat_id = null;
        $user->two_factor_verified_at = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => '2FA disabled successfully',
        ]);
    }

    /**
     * Get current 2FA status
     */
    public function status(): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'two_factor_enabled' => $user->two_factor_enabled ?? false,
                    'two_factor_type' => $user->two_factor_type ?? 'none',
                    'has_telegram_chat_id' => !empty($user->telegram_chat_id),
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('TwoFactorController::status error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resend verification code
     */
    public function resendCode(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->two_factor_type || $user->two_factor_type === 'none') {
            return response()->json([
                'success' => false,
                'message' => '2FA is not set up.',
            ], 400);
        }

        if ($user->two_factor_type === 'google_authenticator') {
            return response()->json([
                'success' => false,
                'message' => 'Google Authenticator codes are generated by the app.',
            ], 400);
        }

        try {
            $this->twoFactorService->generateCode($user);
            
            return response()->json([
                'success' => true,
                'message' => match ($user->two_factor_type) {
                    'email' => 'Verification code sent to your email',
                    'telegram' => 'Verification code sent to your Telegram',
                    default => 'Code generated',
                },
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification code: ' . $e->getMessage(),
            ], 500);
        }
    }
}

