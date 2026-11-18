<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\ListAccountsRequest;
use App\Http\Requests\StoreIndustryPartnerRequest;
use App\Http\Requests\UpdateAccountStatusRequest;
use App\Http\Requests\UpdateIndustryPartnerRequest;
use App\Http\Resources\IndustryPartnerResource;
use App\Models\User;
use App\Services\IndustryPartnerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class IndustryPartnerController extends Controller
{
    public function __construct(private readonly IndustryPartnerService $partnerService) {}

    public function index(ListAccountsRequest $request): AnonymousResourceCollection
    {
        return IndustryPartnerResource::collection($this->partnerService->list($request->validated()));
    }

    public function store(StoreIndustryPartnerRequest $request): IndustryPartnerResource
    {
        return new IndustryPartnerResource($this->partnerService->create($request->validated()));
    }

    public function update(UpdateIndustryPartnerRequest $request, User $partner): IndustryPartnerResource
    {
        return new IndustryPartnerResource(
            $this->partnerService->update($request->user(), $partner, $request->validated()),
        );
    }

    public function updateStatus(UpdateAccountStatusRequest $request, User $partner): IndustryPartnerResource
    {
        return new IndustryPartnerResource($this->partnerService->setStatus(
            $request->user(),
            $partner,
            $request->enum('status', UserStatus::class),
        ));
    }

    public function destroy(Request $request, User $partner): Response
    {
        $this->partnerService->delete($request->user(), $partner);

        return response()->noContent();
    }
}
