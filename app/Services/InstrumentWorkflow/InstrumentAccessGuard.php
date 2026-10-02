<?php

namespace App\Services\InstrumentWorkflow;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Role checks for the Instrument Registration Workflow.
 *
 * The route files only carry `auth` and the sidebar hides links by role, which
 * stops nobody who types a URL. Every workflow action calls this instead.
 * Role names per action live in config('instrument_workflow.roles'); Super Admin
 * (either spelling) always passes, as everywhere else in the app.
 */
class InstrumentAccessGuard
{
    public function allows(string $action, ?User $user = null): bool
    {
        $user = $user ?? Auth::user();

        if (!$user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $allowed = array_map(fn ($role) => $this->normalize($role), self::rolesFor($action));

        if ($allowed === []) {
            return false;
        }

        $held = array_map(fn ($role) => $this->normalize($role), $user->assignedRoleNames());

        return array_intersect($allowed, $held) !== [];
    }

    public function authorize(string $action, ?User $user = null): void
    {
        abort_unless($this->allows($action, $user), 403, 'You do not have permission to perform this Instrument Registration action.');
    }

    /** The subset of $actions the user may perform, for showing/hiding buttons. */
    public function abilities(array $actions, ?User $user = null): array
    {
        $abilities = [];
        foreach ($actions as $action) {
            $abilities[$action] = $this->allows($action, $user);
        }

        return $abilities;
    }

    /** Holding any workflow role is enough to view applications. */
    public function authorizeView(?User $user = null): void
    {
        foreach ($this->allActions() as $action) {
            if ($action !== 'bir.review' && $this->allows($action, $user)) {
                return;
            }
        }

        abort(403, 'You do not have access to Instrument Registration applications.');
    }

    public function allActions(): array
    {
        return array_keys((array) config('instrument_workflow.roles', []));
    }

    /** @var array<string, list<string>>|null roles set in Configurable Entries, by action */
    private static ?array $overrides = null;

    /**
     * The role names allowed for an action: the list saved in Configurable Entries
     * when there is one, otherwise config('instrument_workflow.roles').
     */
    public static function rolesFor(string $action): array
    {
        if (self::$overrides === null) {
            self::$overrides = [];
            try {
                if (\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable('instrument_workflow_action_roles')) {
                    self::$overrides = \App\Models\InstrumentWorkflow\WorkflowActionRole::query()
                        ->get()
                        ->mapWithKeys(fn ($row) => [$row->action => array_values(array_filter((array) $row->roles))])
                        ->all();
                }
            } catch (\Throwable $e) {
                self::$overrides = [];
            }
        }

        if (array_key_exists($action, self::$overrides)) {
            return self::$overrides[$action];
        }

        // Read the map then index it: the action keys themselves contain dots
        // ('bir.review'), which config() would treat as nesting and return null.
        return (array) (((array) config('instrument_workflow.roles', []))[$action] ?? []);
    }

    public static function flushRoles(): void
    {
        self::$overrides = null;
    }

    private function normalize(string $role): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($role)));
    }
}

