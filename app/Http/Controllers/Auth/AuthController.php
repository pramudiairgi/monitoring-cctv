<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Issue a new API token.
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke previous tokens if configured
        if (env('DELETE_PREVIOUS_ACCESS_TOKENS_ON_LOGIN', false)) {
            $user->tokens()->delete();
        }

        $token = $user->createToken($request->device_name, ['telemetry:submit'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Revoke the current token.
     *
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'logged out',
        ]);
    }

    /**
     * Revoke current token and issue a new one.
     *
     * @return JsonResponse
     */
    public function refresh(Request $request)
    {
        $currentToken = $request->user()->currentAccessToken();

        if ($currentToken) {
            $currentToken->delete();
        }

        $token = $request->user()->createToken('refresh', ['telemetry:submit'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }
}
