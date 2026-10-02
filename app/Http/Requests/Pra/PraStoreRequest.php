<?php

namespace App\Http\Requests\Pra;

use Illuminate\Foundation\Http\FormRequest;

class PraStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prop_id' => ['nullable'],
            'mlsFNo' => ['nullable', 'string'],
            'kangisFileNo' => ['nullable', 'string'],
            'NewKANGISFileno' => ['nullable', 'string'],
            'temp_fileno' => ['nullable', 'string'],
            'fileno' => ['nullable', 'string'],
            'np_fileno' => ['nullable', 'string'],
            'transaction_type' => ['required', 'string'],
            'transaction_date' => ['nullable', 'date'],
            'reg_date' => ['nullable', 'date'],
            'reg_time' => ['nullable', 'string'],
            'serialNo' => ['nullable', 'string'],
            'pageNo' => ['nullable', 'string'],
            'volumeNo' => ['nullable', 'string'],
            'op_serial_number' => ['nullable', 'string'],
            // Deed of Purchase: what was paid, and the receipt behind it.
            'consideration_amount' => ['nullable', 'string', 'max:100'],
            'receipt_no' => ['nullable', 'string', 'max:100'],
            'op_type' => ['nullable', 'string'],
            'regNo' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'land_use' => ['nullable', 'string'],
            'plot_no' => ['nullable', 'string'],
            'tp_no' => ['nullable', 'string'],
            'lpkn_no' => ['nullable', 'string'],
            'plot_size' => ['nullable', 'string'],
            'house_no' => ['nullable', 'string'],
            'lgsaOrCity' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'property_description' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'instrument_type' => ['nullable', 'string'],
            'system_source' => ['nullable', 'string'],
            'deeds_date' => ['nullable', 'date'],
            'deeds_time' => ['nullable', 'string'],
            'parties' => ['nullable', 'array'],
            'parties.*' => ['nullable', 'string'],
            'Grantor' => ['nullable', 'string'],
            'Grantee' => ['nullable', 'string'],
            'party_1' => ['nullable', 'string'],
            'party_2' => ['nullable', 'string'],
            'merger_group_id' => ['nullable', 'string', 'max:36'],
            'is_merger_op' => ['nullable', 'boolean'],
            // OP→ToT lineage. source_op_table/id pin the exact originating OP row
            // (avoids prop_id ambiguity); parent_prop_id preserves the OP's prop_id
            // even when the new row is given a fresh prop_id.
            'source_op_id' => ['nullable', 'integer'],
            'source_op_table' => ['nullable', 'string', 'in:pra,instrument_capture,deed_registrations'],
            'parent_prop_id' => ['nullable', 'string', 'max:50'],
            'force_fresh_prop_id' => ['nullable', 'boolean'],
        ];
    }
}
