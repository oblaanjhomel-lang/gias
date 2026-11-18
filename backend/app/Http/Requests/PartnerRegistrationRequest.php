<?php

namespace App\Http\Requests;

use App\Enums\PartnerDocumentType;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Multipart. Same company fields as the admin's StoreIndustryPartnerRequest, plus a password
 * and up to 3 PDF documents: documents[i][file], documents[i][type].
 */
class PartnerRegistrationRequest extends StoreIndustryPartnerRequest
{
    public const MAX_DOCUMENTS = 3;

    public const MAX_DOCUMENT_KB = 5120; // 5 MB

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'documents' => ['nullable', 'array', 'max:'.self::MAX_DOCUMENTS],
            'documents.*' => ['array:file,type'],
            'documents.*.file' => [
                'required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.self::MAX_DOCUMENT_KB,
            ],
            'documents.*.type' => ['required', Rule::enum(PartnerDocumentType::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'email.unique' => 'An account with this email already exists.',
            'documents.max' => 'Upload at most 3 documents.',
            'documents.*.file.required' => 'Choose a PDF file.',
            'documents.*.file.mimes' => 'Each document must be a PDF.',
            'documents.*.file.mimetypes' => 'Each document must be a PDF.',
            'documents.*.file.max' => 'Each document must be 5 MB or smaller.',
            'documents.*.type.required' => 'Choose the document type.',
            'documents.*.type.enum' => 'Choose a valid document type.',
        ];
    }
}
