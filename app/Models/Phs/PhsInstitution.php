<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PhsInstitution extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'phs_institutions';

    protected $fillable = [
        'name',
        'username',
        'type',
        'email',
        'phone',
        'token_balance',
        'primary_color',
        'secondary_color',
        'logo_path',
        'banner_path',
        'status',
        'low_balance_notified_at',
    ];

    protected $casts = [
        'low_balance_notified_at' => 'datetime',
    ];

    public function members()
    {
        return $this->hasMany(PhsMember::class, 'phs_institution_id');
    }

    public function transactions()
    {
        return $this->hasMany(PhsTokenTransaction::class, 'phs_institution_id');
    }

    public function searchLogs()
    {
        return $this->hasMany(PhsSearchLog::class, 'phs_institution_id');
    }

    public function emailHistories()
    {
        return $this->hasMany(PhsEmailHistory::class, 'phs_institution_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Build a unique username suggestion derived from the organization name.
     * Shared by the onboarding email, the registration form, and any other
     * place that needs to propose/validate an organization username.
     */
    public static function suggestUsername(?string $organizationName): string
    {
        $base = \Str::slug((string) $organizationName, '_');

        if ($base === '') {
            $base = 'org';
        }

        $username = $base;
        $suffix = 1;

        while (static::where('username', $username)->exists()) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }

    /**
     * Credit tokens to the wallet and write a ledger row (atomic).
     *
     * @param string $type purchase | bonus | adjustment
     */
    public function addTokens(int $amount, string $type = 'purchase', array $meta = [], ?int $memberId = null): PhsTokenTransaction
    {
        $amount = abs($amount);

        return DB::connection('sqlsrv')->transaction(function () use ($amount, $type, $meta, $memberId) {
            $this->refresh();
            $newBalance = (int) $this->token_balance + $amount;
            $this->token_balance = $newBalance;
            $this->save();

            return $this->transactions()->create(array_merge([
                'phs_member_id'  => $memberId,
                'type'           => $type,
                'tokens'         => $amount,
                'balance_after'  => $newBalance,
                'status'         => 'completed',
            ], $meta));
        });
    }

    /**
     * Debit tokens for a search (atomic). Returns the ledger row, or null when
     * there are insufficient tokens.
     */
    public function deductTokens(int $amount = 1, ?int $memberId = null, array $meta = []): ?PhsTokenTransaction
    {
        $amount = abs($amount);

        return DB::connection('sqlsrv')->transaction(function () use ($amount, $memberId, $meta) {
            $this->refresh();
            if ((int) $this->token_balance < $amount) {
                return null;
            }
            $newBalance = (int) $this->token_balance - $amount;
            $this->token_balance = $newBalance;
            $this->save();

            return $this->transactions()->create(array_merge([
                'phs_member_id'  => $memberId,
                'type'           => 'search_debit',
                'tokens'         => -$amount,
                'balance_after'  => $newBalance,
                'status'         => 'completed',
            ], $meta));
        });
    }
}
