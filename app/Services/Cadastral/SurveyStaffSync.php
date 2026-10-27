<?php

namespace App\Services\Cadastral;

use App\Models\Survey\SurveyorListEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the Cadastral Surveyors directory (cadastral_surveyors) in step with the Survey
 * Department staff list (surveyors_list) (user, 2026-10-27). Each staff member has one
 * Cadastral row, linked by surveyors_list_id: added when missing, its name, phone, email and
 * active flag refreshed from Survey. Survey is the source: these rows are edited there.
 * Nothing is deleted; staff switched off in Survey are switched off here.
 *
 * Government surveyors: no SURCON number, firm "Survey Department", licence Active.
 */
class SurveyStaffSync
{
    public const FIRM = 'Survey Department';

    public static function installed(): bool
    {
        return Schema::connection('sqlsrv')->hasColumn('cadastral_surveyors', 'surveyors_list_id');
    }

    /**
     * Sync every staff member (or work out what would change, when $apply is false).
     *
     * @return array{add: int, update: int, same: int, rows: array<int, array{action: string, name: string, staff_id: ?string}>}
     */
    public function syncAll(bool $apply): array
    {
        $out = ['add' => 0, 'update' => 0, 'same' => 0, 'rows' => []];
        foreach (SurveyorListEntry::query()->orderBy('id')->get() as $staff) {
            $action = $this->syncOne($staff, $apply);
            $out[$action]++;
            $out['rows'][] = ['action' => $action, 'name' => $staff->full_name, 'staff_id' => $staff->staff_id];
        }

        return $out;
    }

    /** @return string add | update | same */
    public function syncOne(SurveyorListEntry $staff, bool $apply = true): string
    {
        if (!self::installed()) {
            return 'same';
        }
        $db = DB::connection('sqlsrv');
        $values = [
            'full_name' => mb_substr($staff->full_name, 0, 255),
            'phone' => $staff->phone ?: null,
            'email' => $staff->email ?: null,
            'is_active' => (bool) $staff->is_active,
        ];
        $row = $db->table('cadastral_surveyors')->where('surveyors_list_id', $staff->id)->whereNull('deleted_at')->first();

        if (!$row) {
            if ($apply) {
                $db->table('cadastral_surveyors')->insert($values + [
                    'surveyors_list_id' => $staff->id,
                    'firm_name' => self::FIRM,
                    'licence_status' => 'Active',
                    'addr_state' => 'Kano',
                    'created_by' => auth()->id(),
                    'updated_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return 'add';
        }

        $changed = array_filter($values, fn ($v, $k) => (string) $row->{$k} !== (string) ($k === 'is_active' ? (int) $v : $v), ARRAY_FILTER_USE_BOTH);
        if (!$changed) {
            return 'same';
        }
        if ($apply) {
            $db->table('cadastral_surveyors')->where('id', $row->id)->update($changed + ['updated_by' => auth()->id(), 'updated_at' => now()]);
        }

        return 'update';
    }
}
