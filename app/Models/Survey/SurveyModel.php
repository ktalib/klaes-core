<?php

namespace App\Models\Survey;

use App\Models\Concerns\HasAddressBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Base for the Survey Department module's tables.
 *
 * All of them live on the sqlsrv connection (where the land data is), soft
 * delete rather than disappear, and stamp created_by / updated_by.
 */
abstract class SurveyModel extends Model
{
    use SoftDeletes;
    use HasAddressBuilder;

    protected $connection = 'sqlsrv';

    protected $guarded = ['id'];

    /**
     * Street, district and LGA are multi-select, so they arrive as arrays
     * straight from the request. Collapse them to the single delimited string
     * the column stores, here rather than in each controller — six controllers
     * pass validated input through to create()/update() untouched.
     */
    public function setAttribute($key, $value)
    {
        if (is_array($value) && preg_match('/^(prop_|addr_)(street|district|lga)$/', (string) $key)) {
            $value = \App\Support\AddressBuilder::toColumn($value);
        }

        return parent::setAttribute($key, $value);
    }

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
