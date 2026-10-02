<?php

namespace App\Http\Requests\Grouping;

use Illuminate\Foundation\Http\FormRequest;

class LinkMlsFileNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mls_file_number' => ['required', 'string', 'max:191'],
            'awaiting_file_number' => ['nullable', 'string', 'max:191'],
            'registry' => ['nullable', 'string', 'max:191'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mls_file_number')) {
            $this->merge([
                'mls_file_number' => strtoupper(trim((string) $this->input('mls_file_number'))),
            ]);
        }
        if ($this->has('awaiting_file_number')) {
            $this->merge([
                'awaiting_file_number' => strtoupper(trim((string) $this->input('awaiting_file_number'))),
            ]);
        }
    }
}
