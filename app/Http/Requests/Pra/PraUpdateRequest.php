<?php

namespace App\Http\Requests\Pra;

use Illuminate\Foundation\Http\FormRequest;

class PraUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    } 

    public function rules(): array
    {
        return [
            'prop_id' => ['sometimes', 'nullable'],
            'mlsFNo' => ['sometimes', 'nullable', 'string'],
            'kangisFileNo' => ['sometimes', 'nullable', 'string'],
            'NewKANGISFileno' => ['sometimes', 'nullable', 'string'],
            'temp_fileno' => ['sometimes', 'nullable', 'string'],
            'fileno' => ['sometimes', 'nullable', 'string'],
            'np_fileno' => ['sometimes', 'nullable', 'string'],
            'transaction_type' => ['sometimes', 'nullable', 'string'],
            'transaction_date' => ['sometimes', 'nullable', 'date'],
            'reg_date' => ['sometimes', 'nullable', 'date'],
            'reg_time' => ['sometimes', 'nullable', 'string'],
            'serialNo' => ['sometimes', 'nullable', 'string'],
            'pageNo' => ['sometimes', 'nullable', 'string'],
            'volumeNo' => ['sometimes', 'nullable', 'string'],
            'op_serial_number' => ['sometimes', 'nullable', 'string'],
            'op_type' => ['sometimes', 'nullable', 'string'],
            'regNo' => ['sometimes', 'nullable', 'string'],
            'purpose' => ['sometimes', 'nullable', 'string'],
            'land_use' => ['sometimes', 'nullable', 'string'],
            'plot_no' => ['sometimes', 'nullable', 'string'],
            'tp_no' => ['sometimes', 'nullable', 'string'],
            'lpkn_no' => ['sometimes', 'nullable', 'string'],
            'plot_size' => ['sometimes', 'nullable', 'string'],
            'house_no' => ['sometimes', 'nullable', 'string'],
            'lgsaOrCity' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string'],
            'property_description' => ['sometimes', 'nullable', 'string'],
            'source' => ['sometimes', 'nullable', 'string'],
            'instrument_type' => ['sometimes', 'nullable', 'string'],
            'deeds_date' => ['sometimes', 'nullable', 'date'],
            'deeds_time' => ['sometimes', 'nullable', 'string'],
            'parties' => ['sometimes', 'nullable', 'array'],
            'parties.*' => ['nullable', 'string'],
            'Grantor' => ['sometimes', 'nullable', 'string'],
            'Grantee' => ['sometimes', 'nullable', 'string'],
            'party_1' => ['sometimes', 'nullable', 'string'],
            'party_2' => ['sometimes', 'nullable', 'string'],
            'update_siblings' => ['sometimes', 'nullable', 'boolean'],
            'merger_group_id' => ['sometimes', 'nullable', 'string', 'max:36'],
            'is_merger_op' => ['sometimes', 'nullable', 'boolean'],
            'is_subdivided' => ['sometimes', 'nullable', 'boolean'],
            'op_count' => ['sometimes', 'nullable', 'integer', 'min:2'],
            // OP→ToT lineage (see PraStoreRequest for rationale).
            'source_op_id' => ['sometimes', 'nullable', 'integer'],
            'source_op_table' => ['sometimes', 'nullable', 'string', 'in:pra,instrument_capture,deed_registrations'],
            'parent_prop_id' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }
}
