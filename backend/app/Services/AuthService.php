<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthService
{
    /**
     * Start a cookie session (web guard) for the given credentials.
     *
     * @throws ValidationException wrong credentials (422)
     * @throws AccessDeniedHttpException account pending or inactive (403)
     */
    public function login(string $email, string $password, bool $remember = false): User
    {
        $guard = Auth::guard('web');

        if (! $guard->attempt(['email' => $email, 'password' => $password], $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        /** @var User $user */
        $user = $guard->user();

        $blocked = match ($user->status) {
            UserStatus::Pending => 'Your account is awaiting approval.',
            UserStatus::Inactive => 'Your account has been deactivated.',
            UserStatus::Rejected => 'Your registration was returned: '
                .rtrim((string) $user->review_note, " .\t\n")
                .'. Contact your OJT Coordinator or the System Development Office.',
            default => null,
        };

        if ($blocked !== null) {
            $guard->logout();

            throw new AccessDeniedHttpException($blocked);
        }

        session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return $user->load('program');
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();
    }

    public function me(User $user): User
    {
        return $user->load('program');
    }

    /**
     * Set a new password from an emailed set-password / reset link.
     *
     * @param  array{token: string, email: string, password: string, password_confirmation?: string}  $data
     *
     * @throws ValidationException invalid/expired token or unknown email (422 on `email`)
     */
    public function resetPassword(array $data): void
    {
        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => $password, // hashed by the model cast
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }
    }
}
