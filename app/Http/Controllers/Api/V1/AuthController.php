<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'token_name' => 'required|string|max:100',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $raw = Str::random(40);
        $token = ApiToken::create([
            'user_id' => $user->id,
            'name' => $request->token_name,
            'token' => hash('sha256', $raw),
            'abilities' => ['*'],
            'expires_at' => now()->addDays(365),
        ]);

        return response()->json([
            'token' => $raw,
            'token_id' => $token->id,
            'expires_at' => $token->expires_at,
        ]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $raw = $request->bearerToken() ?? $request->header('X-API-Token');

        if ($raw) {
            ApiToken::where('token', hash('sha256', $raw))->delete();
        }

        return response()->json(['message' => 'Token revoked.']);
    }

    public function tokens(Request $request): JsonResponse
    {
        $tokens = ApiToken::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at', 'created_at', 'updated_at']);

        return response()->json(['data' => $tokens]);
    }
}
