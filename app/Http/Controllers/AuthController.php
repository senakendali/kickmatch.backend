<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;  


class AuthController extends Controller
{
    public function login(Request $request)
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

    public function register(Request $request)
    {
        try {
            // Validate input
            $validatedData = $request->validate([
                'person_responsible' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6|confirmed', // password confirmation
            ]);

            // Create new user
            $user = User::create([
                'name' => $request->person_responsible, // Assuming 'name' is for the person's name
                'email' => $request->email,
                'group_id' => 3,
                'password' => Hash::make($request->password), // Hash the password before saving
            ]);

            // Generate a token for the new user
            $token = $user->createToken('auth_token')->plainTextToken;

            // Return the response with user and token
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
