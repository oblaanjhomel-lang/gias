<?php

namespace App\Http\Requests;

use App\Enums\EvaluationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluationExportRequest extends FormRequest
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
            'type' => ['required', Rule::enum(EvaluationType::class)],
        ];
    }

    public function evaluationType(): EvaluationType
    {
        return EvaluationType::from($this->validated('type'));
    }
}
