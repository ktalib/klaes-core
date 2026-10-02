<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandOfficer extends Model
{
    protected $connection = 'sqlsrv';

    protected $table = 'signing_officers';

    protected $fillable = [
        'name',
        'rank',
        'signature_file',
        'user_id',
        'verification_code',
        'verification_code_expires_at',
        'notification_type',
    ];

    protected $casts = [
        'verification_code_expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /*
     | This table holds TWO kinds of signatory, told apart by user_id.
     |
     | STAFF OFFICERS are KLAES users who sign in the course of their work -- survey
     | reports, legal searches, digital file requests. They are created from the Land
     | Officer Settings screen, which REQUIRES a user_id, so every one of them has one.
     |
     | DOCUMENT SIGNATORIES are the office holders whose name and signature are printed
     | on issued documents -- the Honourable Commissioner, His Excellency the Governor.
     | They are not KLAES users and never sign in, so they carry no user_id. They are
     | maintained from System Admin -> Configurable Entries -> Signatories.
     |
     | The distinction is user_id and NOT `rank`: the Land Officer Settings screen sets
     | rank from its own form, so a staff officer can perfectly well have one.
     */

    /** Office holders printed on documents: no KLAES account behind them. */
    public function scopeDocumentSignatories($query)
    {
        return $query->whereNull('user_id');
    }

    /** Officers who are KLAES users. Everything that offers "who signs this?" wants these. */
    public function scopeStaffOfficers($query)
    {
        return $query->whereNotNull('user_id');
    }
}
