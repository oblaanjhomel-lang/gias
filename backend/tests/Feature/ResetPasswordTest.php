<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_token_sets_the_password(): void
    {
        $user = User::factory()->create(['email' => 'new.user@dnsc.edu.ph']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => $token,
            'email' => 'new.user@dnsc.edu.ph',
            'password' => 'fresh-pass-2026',
            'password_confirmation' => 'fresh-pass-2026',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('fresh-pass-2026', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'new.user@dnsc.edu.ph']);
    }

    public function test_a_bad_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'new.user@dnsc.edu.ph']);
        $original = $user->password;
        Password::broker()->createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'token' => 'not-the-token',
            'email' => 'new.user@dnsc.edu.ph',
            'password' => 'fresh-pass-2026',
            'password_confirmation' => 'fresh-pass-2026',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'This password reset token is invalid.']);

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_password_rules(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/reset-password', [
            'token' => Password::broker()->createToken($user),
            'email' => $user->email,
            'password' => 'lettersonly',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');
    }
}
