<?php

namespace App\Models\Cadastral;

use App\Models\Concerns\HasAddressBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Base for the Cadastral Department module's tables.
 *
 * A structural twin of Survey\SurveyModel rather than a subclass of it: the two
 * modules need the same things (sqlsrv, soft deletes, created_by/updated_by,
 * a reference generator) but the Survey conventions declare that module
 * standalone, and subclassing would couple them.
 */
abstract class CadastralModel extends Model
{
    use SoftDeletes;
    use HasAddressBuilder;

    protected $connection = 'sqlsrv';

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (Auth::check()) {
                $m->created_by = $m->created_by ?: Auth::id();
                $m->updated_by = $m->updated_by ?: Auth::id();
            }
        });

        static::updating(function (self $m) {
            if (Auth::check()) $m->updated_by = Auth::id();
        });
    }

    /**
     * Next reference in a "PREFIX-YYYY-000" series, based on the highest
     * existing value for the current year.
     */
    public static function nextRef(string $column, string $prefix, int $pad = 3): string
    {
        $year = date('Y');
        $like = "{$prefix}-{$year}-%";

        $last = static::withTrashed()
            ->where($column, 'like', $like)
            ->orderByDesc($column)
            ->value($column);

        $n = 0;
        if ($last && preg_match('/(\d+)$/', $last, $m)) $n = (int) $m[1];

        return sprintf('%s-%s-%0' . $pad . 'd', $prefix, $year, $n + 1);
    }
}
