<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\GeocodingService;
use App\Services\IGeocodingService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IGeocodingService::class, GeocodingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Set-password / reset links open the SPA, not a Laravel view.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('app.frontend_url')
            .'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]));

        // {coordinator} / {partner} / {student} only resolve users with that role; anything else is a 404.
        Route::bind('coordinator', fn (string $value) => $this->findUserWithRole($value, UserRole::Coordinator));
        Route::bind('partner', fn (string $value) => $this->findUserWithRole($value, UserRole::Partner));
        Route::bind('student', fn (string $value) => $this->findUserWithRole($value, UserRole::Student));
    }

    private function findUserWithRole(string $id, UserRole $role): User
    {
        abort_unless(ctype_digit($id), 404);

        return User::query()->where('role', $role)->findOrFail((int) $id);
    }
}
