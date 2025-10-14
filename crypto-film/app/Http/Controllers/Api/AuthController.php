<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_number' => $request->phone_number,
        ]);

        $verificationCode = random_int(100000, 999999);
        $user->sms_verification_code = $verificationCode;
        $user->save();

        // Mock SMS sending by logging the code
        Log::info('SMS Verification Code for user ' . $user->id . ': ' . $verificationCode);

        return response()->json([
            'message' => 'User registered successfully. Please verify your phone number.',
            'user_id' => $user->id,
        ], 201);
    }

    public function verifySms(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'verification_code' => 'required|string|digits:6',
        ]);

        $user = User::find($request->user_id);

        if ($user->sms_verification_code === $request->verification_code) {
            $user->phone_verified_at = now();
            $user->sms_verification_code = null; // Invalidate the code
            $user->save();

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'message' => 'Phone number verified successfully.',
                'token' => $token,
            ]);
        }

        return response()->json(['message' => 'Invalid verification code.'], 400);
    }

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->phone_verified_at) {
            return response()->json(['message' => 'Phone number not verified.'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json(['token' => $token]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
