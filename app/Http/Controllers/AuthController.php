<?php

namespace App\Http\Controllers;

use Illuminate\Http\Requeregisterst;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;  
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login_(Request $request)
    {
        try {
            // Validate input
            $validatedData = $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:6',
            ]);

            // Attempt to find the user
            $user = User::where('email', $request->email)->first();

            // Verify the password
            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                    'errors' => [
                        'email' => !$user ? 'The provided email is not registered.' : null,
                        'password' => $user ? 'The provided password is incorrect.' : null,
                    ],
                ], 401);
            }

            // Generate a token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Return the token and user information
            return response()->json([
                'success' => true,
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:6',
            ]);

            // ✅ ambil relasi kalau ada (opsional)
            $user = User::query()
                ->where('email', $request->email)
                // ->with(['organizer']) // kalau ada relasi organizer()
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            // =========================
            // ✅ ONBOARDING LOGIC FIX
            // =========================
            $dashboardPath = match ((int) $user->role_id) {
                2 => '/eo/dashboard',
                3 => '/manager/dashboard',
                default => '/admin',
            };

            // Default: gak perlu onboarding
            $needsOnboarding = false;

            // Role EO doang yang wajib onboarding
            if ((int) $user->role_id === 2) {
                // ✅ indikator onboarding = sudah punya profile EO (bukan tournament_id)
                // Pilih salah satu sesuai struktur DB lu:
                $needsOnboarding = empty($user->organizer_id)
                    // && empty(optional($user->organizer)->id) // kalau relasi ada
                    ;

                // fallback kalau lu punya flag onboarding_completed:
                if (property_exists($user, 'onboarding_completed') && $user->onboarding_completed) {
                    $needsOnboarding = false;
                }
            }

            // Optional: biar FE gampang, kasih path redirect
            $onboardingPath = '/onboarding?intent=eo';

            return response()->json([
                'success' => true,
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',

                'needs_onboarding' => (bool) $needsOnboarding,
                'dashboard_path'   => $dashboardPath,
                'onboarding_path'  => $onboardingPath,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
            ], 500);
        }
    }



    public function register(Request $request)
    {
        try {
            // Validate input
            $validatedData = $request->validate([
                'person_responsible' => 'required|string|max:255',
                'email'              => 'required|email|unique:users,email',
                'password'           => 'required|min:6|confirmed',
                // intent optional: eo / manager
                'intent'             => 'nullable|string|in:eo,manager',
            ]);

            // Map intent -> group_id & role_id
            // eo => 2, manager => 3 (default)
            $intent = $request->input('intent', 'manager');

            $map = [
                'eo'      => ['group_id' => 2, 'role_id' => 2],
                'manager' => ['group_id' => 3, 'role_id' => 3],
            ];

            $selected = $map[$intent] ?? $map['manager'];

            // Create new user
            $user = User::create([
                'name'     => $request->person_responsible,
                'email'    => $request->email,
                'group_id' => $selected['group_id'],
                'role_id'  => $selected['role_id'],
                'password' => Hash::make($request->password),
            ]);

            // Generate a token for the new user
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success'      => true,
                'user'         => $user,
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }


    public function logout(Request $request)
    {
        try {
            // Revoke the current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while logging out',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
