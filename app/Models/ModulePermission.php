<?php

namespace App\Models;

use App\Support\Permissions\ModuleName;
use Illuminate\Database\Eloquent\Model;

/**
 * One user's action grants on one module. See the create migration for why the key is a
 * normalized module NAME rather than a user_roles id.
 */
class ModulePermission extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'module_permissions';

    protected $fillable = [
        'user_id',
        'module_name',
        'can_view',
        'can_create',
        'can_edit',
        'can_delete',
        'can_print',
        'can_export',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'can_view' => 'boolean',
        'can_create' => 'boolean',
        'can_edit' => 'boolean',
        'can_delete' => 'boolean',
        'can_print' => 'boolean',
        'can_export' => 'boolean',
    ];

    /** The actions this table can grant, in the order the user modal shows them. */
    public const ACTIONS = ['view', 'create', 'edit', 'approve', 'delete', 'print', 'export'];

    /**
     * Actions that only make sense on some modules.
     *
     * Approving is an act of authority and most modules have nothing to approve, so the grid
     * offers the column only where the module actually has an approve/reject route — see
     * App\Support\Permissions\ModuleRoutes::approvableModules().
     */
    public const CONDITIONAL_ACTIONS = ['approve'];

    /** Column holding the grant for one action. */
    public static function column(string $action): ?string
    {
        return in_array($action, self::ACTIONS, true) ? 'can_' . $action : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Names are stored normalized; enforce it on the model so no writer can skip it. */
    public function setModuleNameAttribute($value): void
    {
        $this->attributes['module_name'] = ModuleName::normalize($value);
    }
}
