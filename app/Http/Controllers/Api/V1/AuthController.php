<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user  = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('luminite-desktop')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user  = Auth::user();
        $token = $user->createToken('luminite-desktop')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        // Always returns the same generic message — never reveals whether the
        // email belongs to an account (prevents account enumeration).
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'data'    => null,
            'message' => 'If an account exists for that email, a reset link has been sent.',
            'errors'  => (object) [],
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                // Revoke every Sanctum token — consistent with the password-change rule.
                $user->tokens()->delete();

                event(new PasswordReset($user));

                // Audit trail (global auth event — NOT the project-scoped ActivityLogService).
                Log::info('Password reset completed', [
                    'user_id' => $user->id,
                    'email'   => $user->email,
                    'ip'      => $request->ip(),
                ]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'data'    => null,
                'message' => __($status),
                'errors'  => ['email' => [__($status)]],
            ], 422);
        }

        return response()->json([
            'data'    => null,
            'message' => 'Your password has been reset. Please log in with your new password.',
            'errors'  => (object) [],
        ]);
    }
}
