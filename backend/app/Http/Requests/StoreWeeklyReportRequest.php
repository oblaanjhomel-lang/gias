<?php

namespace App\Http\Requests;

/**
 * Create (or upsert) a draft for a week. Only `week_number` picks the period; the server
 * computes the dates from the deployment's start date.
 */
class StoreWeeklyReportRequest extends UpdateWeeklyReportRequest
{
    public function authorize(): bool
    {
        return true; // role:student; the service checks the active deployment
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['week_number' => ['required', 'integer', 'between:1,104']] + parent::rules();
    }
}
