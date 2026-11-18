<?php

namespace App\Http\Controllers;

use App\Http\Resources\TermOverviewResource;
use App\Services\TermService;

class TermController extends Controller
{
    public function __construct(private readonly TermService $termService) {}

    public function __invoke(): TermOverviewResource
    {
        return new TermOverviewResource($this->termService->overview());
    }
}
