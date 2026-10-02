<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The Cadastral Department staff directory.
 *
 * Predates the Cadastral Module: SurveyReportController reads it for the Land 12
 * officer dropdown, and that must keep working. The Cadastral Module extends it
 * additively with the job post (post_code) the report workflow routes on, so the
 * Land 12 dropdown and the workflow share one directory rather than drifting.
 *
 * Posts are NOT user_roles rows. config/module_permissions.php is explicit that
 * modules are named menu areas, not job roles; the post catalogue lives in
 * config('cadastral_module.posts').
 */
class CadastralOfficer extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_officers';

    protected $fillable = [
        'name',
        'rank',
        'user_id',
        'post_code',
        'department',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** Human label for the job post, e.g. "Chart Officer I". */
    public function getPostLabelAttribute(): ?string
    {
        return $this->post_code
            ? (config('cadastral_module.posts')[$this->post_code] ?? $this->post_code)
            : null;
    }

    /**
     * The post codes a user holds. Empty when the user is not in the directory,
     * which is why the workflow treats "no post" as "cannot act on a step that
     * names a post" rather than as an error.
     *
     * @return array<int, string>
     */
    public static function postsFor(?int $userId): array
    {
        if (! $userId) {
            return [];
        }

        return static::query()
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->whereNotNull('post_code')
            ->pluck('post_code')
            ->unique()
            ->values()
            ->all();
    }

    /** Users holding a given post, for notifying the next desk. */
    public static function userIdsForPost(?string $postCode): array
    {
        if (! $postCode) {
            return [];
        }

        return static::query()
            ->where('post_code', $postCode)
            ->where(fn ($q) => $q->where('is_active', true)->orWhereNull('is_active'))
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }
}
