<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Services\AuditLogger;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            DB::beginTransaction();

            // Create user with initial balance
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'balance' => '10000.00', // Initial USD balance
            ]);

            // Create initial crypto assets with starter balance for testing
            Asset::create([
                'user_id' => $user->id,
                'symbol' => 'BTC',
                'amount' => '1.00000000', // 1 BTC for testing
                'locked_amount' => '0.00000000',
            ]);

            Asset::create([
                'user_id' => $user->id,
                'symbol' => 'ETH',
                'amount' => '10.00000000', // 10 ETH for testing
                'locked_amount' => '0.00000000',
            ]);

            // Create authentication token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Log the registration
            AuditLogger::log('user_registered', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Registration successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            AuditLogger::log('registration_failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Registration failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request)
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            // Revoke all previous tokens
            $user->tokens()->delete();

            // Create new token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Log the login
            AuditLogger::log('user_login', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'token' => $token,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Invalid credentials',
                'errors' => $e->errors(),
            ], 401);
        } catch (\Exception $e) {
            AuditLogger::log('login_failed', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Login failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            // Log the logout
            AuditLogger::log('user_logout', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Logout successful',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed',
            ], 500);
        }
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
}
