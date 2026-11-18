<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    public function login(LoginRequest $request): UserResource
    {
        return new UserResource($this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->boolean('remember'),
        ));
    }

    public function logout(): Response
    {
        $this->authService->logout();

        return response()->noContent();
    }

    public function resetPassword(ResetPasswordRequest $request): Response
    {
        $this->authService->resetPassword($request->validated());

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($this->authService->me($request->user()));
    }
}
