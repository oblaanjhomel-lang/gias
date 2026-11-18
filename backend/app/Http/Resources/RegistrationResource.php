<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Registration response: {message} only. The account is pending, so no user data or session.
 */
class RegistrationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array{message: string}
     */
    public function toArray(Request $request): array
    {
        return ['message' => (string) $this->resource];
    }
}
