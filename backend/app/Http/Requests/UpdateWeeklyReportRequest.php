<?php

namespace App\Http\Requests;

use App\Models\WeeklyReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Report content. The server computes period dates and total_hours; clients never send them.
 */
class UpdateWeeklyReportRequest extends FormRequest
{
    public const MAX_WEEK_HOURS = 60;

    public function authorize(): bool
    {
        return true; // route `can:manage,report`
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'daily_hours' => ['required', 'array:'.implode(',', WeeklyReport::DAYS)],
            'tasks' => ['required', 'string', 'min:20', 'max:2000'],
            'learnings' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (WeeklyReport::DAYS as $day) {
            $rules["daily_hours.{$day}"] = ['required', 'numeric', 'between:0,12', 'multiple_of:0.5'];
        }

        return $rules;
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('daily_hours*')) {
                    return;
                }
                $total = array_sum(array_map('floatval', (array) $this->input('daily_hours', [])));
                if ($total > self::MAX_WEEK_HOURS) {
                    $validator->errors()->add('daily_hours', "A week can have at most 60 hours (this one has {$total}).");
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'daily_hours.*.multiple_of' => 'Use steps of 0.5 hours.',
            'daily_hours.*.between' => 'Each day is 0 to 12 hours.',
            'tasks.min' => 'Describe your tasks in at least 20 characters.',
        ];
    }
}
