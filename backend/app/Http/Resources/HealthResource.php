<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HealthResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => 'ok',
            'laravel' => $this->resource['laravel'],
            'php' => $this->resource['php'],
            'postgis' => $this->resource['postgis'],
        ];
    }
}
