<?php

namespace App\Http\Requests\Pra;

use Illuminate\Foundation\Http\FormRequest;

class PraSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pra.read') ?? false;
    }

    public function rules(): array
    {
        return [
            'file_numbers' => ['sometimes', 'array'],
            'file_numbers.*' => ['string'],
            'prop_ids' => ['sometimes', 'array'],
            'prop_ids.*' => ['string'],
            'party' => ['sometimes', 'string'],
            'location' => ['sometimes', 'string'],
            'land_use' => ['sometimes', 'string'],
            'instrument_type' => ['sometimes', 'string'],
            'registration_date_from' => ['sometimes', 'date'],
            'registration_date_to' => ['sometimes', 'date'],
            'transaction_date_from' => ['sometimes', 'date'],
            'transaction_date_to' => ['sometimes', 'date'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string'],
            'order' => ['sometimes', 'in:asc,desc'],
        ];
    }
}
