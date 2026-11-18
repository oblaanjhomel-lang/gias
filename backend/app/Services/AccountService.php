<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Rules shared by every admin-managed account type (coordinators, industry partners).
 */
class AccountService
{
    /**
     * Admin accounts, and the acting admin's own account, can never be changed through
     * the account-management endpoints.
     */
    public function ensureManageable(User $actor, User $target): void
    {
        if ($target->role === UserRole::Admin || $target->is($actor)) {
            throw new AccessDeniedHttpException('This account cannot be changed here.');
        }
    }

    /**
     * Placeholder password for a new account; the owner sets the real one via the emailed link.
     */
    public function randomPassword(): string
    {
        return Str::password(40);
    }

    /**
     * Change status. Deactivating signs the user out everywhere immediately.
     * Call inside the caller's transaction.
     */
    public function applyStatus(User $user, UserStatus $status): void
    {
        $user->status = $status;

        if ($status === UserStatus::Inactive) {
            // Invalidate "keep me signed in" cookies too, or they'd silently log the user back in.
            $user->setRememberToken(Str::random(60));
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $user->save();
    }

    public function changeStatus(User $actor, User $user, UserStatus $status): void
    {
        $this->ensureManageable($actor, $user);

        DB::transaction(fn () => $this->applyStatus($user, $status));
    }

    /**
     * Hard delete. Profile rows (e.g. industry_partners) cascade in the database.
     */
    public function delete(User $actor, User $user): void
    {
        $this->ensureManageable($actor, $user);

        DB::transaction(function () use ($user) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            Password::broker()->deleteToken($user); // unused set-password link
            $user->delete();
        });
    }

    /**
     * Email a set-password link (Laravel's reset-password notification, pointing at the SPA).
     */
    public function sendSetPasswordLink(User $user): void
    {
        $user->sendPasswordResetNotification(Password::broker()->createToken($user));
    }

    /**
     * Case-insensitive "contains" pattern for whereLike(), with LIKE wildcards escaped.
     */
    public function containsPattern(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
