<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the raw observations submitted by the frontend for a single
 * POST /api/checks run. Validation rules only — no business logic, no
 * database access (see backend-conventions → FormRequest).
 */
final class StoreCheckRequest extends FormRequest
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
        $decimalString = ['string', 'regex:/^\d+(\.\d+)?$/'];

        return [
            'run_id' => ['required', 'uuid'],
            'displayed_address' => ['required', 'string'],
            'displayed_amount' => ['required', ...$decimalString],
            'displayed_network' => ['required', 'string'],
            'qr_address' => ['nullable', 'string'],
            'qr_amount' => ['nullable', ...$decimalString],
            'qr_network' => ['nullable', 'string'],
            'copy_button_value' => ['nullable', 'string'],
            'address_after_watch_window' => ['nullable', 'string'],
            'page_scripts' => ['nullable', 'array'],
            'page_scripts.*' => ['string'],
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
