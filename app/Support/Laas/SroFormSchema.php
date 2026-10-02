<?php

namespace App\Support\Laas;

/**
 * The Application for Statutory Right of Occupancy, as the applicant fills it.
 *
 * Single source of truth for all four land types. The apply form renders from
 * it, the controller validates against it, and the status and staff screens
 * read submitted answers back through it — so a field cannot exist in one place
 * and be missing from another.
 *
 * Field keys are deliberately identical to the `oss_applications` column names
 * used by the staff modal (see _ossFieldMap in
 * public/js/lands-one-stop-shop/applications.js). A portal submission can
 * therefore be promoted into the live OSS table by column name, with no
 * translation layer to keep in step.
 *
 * Layout: every field carries a `col` span on a 12-column grid, so the form
 * lays out landscape rather than as one tall stack. Widths are chosen from the
 * answer, not the label — an age is three characters and has no business
 * occupying the same width as a street address.
 *
 * Lookups. Only the cascade that the data actually supports is offered:
 * `districts` (1,818 rows) has no lga_id and `street_names` (826) has no
 * district_id, so neither can be filtered by its parent. Street and district
 * are loaded from lookup tables as dropdown selects; State → LGA is the only
 * genuine cascade and is rendered as one.
 */
class SroFormSchema
{
    public const TYPE_RESIDENTIAL  = 'residential';
    public const TYPE_COMMERCIAL   = 'commercial';
    public const TYPE_INDUSTRIAL   = 'industrial';
    public const TYPE_AGRICULTURAL = 'agricultural';

    public const TYPES = [
        self::TYPE_RESIDENTIAL  => ['label' => 'Residential Land',  'icon' => 'home'],
        self::TYPE_COMMERCIAL   => ['label' => 'Commercial Land',   'icon' => 'building-2'],
        self::TYPE_INDUSTRIAL   => ['label' => 'Industrial Land',   'icon' => 'factory'],
        self::TYPE_AGRICULTURAL => ['label' => 'Agricultural Land', 'icon' => 'wheat'],
    ];

    /**
     * The literal a select carries when the applicant's answer is not on the
     * list. Seeing it stored anywhere means resolveOther() was skipped — the
     * controller always swaps it for what they actually typed.
     */
    public const OTHER = 'Other';

    /**
     * No nationalities lookup table exists either. Nigerian leads because it is
     * the overwhelming majority; the rest are the neighbours and the communities
     * with a standing presence in Kano. "Other" catches everyone else.
     */
    public const NATIONALITIES = [
        'Nigerian',
        'Beninese', 'Cameroonian', 'Chadian', 'Ghanaian', 'Nigerien', 'Togolese',
        'British', 'American', 'Chinese', 'Indian', 'Lebanese',
        'Other',
    ];

    public const SEX = ['Male', 'Female'];
    public const MARITAL_STATUS = ['Single', 'Married', 'Divorced', 'Widowed'];
    public const YES_NO = ['Yes', 'No'];

    /**
     * There is no occupations lookup table in the database, so this list is
     * curated. "Other" is last and always available — an applicant must never
     * be blocked because their trade is not on someone's list.
     */
    public const OCCUPATIONS = [
        'Civil Servant',
        'Public Servant',
        'Private Sector Employee',
        'Business / Trader',
        'Farmer',
        'Teacher / Lecturer',
        'Medical Practitioner',
        'Legal Practitioner',
        'Engineer',
        'Architect / Surveyor',
        'Accountant',
        'Banker',
        'Artisan / Technician',
        'Transporter',
        'Student',
        'Retired',
        'Unemployed',
        'Other',
    ];

    /** Number of prior-allocation rows the residential form offers. */
    public const PREV_ALLOCATION_ROWS = 3;

    public static function isValidType(?string $type): bool
    {
        return $type !== null && array_key_exists($type, self::TYPES);
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type]['label'] ?? (string) $type;
    }

    /**
     * Sections for one land type.
     *
     * Field: key, label, type (text|textarea|select|number|address|
     * prev_allocations), col (1-12 grid span), plus optional required, help,
     * options.
     */
    public static function sections(string $type): array
    {
        switch ($type) {
            case self::TYPE_RESIDENTIAL:
                return self::residential();
            case self::TYPE_COMMERCIAL:
                return self::commercial();
            case self::TYPE_INDUSTRIAL:
                return self::industrialLike('Industrial', 'ind_', 'nature_of_industrial', 'Nature of the industrial activity');
            case self::TYPE_AGRICULTURAL:
                return self::industrialLike('Agricultural', 'agr_', 'nature_of_agricultural', 'Nature of the agricultural activity');
        }

        return [];
    }

    /**
     * The parts every address block is built from, in the order they are shown.
     *
     * `lookup` names the reference endpoint that feeds the control:
     *   state    — a plain select, 37 rows, safe to inline
     *   lga      — cascades off the chosen state
    *   district — lookup dropdown, 1,818 rows and no parent key
    *   street   — lookup dropdown, 826 rows and no parent key
     */
    public static function addressParts(): array
    {
        return [
            'plot'     => ['label' => 'Plot Number', 'type' => 'text',     'col' => 2],
            'street'   => ['label' => 'Street Name', 'type' => 'select', 'col' => 4, 'lookup' => 'street',   'other' => true, 'other_key' => 'street_other'],
            'district' => ['label' => 'District',    'type' => 'select', 'col' => 2, 'lookup' => 'district', 'other' => true, 'other_key' => 'district_other'],
            'lga'      => ['label' => 'L.G.A.',      'type' => 'select', 'col' => 2, 'lookup' => 'lga',      'other' => true],
            'state'    => ['label' => 'State',       'type' => 'select', 'col' => 2, 'lookup' => 'state',    'other' => true],
        ];
    }

    /**
     * Name of the scratch input that captures what the applicant typed after
     * choosing "Other".
     *
     * Deliberately NOT a real oss_applications column: the controller consumes
     * it and drops it, so nothing that could never be promoted ever reaches
     * form_data.
     */
    public static function specifyKey(string $key): string
    {
        return $key . '__specify';
    }

    /**
     * Every select on this form that offers "Other", as
     * [main key => the *_other column to also fill, or null].
     *
     * Null means the typed value simply replaces the selection: `nationality`
     * has no companion column, and storing the literal "Other" there would
     * tell an officer nothing.
     */
    public static function otherFields(string $type): array
    {
        $map = [];

        foreach (self::sections($type) as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'address') {
                    foreach (self::addressParts() as $part => $meta) {
                        if (!empty($meta['other'])) {
                            $map[$field['prefix'] . $part] = isset($meta['other_key'])
                                ? $field['prefix'] . $meta['other_key']
                                : null;
                        }
                    }
                    continue;
                }

                if (!empty($field['other'])) {
                    $map[$field['key']] = null;
                }
            }
        }

        return $map;
    }

    /** Every field key for a type, flattened — address groups expanded. */
    public static function fieldKeys(string $type): array
    {
        $keys = [];

        foreach (self::sections($type) as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'address') {
                    $keys[] = $field['key'];
                    foreach (array_keys(self::addressParts()) as $part) {
                        $keys[] = $field['prefix'] . $part;
                    }
                    continue;
                }

                if ($field['type'] === 'prev_allocations') {
                    continue; // Collected as rows, stored as JSON.
                }

                $keys[] = $field['key'];
            }
        }

        return $keys;
    }

    /** Laravel validation rules for a submitted form of this type. */
    public static function rules(string $type): array
    {
        $rules = [];

        foreach (self::sections($type) as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'address') {
                    $rules[$field['key']] = ['nullable', 'string', 'max:2000'];
                    foreach (array_keys(self::addressParts()) as $part) {
                        $rules[$field['prefix'] . $part] = ['nullable', 'string', 'max:255'];
                    }
                    continue;
                }

                if ($field['type'] === 'prev_allocations') {
                    continue;
                }

                $rule = [($field['required'] ?? false) ? 'required' : 'nullable'];

                $rule[] = $field['type'] === 'number' ? 'numeric' : 'string';

                // Only closed lists are constrained. A combobox is deliberately
                // free text: the reference tables are incomplete, and refusing
                // an address because a street is missing from them would be
                // the form's fault, not the applicant's.
                if (isset($field['options'])) {
                    $rule[] = 'in:' . implode(',', $field['options']);
                }

                $rule[] = $field['type'] === 'textarea' ? 'max:2000' : 'max:255';

                $rules[$field['key']] = $rule;
            }
        }

        // The "Other" text boxes. Never required: the select itself carries the
        // requirement, and an empty specify simply leaves "Other" as the answer.
        foreach (array_keys(self::otherFields($type)) as $key) {
            $rules[self::specifyKey($key)] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /** Human label for a stored key, for reading answers back. */
    public static function labelFor(string $type, string $key): string
    {
        foreach (self::sections($type) as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['type'] === 'address') {
                    foreach (self::addressParts() as $part => $meta) {
                        if ($field['prefix'] . $part === $key) {
                            return $field['label'] . ' — ' . $meta['label'];
                        }
                    }
                    continue;
                }

                if ($field['key'] === $key) {
                    return $field['label'];
                }
            }
        }

        return ucfirst(str_replace('_', ' ', $key));
    }

    // ---------------------------------------------------------------- types

    private static function residential(): array
    {
        return [
            [
                'title'  => '1. Applicant',
                'icon'   => 'user',
                'fields' => [
                    ['key' => 'applicant_name', 'label' => 'Applicant&rsquo;s full name', 'type' => 'text', 'col' => 6, 'required' => true],
                    ['key' => 'age', 'label' => '(A) Age', 'type' => 'number', 'col' => 2],
                    ['key' => 'sex', 'label' => '(B) Sex', 'type' => 'select', 'col' => 2, 'options' => self::SEX],
                    ['key' => 'marital_status', 'label' => '(C) Marital status', 'type' => 'select', 'col' => 2, 'options' => self::MARITAL_STATUS],
                    ['key' => 'husband_name_address', 'label' => '(D) If a married woman, give the name and address of your husband', 'type' => 'textarea', 'col' => 12],
                ],
            ],
            [
                'title'  => '2. Residential address',
                'icon'   => 'home',
                'note'   => 'A P.O. Box must not be given.',
                'fields' => [
                    ['key' => 'residential_address', 'label' => 'Residential address', 'type' => 'address', 'col' => 12, 'prefix' => 'res_addr_'],
                ],
            ],
            [
                'title'  => '3. Correspondence address',
                'icon'   => 'mail',
                'fields' => [
                    ['key' => 'correspondence_address', 'label' => 'Correspondence address', 'type' => 'address', 'col' => 12, 'prefix' => 'res_corr_'],
                ],
            ],
            [
                'title'  => '4. Contact',
                'icon'   => 'phone',
                'fields' => [
                    ['key' => 'phone', 'label' => 'Phone number', 'type' => 'text', 'col' => 6, 'required' => true, 'help' => 'Updates are sent here by SMS.'],
                    ['key' => 'email', 'label' => 'Email address', 'type' => 'text', 'col' => 6, 'help' => 'If any'],
                ],
            ],
            [
                'title'  => '5. Business address',
                'icon'   => 'briefcase',
                'note'   => 'Only if different from your correspondence address above.',
                'fields' => [
                    ['key' => 'business_address', 'label' => 'Business address', 'type' => 'address', 'col' => 12, 'prefix' => 'res_biz_'],
                ],
            ],
            [
                'title'  => '6. Nationality and origin',
                'icon'   => 'flag',
                'fields' => [
                    ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'select', 'col' => 4, 'options' => self::NATIONALITIES, 'other' => true, 'help' => 'Indicate if naturalised'],
                    ['key' => 'state_of_origin', 'label' => '(A) State of origin', 'type' => 'select', 'col' => 4, 'lookup' => 'state', 'help' => 'If Nigerian'],
                    ['key' => 'lga', 'label' => '(B) Local Government of origin', 'type' => 'select', 'col' => 4, 'lookup' => 'lga', 'parent' => 'state_of_origin', 'help' => 'If Nigerian'],
                ],
            ],
            [
                'title'  => '7. Occupation',
                'icon'   => 'hard-hat',
                'fields' => [
                    ['key' => 'occupation', 'label' => 'Occupation', 'type' => 'select', 'col' => 6, 'options' => self::OCCUPATIONS, 'other' => true],
                    ['key' => 'annual_income', 'label' => '(a) Annual income', 'type' => 'text', 'col' => 6, 'help' => 'e.g. 2,400,000'],
                ],
            ],
            [
                'title'  => '8. Previous allocation',
                'icon'   => 'history',
                'fields' => [
                    ['key' => 'prev_allocated', 'label' => 'Have you been allocated any residential plot before?', 'type' => 'select', 'col' => 4, 'options' => self::YES_NO],
                    ['key' => 'prev_allocation_details', 'label' => 'If yes, give the details', 'type' => 'prev_allocations', 'col' => 12, 'show_if' => ['field' => 'prev_allocated', 'value' => 'Yes']],
                ],
            ],
            self::remarksSection(),
        ];
    }

    private static function commercial(): array
    {
        return [
            [
                'title'  => '1. Applicant',
                'icon'   => 'user',
                'fields' => [
                    ['key' => 'applicant_name', 'label' => 'Name of applicant or company', 'type' => 'text', 'col' => 12, 'required' => true],
                ],
            ],
            [
                'title'  => '2. Nationality and background',
                'icon'   => 'flag',
                'fields' => [
                    ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'select', 'col' => 4, 'options' => self::NATIONALITIES, 'other' => true, 'help' => 'Indicate if naturalised'],
                    ['key' => 'state_of_origin', 'label' => '(a) State of origin', 'type' => 'select', 'col' => 4, 'lookup' => 'state'],
                    ['key' => 'home_domicile', 'label' => '(b) Home domicile', 'type' => 'text', 'col' => 4],
                    ['key' => 'occupation_or_business', 'label' => '(c) Occupation or business', 'type' => 'select', 'col' => 6, 'options' => self::OCCUPATIONS, 'other' => true],
                    ['key' => 'annual_income', 'label' => '(e) Annual income', 'type' => 'text', 'col' => 6],
                    ['key' => 'nature_of_commerce', 'label' => '(d) Nature of commercial activity', 'type' => 'textarea', 'col' => 12],
                ],
            ],
            [
                'title'  => '3. Company details',
                'icon'   => 'building-2',
                'fields' => [
                    ['key' => 'company_registered_under', 'label' => '(a) Company is registered under', 'type' => 'text', 'col' => 6],
                    ['key' => 'annual_income_anticipation', 'label' => '(e) Annual or anticipated income', 'type' => 'text', 'col' => 6],
                    ['key' => 'registration_particulars', 'label' => '(b) Registration particulars', 'type' => 'textarea', 'col' => 12],
                    ['key' => 'business_location', 'label' => '(c) Business location', 'type' => 'address', 'col' => 12, 'prefix' => 'com_biz_'],
                    ['key' => 'correspondence_address', 'label' => '(d) Correspondence address', 'type' => 'address', 'col' => 12, 'prefix' => 'com_corr_'],
                ],
            ],
            [
                'title'  => '4. Previous allocation',
                'icon'   => 'history',
                'fields' => [
                    ['key' => 'prev_allocated', 'label' => 'Have you ever been allocated commercial land before?', 'type' => 'select', 'col' => 4, 'options' => self::YES_NO],
                    ['key' => 'prev_allocation_details', 'label' => '(a) If yes, state when and where, and the Certificate of Occupancy number', 'type' => 'textarea', 'col' => 6, 'show_if' => ['field' => 'prev_allocated', 'value' => 'Yes']],
                    ['key' => 'prev_land_purpose', 'label' => '(b) Purpose for which the land above is being used', 'type' => 'textarea', 'col' => 6, 'show_if' => ['field' => 'prev_allocated', 'value' => 'Yes']],
                ],
            ],
            [
                'title'  => '5. Purpose',
                'icon'   => 'target',
                'fields' => [
                    ['key' => 'purpose', 'label' => 'Purpose for which the land applied for is required', 'type' => 'textarea', 'col' => 6],
                    ['key' => 'intended_activities', 'label' => '6. What type of activities do you intend to undertake?', 'type' => 'textarea', 'col' => 6],
                ],
            ],
            self::contactSection(),
            self::remarksSection(),
        ];
    }

    /**
     * Industrial and Agricultural are the same paper form with one word and one
     * field changed, so they share a builder rather than being copied.
     */
    private static function industrialLike(string $word, string $prefix, string $natureKey, string $natureLabel): array
    {
        $lower = strtolower($word);

        return [
            [
                'title'  => '1. Applicant',
                'icon'   => 'user',
                'fields' => [
                    ['key' => 'applicant_name', 'label' => 'Name of applicant or company', 'type' => 'text', 'col' => 12, 'required' => true],
                ],
            ],
            [
                'title'  => '2. Nationality',
                'icon'   => 'flag',
                'fields' => [
                    ['key' => 'nationality', 'label' => 'Nationality', 'type' => 'select', 'col' => 4, 'options' => self::NATIONALITIES, 'other' => true, 'help' => 'Indicate if naturalised'],
                    ['key' => 'state_of_origin', 'label' => '(a) State of origin', 'type' => 'select', 'col' => 4, 'lookup' => 'state'],
                    ['key' => 'home_domicile', 'label' => '(b) Home domicile', 'type' => 'text', 'col' => 4],
                ],
            ],
            [
                'title'  => '3. Occupation or business',
                'icon'   => 'hard-hat',
                'fields' => [
                    ['key' => 'occupation_or_business', 'label' => '3. Occupation or business', 'type' => 'select', 'col' => 6, 'options' => self::OCCUPATIONS, 'other' => true],
                    ['key' => 'nature_of_occupation', 'label' => '4. Nature of occupation or business', 'type' => 'textarea', 'col' => 6],
                ],
            ],
            [
                'title'  => '5. Registration details',
                'icon'   => 'building-2',
                'fields' => [
                    ['key' => 'company_registered_under', 'label' => '(A) Applicant or company is registered under', 'type' => 'text', 'col' => 6],
                    ['key' => 'registration_particulars', 'label' => '(B) Registration particulars', 'type' => 'textarea', 'col' => 6],
                    ['key' => 'business_location', 'label' => '(C) Business location', 'type' => 'address', 'col' => 12, 'prefix' => $prefix . 'biz_'],
                    ['key' => 'correspondence_address', 'label' => '(D) Correspondence address', 'type' => 'address', 'col' => 12, 'prefix' => $prefix . 'corr_'],
                ],
            ],
            [
                'title'  => '6. Income and employees',
                'icon'   => 'coins',
                'fields' => [
                    ['key' => 'annual_income_turnover', 'label' => '6. Annual income or turnover', 'type' => 'text', 'col' => 6],
                    ['key' => 'number_of_employees', 'label' => '7. Number of employees', 'type' => 'number', 'col' => 6],
                ],
            ],
            [
                'title'  => '8. Previous allocation',
                'icon'   => 'history',
                'fields' => [
                    ['key' => 'prev_allocated', 'label' => 'Have you ever been allocated ' . $lower . ' land before?', 'type' => 'select', 'col' => 4, 'options' => self::YES_NO],
                    ['key' => 'prev_allocation_details', 'label' => 'If yes, give the date of the allocation and the Certificate of Occupancy number', 'type' => 'textarea', 'col' => 8, 'show_if' => ['field' => 'prev_allocated', 'value' => 'Yes']],
                ],
            ],
            [
                'title'  => '9. Purpose',
                'icon'   => 'target',
                'fields' => [
                    ['key' => 'purpose', 'label' => 'Purpose for which the land applied for is acquired', 'type' => 'textarea', 'col' => 4],
                    ['key' => $natureKey, 'label' => $natureLabel, 'type' => 'textarea', 'col' => 4],
                    ['key' => 'waste_disposal_requirements', 'label' => 'Any special requirement needed for waste disposal', 'type' => 'textarea', 'col' => 4],
                ],
            ],
            self::contactSection(),
            self::remarksSection(),
        ];
    }

    /**
     * Contact details. Not numbered on the commercial, industrial and
     * agricultural paper forms — which assume a clerk already holds them — but
     * the portal cannot text an applicant it has no number for.
     */
    private static function contactSection(): array
    {
        return [
            'title'  => 'Contact',
            'icon'   => 'phone',
            'fields' => [
                ['key' => 'phone', 'label' => 'Phone number', 'type' => 'text', 'col' => 6, 'required' => true, 'help' => 'Updates are sent here by SMS.'],
                ['key' => 'email', 'label' => 'Email address', 'type' => 'text', 'col' => 6, 'help' => 'If any'],
            ],
        ];
    }

    private static function remarksSection(): array
    {
        return [
            'title'  => 'Anything else',
            'icon'   => 'message-square',
            'fields' => [
                ['key' => 'remarks', 'label' => 'Remarks or notes', 'type' => 'textarea', 'col' => 12],
            ],
        ];
    }
}
