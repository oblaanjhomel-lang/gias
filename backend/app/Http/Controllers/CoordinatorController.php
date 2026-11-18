<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\ListAccountsRequest;
use App\Http\Requests\StoreCoordinatorRequest;
use App\Http\Requests\UpdateAccountStatusRequest;
use App\Http\Requests\UpdateCoordinatorRequest;
use App\Http\Resources\CoordinatorResource;
use App\Http\Resources\ProgramResource;
use App\Models\User;
use App\Services\CoordinatorService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CoordinatorController extends Controller
{
    public function __construct(private readonly CoordinatorService $coordinatorService) {}

    public function index(ListAccountsRequest $request): AnonymousResourceCollection
    {
        $result = $this->coordinatorService->list($request->validated());

        return CoordinatorResource::collection($result['coordinators'])->additional([
            'meta' => [
                'programs_without_active_coordinator' => ProgramResource::collection(
                    $result['programs_without_active_coordinator'],
                )->resolve($request),
            ],
        ]);
    }

    public function store(StoreCoordinatorRequest $request): CoordinatorResource
    {
        return new CoordinatorResource($this->coordinatorService->create($request->validated()));
    }

    public function update(UpdateCoordinatorRequest $request, User $coordinator): CoordinatorResource
    {
        return new CoordinatorResource(
            $this->coordinatorService->update($request->user(), $coordinator, $request->validated()),
        );
    }

    public function updateStatus(UpdateAccountStatusRequest $request, User $coordinator): CoordinatorResource
    {
        return new CoordinatorResource($this->coordinatorService->setStatus(
            $request->user(),
            $coordinator,
            $request->enum('status', UserStatus::class),
        ));
    }

    public function destroy(Request $request, User $coordinator): Response
    {
        $this->coordinatorService->delete($request->user(), $coordinator);

        return response()->noContent();
    }
}
