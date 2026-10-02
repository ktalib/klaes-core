<?php

namespace App\Http\Requests\OnlineLegalSearch;

use App\Models\Laas\LaasApplicant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Applicant identification submitted from the public Online Legal Search
 * payment card.
 *
 * Everything here is re-checked server-side. The browser's own checks exist only
 * to save the applicant a round trip — a hand-crafted request reaches exactly
 * these rules, and a verification status or score sent by the browser is ignored
 * outright (it is never read from the request anywhere in this flow).
 */
class StoreIdVerificationRequest extends FormRequest
{
    /** The portal is public by design: anyone may submit their own identification. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $types   = array_keys((array) config('id_verification.types', []));
        $customerTypes = array_keys((array) config('id_verification.customer_types', []));
        $maxKb   = (int) config('id_verification.uploads.max_kilobytes', 5120);
        $mimes   = implode(',', (array) config('id_verification.uploads.mimes', ['jpeg', 'jpg', 'png', 'webp']));
        $mimeTypes = implode(',', (array) config('id_verification.uploads.mime_types', []));

        // `image` + `mimes` + `mimetypes` together are what rejects a renamed
        // executable: the extension list alone would accept payload.png, and the
        // MIME list alone trusts a spoofable client header. `image` inspects the
        // file itself.
        $imageRules = [
            'image',
            'mimes:' . $mimes,
            'mimetypes:' . $mimeTypes,
            'max:' . $maxKb,
        ];

        return [
            'file_number' => ['required', 'string', 'max:100'],
            'email'       => ['required', 'email', 'max:255'],

            // Individual or Lawyer / Legal Adviser. Both upload an ID and go
            // through the same name check; only a lawyer supplies a bar number.
            'customer_type' => ['required', 'string', Rule::in($customerTypes)],

            // Required for a lawyer, and rejected outright for an individual - an
            // individual has no call-to-bar number, so a value here would be
            // meaningless data on the record.
            'call_to_bar_number' => [
                Rule::requiredIf(fn () => $this->customerRequiresBarNumber()),
                'nullable',
                'string',
                'max:60',
                'regex:/^[A-Za-z0-9\/\-\. ]+$/',
            ],

            'applicant_full_name' => ['required', 'string', 'min:3', 'max:200'],
            'applicant_phone'     => ['required', 'string', 'max:30'],
            'applicant_address'   => ['required', 'string', 'min:5', 'max:500'],

            'identification_type' => ['required', 'string', Rule::in($types)],

            // Only meaningful for the "other" type, and required there — an
            // unlabelled "other government-issued ID" tells a reviewer nothing.
            'identification_type_other' => [
                Rule::requiredIf(fn () => $this->input('identification_type') === 'other'),
                'nullable',
                'string',
                'max:120',
            ],

            // The only image collected. The name is on this side of every accepted
            // document, so a back image is neither required nor accepted.
            'id_front' => array_merge(['required', 'file'], $imageRules),
        ];
    }

    public function messages(): array
    {
        return [
            'applicant_full_name.required'       => 'Enter your full name exactly as it appears on your identification.',
            'applicant_phone.required'           => 'Enter your phone number.',
            'applicant_address.required'         => 'Enter your residential or contact address.',
            'customer_type.required'             => 'Select whether you are an individual or a lawyer.',
            'customer_type.in'                   => 'Select a customer type from the list.',
            'call_to_bar_number.required'        => 'Enter your Call-to-Bar number.',
            'call_to_bar_number.regex'           => 'The Call-to-Bar number may only contain letters, numbers, spaces and - / .',
            'identification_type.required'       => 'Select your means of identification.',
            'identification_type.in'             => 'Select a means of identification from the list.',
            'identification_type_other.required' => 'Enter the type of identification you are uploading.',
            'id_front.required'                  => 'Upload a photo of your identification.',
            'id_front.image'                     => 'The image must be a JPEG, PNG or WebP image.',
            'id_front.max'                       => 'The image must not be larger than 5MB.',
        ];
    }

    /**
     * Normalize the phone to the project's single Nigerian form before the rules
     * run, so what is validated is what is stored. Reuses the model helper that
     * LAAS registration, sign-in and the profile screen already share, rather
     * than introducing a second idea of a valid number.
     */
    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('applicant_phone', ''));

        if ($phone !== '') {
            $this->merge([
                'applicant_phone' => LaasApplicant::normalizePhone($phone) ?? $phone,
            ]);
        }
    }

    /** Does the chosen customer type have to supply a Call-to-Bar number? */
    public function customerRequiresBarNumber(): bool
    {
        $type = (string) $this->input('customer_type', '');

        return (bool) config('id_verification.customer_types.' . $type . '.requires_bar_number', false);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $phone = (string) $this->input('applicant_phone', '');

            if ($phone !== '' && LaasApplicant::normalizePhone($phone) === null) {
                $validator->errors()->add(
                    'applicant_phone',
                    'Enter a valid Nigerian phone number, e.g. 08031234567.'
                );
            }
        });
    }
}
