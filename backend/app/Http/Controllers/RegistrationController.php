<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartnerRegistrationRequest;
use App\Http\Requests\StudentRegistrationRequest;
use App\Http\Resources\RegistrationResource;
use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;

class RegistrationController extends Controller
{
    public function __construct(private readonly RegistrationService $registrationService) {}

    public function student(StudentRegistrationRequest $request): JsonResponse
    {
        return (new RegistrationResource($this->registrationService->registerStudent($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function partner(PartnerRegistrationRequest $request): JsonResponse
    {
        return (new RegistrationResource($this->registrationService->registerPartner($request->validated())))
            ->response()
            ->setStatusCode(201);
    }
}
