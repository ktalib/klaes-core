<?php

namespace App\Http\Controllers\SystemAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\InstrumentWorkflow\Concerns\RunsWorkflowActions;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Configurable Entities > Units (CR 30102026 item 34, user 2026-11-01): the units
 * inside a department that File Movement offers, optionally, on "Send to". The
 * department is the offices.department name File Movement groups offices by.
 * Saves only; the tab itself is drawn by ConfigurableEntriesController.
 */
class FileMovementUnitsController extends Controller
{
    use RunsWorkflowActions;

    private function db()
    {
        return DB::connection('sqlsrv');
    }

    /** The departments File Movement knows (offices.department). */
    public static function departments(): array
    {
        return DB::connection('sqlsrv')->table('offices')->where('is_active', 1)->whereNotNull('department')
            ->distinct()->orderBy('department')->pluck('department')
            ->map(fn ($d) => trim((string) $d))->filter()->unique(fn ($d) => mb_strtolower($d))->values()->all();
    }

    public function save(Request $r, ?int $id = null)
    {
        $this->authorizeAdmin();
        $d = $r->validate([
            'department' => ['required', 'string', Rule::in(self::departments())],
            'name'       => 'required|string|max:150',
        ], [
            'department.in' => 'Choose a department from the list.',
            'name.required' => 'Name the unit, e.g. Recommendation.',
        ]);
        // Spacing tidied; the wording and capitals are kept as typed (RofO, OSS).
        $name = trim(preg_replace('/\s+/', ' ', $d['name']));

        return $this->attempt(function () use ($id, $d, $name) {
            $row = $id ? $this->db()->table('file_movement_units')->where('id', $id)->first() : null;
            if ($id && ! $row) throw WorkflowGuardException::because('That unit no longer exists. Reload the page.');

            // One unit of a name per department, whatever the capitals (the collation ignores case).
            $dup = $this->db()->table('file_movement_units')->where('department', $d['department'])->where('name', $name)
                ->when($id, fn ($q) => $q->where('id', '<>', $id))->value('name');
            if ($dup) throw WorkflowGuardException::because("{$d['department']} already has a unit called {$dup}.");

            $values = ['department' => $d['department'], 'name' => $name, 'updated_by' => Auth::id(), 'updated_at' => now()];
            $id ? $this->db()->table('file_movement_units')->where('id', $id)->update($values)
                : $this->db()->table('file_movement_units')->insert($values + ['is_active' => 1, 'created_by' => Auth::id(), 'created_at' => now()]);
            Log::info('Configurable Entries: File Movement unit saved', ['id' => $id, 'before' => $row ? (array) $row : null, 'after' => $values, 'by' => Auth::id()]);

            return 'Saved.';
        }, 'Saved.');
    }

    /** Switch a unit on or off; an off unit is no longer offered, sends that named it keep it. */
    public function toggle(int $id)
    {
        $this->authorizeAdmin();

        return $this->attempt(function () use ($id) {
            $row = $this->db()->table('file_movement_units')->where('id', $id)->first();
            if (! $row) throw WorkflowGuardException::because('That unit no longer exists. Reload the page.');
            $this->db()->table('file_movement_units')->where('id', $id)
                ->update(['is_active' => $row->is_active ? 0 : 1, 'updated_by' => Auth::id(), 'updated_at' => now()]);
            Log::info('Configurable Entries: File Movement unit switched', ['id' => $id, 'is_active' => ! $row->is_active, 'by' => Auth::id()]);

            return $row->is_active ? 'Switched off. It is no longer offered; files already sent to it keep it.' : 'Switched back on.';
        }, 'Updated.');
    }

    /** Delete a unit no file has been sent to; one that has been used can only be switched off. */
    public function delete(int $id)
    {
        $this->authorizeAdmin();

        return $this->attempt(function () use ($id) {
            $row = $this->db()->table('file_movement_units')->where('id', $id)->first();
            if (! $row) throw WorkflowGuardException::because('That unit no longer exists. Reload the page.');
            // Sends store the unit in the movement log as "receiving_unit_id":<id>.
            $used = $this->db()->table('file_tracker')->where(function ($q) use ($id) {
                $q->where('movement_log', 'like', '%"receiving_unit_id":' . $id . ',%')
                  ->orWhere('movement_log', 'like', '%"receiving_unit_id":' . $id . '}%');
            })->count();
            if ($used) throw WorkflowGuardException::because("{$used} file(s) have been sent to this unit. Switch it off instead.");
            $this->db()->table('file_movement_units')->where('id', $id)->delete();
            Log::info('Configurable Entries: File Movement unit deleted', ['row' => (array) $row, 'by' => Auth::id()]);

            return 'Deleted.';
        }, 'Deleted.');
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        $roles = array_map(fn ($r) => strtolower(trim((string) $r)), $user?->assignedRoleNames() ?? []);

        abort_unless($user && ($user->isSuperAdmin() || in_array('system settings', $roles, true)), 403, 'Configurable Entities needs the System Settings role.');
    }
}
