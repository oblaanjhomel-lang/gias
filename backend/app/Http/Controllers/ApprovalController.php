<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListApprovalsRequest;
use App\Http\Requests\ReturnRegistrationRequest;
use App\Http\Resources\ApprovalCountsResource;
use App\Http\Resources\PartnerApprovalResource;
use App\Http\Resources\StudentApprovalResource;
use App\Models\PartnerDocument;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Routes: role middleware decides who reaches each endpoint; {student} / {partner} only
 * resolve users with that role (404 otherwise); student actions also pass UserPolicy::reviewStudent.
 */
class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $approvalService) {}

    public function counts(Request $request): ApprovalCountsResource
    {
        return new ApprovalCountsResource($this->approvalService->counts($request->user()));
    }

    // Students

    public function students(ListApprovalsRequest $request): AnonymousResourceCollection
    {
        return StudentApprovalResource::collection(
            $this->approvalService->students($request->user(), $request->validated()),
        );
    }

    public function approveStudent(Request $request, User $student): StudentApprovalResource
    {
        return new StudentApprovalResource($this->approvalService->approveStudent($request->user(), $student));
    }

    public function returnStudent(ReturnRegistrationRequest $request, User $student): StudentApprovalResource
    {
        return new StudentApprovalResource(
            $this->approvalService->returnStudent($request->user(), $student, $request->validated('reason')),
        );
    }

    // Industry partners

    public function partners(ListApprovalsRequest $request): AnonymousResourceCollection
    {
        return PartnerApprovalResource::collection($this->approvalService->partners($request->validated()));
    }

    public function approvePartner(Request $request, User $partner): PartnerApprovalResource
    {
        return new PartnerApprovalResource($this->approvalService->approvePartner($request->user(), $partner));
    }

    public function returnPartner(ReturnRegistrationRequest $request, User $partner): PartnerApprovalResource
    {
        return new PartnerApprovalResource(
            $this->approvalService->returnPartner($request->user(), $partner, $request->validated('reason')),
        );
    }

    public function document(User $partner, PartnerDocument $document): StreamedResponse
    {
        return $this->approvalService->document($partner, $document);
    }
}
