<?php

namespace App\Http\Requests;

use App\Enums\EvaluationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::enum(EvaluationType::class)],
        ];
    }

    public function evaluationType(): ?EvaluationType
    {
        return $this->validated('type') ? EvaluationType::from($this->validated('type')) : null;
    }
}
