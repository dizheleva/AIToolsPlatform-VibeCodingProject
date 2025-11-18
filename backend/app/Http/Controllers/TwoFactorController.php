<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorService;
use App\Http\Requests\SetupTwoFactorRequest;
use App\Http\Requests\VerifyTwoFactorRequest;
use App\Http\Requests\DisableTwoFactorRequest;
use App\Http\Resources\TwoFactorResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    public function setup(SetupTwoFactorRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $type = $request->type;

            if ($type === 'google_authenticator') {
                $secret = $this->twoFactorService->generateGoogleSecret($user);
                
                DB::transaction(function () use ($user, $type, $secret) {
                    $user->two_factor_type = $type;
                    $user->two_factor_enabled = false;
                    $user->two_factor_verified_at = null;
                    $user->two_factor_secret = $secret;
                    $user->save();
                });

                $qrCodeUrl = $this->twoFactorService->getQRCodeUrl($user, $secret);

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

            DB::transaction(function () use ($user, $type, $request) {
                $user->two_factor_type = $type;
                $user->two_factor_enabled = false;
                $user->two_factor_verified_at = null;
                $user->two_factor_secret = null;
                
                if ($type === 'telegram') {
                    $user->telegram_chat_id = $request->telegram_chat_id;
                }
                
                $user->save();
            });

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
    public function verify(VerifyTwoFactorRequest $request): JsonResponse
    {
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

        DB::transaction(function () use ($user) {
            $user->two_factor_enabled = true;
            $user->two_factor_verified_at = now();
            $user->save();
        });

        // Refresh user to get latest data
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => '2FA enabled successfully',
            'data' => new TwoFactorResource($user),
        ]);
    }

    /**
     * Disable 2FA
     */
    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        $user = Auth::user();

        // Verify code before disabling
        $valid = $this->twoFactorService->verifyCode($user, $request->code);

        if (!$valid) {
            throw ValidationException::withMessages([
                'code' => ['Invalid verification code.'],
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->two_factor_enabled = false;
            $user->two_factor_type = 'none';
            $user->two_factor_secret = null;
            $user->telegram_chat_id = null;
            $user->two_factor_verified_at = null;
            $user->save();
        });

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
                'data' => new TwoFactorResource($user),
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

