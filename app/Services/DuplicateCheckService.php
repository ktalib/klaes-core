<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DuplicateCheckService
{
    /**
     * Centralized duplicate check for various instrument types.
     * Uses 5 key parameters: File Number, Prop ID, Party 1, Party 2, Serial/Reg Details.
     *
     * @param string $type ('rofo', 'cofo', 'op', 'instrument', 'pra')
     * @param array $params
     * @return object|null  Returns the matching record or null
     */
    public function check(string $type, array $params)
    {
        // Strip empty values so we can count meaningful parameters
        $params = array_filter($params, fn($v) => $v !== null && $v !== '');

        // Require at least 2 non-empty parameters for a meaningful check
        if (count($params) < 2) {
            return null;
        }

        return match ($type) {
            'rofo'          => $this->checkRofo($params),
            'cofo'          => $this->checkCofo($params),
            'cofo_staging'  => $this->checkCofoStaging($params),
            'op'            => $this->checkOp($params),
            'instrument'    => $this->checkInstrument($params),
            'pra'           => $this->checkPra($params),
            default         => null,
        };
    }

    /**
     * Build a file-number OR clause across standard columns.
     */
    protected function applyFilenoFilter($query, string $fileno, array $columns = ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'temp_fileno'])
    {
        $query->where(function ($q) use ($fileno, $columns) {
            foreach ($columns as $i => $col) {
                $i === 0 ? $q->where($col, $fileno) : $q->orWhere($col, $fileno);
            }
        });
    }

    // ──────────────────────────────────────────────
    //  RofO — land_recommendations table
    // ──────────────────────────────────────────────
    protected function checkRofo(array $params)
    {
        $query = DB::connection('sqlsrv')->table('land_recommendations');

        if (!empty($params['exclude_id'])) {
            $query->where('id', '!=', $params['exclude_id']);
        }

        if (!empty($params['fileno'])) {
            $query->where('file_number', $params['fileno']);
        }

        if (!empty($params['party_name'])) {
            $query->where('applicant_name', 'LIKE', '%' . $params['party_name'] . '%');
        }

        if (!empty($params['serial_no'])) {
            $query->where('land_rofo_serial_no', $params['serial_no']);
        }

        if (!empty($params['grant_date'])) {
            $query->where('effective_date', $params['grant_date']);
        }

        if (!empty($params['land_use'])) {
            $query->where('land_use', $params['land_use']);
        }

        return $query->first();
    }

    // ──────────────────────────────────────────────
    //  CofO — CofO table (legacy Lands CofO)
    // ──────────────────────────────────────────────
    protected function checkCofo(array $params)
    {
        $query = DB::connection('sqlsrv')->table('CofO');

        if (!empty($params['exclude_id'])) {
            $query->where('id', '!=', $params['exclude_id']);
        }

        if (!empty($params['cofo_no'])) {
            $query->where('cofo_no', $params['cofo_no']);
        }

        if (!empty($params['prop_id'])) {
            $query->where('prop_id', $params['prop_id']);
        }

        if (!empty($params['fileno'])) {
            $this->applyFilenoFilter($query, $params['fileno'], ['fileno', 'mlsFNo', 'kangisFileNo', 'NewKANGISFileno']);
        }

        if (!empty($params['owner_name'])) {
            $name = $params['owner_name'];
            $query->where(function ($q) use ($name) {
                $q->where('Grantee', 'LIKE', '%' . $name . '%')
                  ->orWhere('Assignee', 'LIKE', '%' . $name . '%');
            });
        }

        // CofO is unique per file/prop_id/cofo_no; do not strictly match date if those are present
        if (!empty($params['reg_date']) && empty($params['cofo_no']) && empty($params['prop_id']) && empty($params['fileno'])) {
            $query->where('transaction_date', $params['reg_date']);
        }

        return $query->first();
    }

    // ──────────────────────────────────────────────
    //  OP — instrument_capture (Occupancy Permit)
    // ──────────────────────────────────────────────
    protected function checkOp(array $params)
    {
        $query = DB::connection('sqlsrv')->table('instrument_capture')
            ->where('instrument_type', 'Occupancy Permit')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });

        if (!empty($params['exclude_id'])) {
            $query->where('id', '!=', $params['exclude_id']);
        }

        if (!empty($params['op_serial'])) {
            $query->where('op_serial_number', $params['op_serial']);
        }

        if (!empty($params['fileno'])) {
            $this->applyFilenoFilter($query, $params['fileno']);
        }

        if (!empty($params['party_1'])) {
            $query->where('party_1_name', 'LIKE', '%' . $params['party_1'] . '%');
        }

        if (!empty($params['party_2'])) {
            $query->where('party_2_name', 'LIKE', '%' . $params['party_2'] . '%');
        }

        // Occupancy Permits are unique per file/serial; do not strictly match date if those are present
        if (!empty($params['instrument_date']) && empty($params['op_serial']) && empty($params['fileno'])) {
            $query->where('instrument_date', $params['instrument_date']);
        }

        return $query->first();
    }

    // ──────────────────────────────────────────────
    //  Generic Instrument — instrument_capture (non-OP)
    // ──────────────────────────────────────────────
    protected function checkInstrument(array $params)
    {
        $query = DB::connection('sqlsrv')->table('instrument_capture')
            ->where('instrument_type', '!=', 'Occupancy Permit')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });

        if (!empty($params['exclude_id'])) {
            $query->where('id', '!=', $params['exclude_id']);
        }

        if (!empty($params['instrument_type'])) {
            $query->where('instrument_type', $params['instrument_type']);
        }

        if (!empty($params['reg_no'])) {
            $query->where('reg_no', $params['reg_no']);
        }

        if (!empty($params['fileno'])) {
            $this->applyFilenoFilter($query, $params['fileno']);
        }

        if (!empty($params['party_1'])) {
            $query->where('party_1_name', 'LIKE', '%' . $params['party_1'] . '%');
        }

        if (!empty($params['party_2'])) {
            $query->where('party_2_name', 'LIKE', '%' . $params['party_2'] . '%');
        }

        // Do not strictly match date if it's a singular instrument type OR if key identifiers (fileno, reg_no, prop_id) are present
        if (!empty($params['instrument_date'])) {
            $isSingular = false;
            if (!empty($params['instrument_type'])) {
                $normalizedType = strtolower(trim($params['instrument_type']));
                if (str_contains($normalizedType, 'power of attorney') ||
                    str_contains($normalizedType, 'devolution order') ||
                    str_contains($normalizedType, 'deed of assignment') ||
                    str_contains($normalizedType, 'deed of gift') ||
                    str_contains($normalizedType, 'surrender') ||
                    str_contains($normalizedType, 'release') ||
                    str_contains($normalizedType, 'lease') ||
                    str_contains($normalizedType, 'merger') ||
                    str_contains($normalizedType, 'sub-division') ||
                    str_contains($normalizedType, 'certificate of occupancy')) {
                    $isSingular = true;
                }
            }

            $hasIdentifiers = !empty($params['fileno']) || !empty($params['reg_no']) || !empty($params['prop_id']);

            if (!$isSingular && !$hasIdentifiers) {
                $query->where('instrument_date', $params['instrument_date']);
            }
        }

        return $query->first();
    }

    // ──────────────────────────────────────────────
    //  CofO Staging — CofO_staging table (Property Record Assistant CofO branch)
    // ──────────────────────────────────────────────
    protected function checkCofoStaging(array $params)
    {
        $query = DB::connection('sqlsrv')->table('CofO_staging')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });

        if (!empty($params['exclude_id'])) {
            $query->where('id', '!=', $params['exclude_id']);
        }

        if (!empty($params['cofo_type'])) {
            $query->where('cofo_type', $params['cofo_type']);
        }

        // Strongest identifiers
        if (!empty($params['prop_id'])) {
            $query->where('prop_id', $params['prop_id']);
        }

        if (!empty($params['cofo_no'])) {
            $query->where('cofo_no', $params['cofo_no']);
        }

        // Registration particulars: regNo OR vol/page/serial combo
        if (!empty($params['reg_no']) || (!empty($params['vol']) && !empty($params['page']) && !empty($params['serial']))) {
            $query->where(function ($q) use ($params) {
                if (!empty($params['reg_no'])) {
                    $q->where('regNo', $params['reg_no']);
                }
                if (!empty($params['vol']) && !empty($params['page']) && !empty($params['serial'])) {
                    $q->orWhere(function ($sq) use ($params) {
                        $sq->where('volumeNo', $params['vol'])
                           ->where('pageNo', $params['page'])
                           ->where('serialNo', $params['serial']);
                    });
                }
            });
        }

        if (!empty($params['fileno'])) {
            $this->applyFilenoFilter(
                $query,
                $params['fileno'],
                ['mlsFNo', 'kangisFileNo', 'NewKANGISFileno', 'np_fileno', 'temp_fileno', 'fileno']
            );
        }

        if (!empty($params['transaction_type'])) {
            $query->where('transaction_type', $params['transaction_type']);
        }

        // CofO is unique per file/prop_id/cofo_no; do not strictly match date if those are present
        if (!empty($params['transaction_date']) && empty($params['cofo_no']) && empty($params['prop_id']) && empty($params['fileno'])) {
            $query->whereDate('transaction_date', $params['transaction_date']);
        }

        if (!empty($params['party_1'])) {
            $name = $params['party_1'];
            $query->where(function ($q) use ($name) {
                $q->where('party_1', 'LIKE', '%' . $name . '%')
                  ->orWhere('Grantee', 'LIKE', '%' . $name . '%')
                  ->orWhere('Assignee', 'LIKE', '%' . $name . '%');
            });
        }

        return $query->first();
    }

    // ──────────────────────────────────────────────
    //  PRA — pra table (Property Record Archive)
    // ──────────────────────────────────────────────
    protected function checkPra(array $params)
    {
        $query = DB::connection('sqlsrv')->table('pra')
            ->where(function ($q) {
                $q->where('is_deleted', 0)->orWhereNull('is_deleted');
            });

        if (!empty($params['exclude_id'])) {
            $query->where('id', '!=', $params['exclude_id']);
        }

        if (!empty($params['prop_id'])) {
            $query->where('prop_id', $params['prop_id']);
        }

        // Registration particulars: regNo OR vol/page/serial combo
        if (!empty($params['reg_no']) || (!empty($params['vol']) && !empty($params['page']) && !empty($params['serial']))) {
            $query->where(function ($q) use ($params) {
                if (!empty($params['reg_no'])) {
                    $q->where('regNo', $params['reg_no']);
                }
                if (!empty($params['vol']) && !empty($params['page']) && !empty($params['serial'])) {
                    $q->orWhere(function ($sq) use ($params) {
                        $sq->where('volumeNo', $params['vol'])
                           ->where('pageNo', $params['page'])
                           ->where('serialNo', $params['serial']);
                    });
                }
            });
        }

        if (!empty($params['fileno'])) {
            $this->applyFilenoFilter($query, $params['fileno'], ['mlsFNo', 'kangisFileNo', 'temp_fileno']);
        }

        if (!empty($params['party_1'])) {
            $query->where('party_1', 'LIKE', '%' . $params['party_1'] . '%');
        }

        if (!empty($params['transaction_type'])) {
            $query->where('transaction_type', $params['transaction_type']);
        }

        return $query->first();
    }
}
