<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the (all-optional) fields for creating a test-fixture reference
 * payment. Validation rules only — no business logic, no database access
 * (see backend-conventions → FormRequest). Any field left out is filled in
 * by the Application use case, not here.
 */
final class StoreReferencePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'address' => ['nullable', 'string'],
            'amount' => ['nullable', 'string', 'regex:/^\d+(\.\d+)?$/'],
            'network' => ['nullable', 'string'],
            'allowed_scripts' => ['nullable', 'array'],
            'allowed_scripts.*' => ['string'],
        ];
    }

    /**
     * Overrides Laravel's default validation error shape with the project's
     * error contract: {"error": {"message": "...", "fields": {...}}}.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'message' => 'Переданные данные не прошли проверку.',
                'fields' => $validator->errors()->toArray(),
            ],
        ], 422));
    }
}
