<?php

namespace App\Models\Phs;

use Illuminate\Database\Eloquent\Model;

class PhsEmailHistory extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'phs_email_histories';

    protected $fillable = [
        'phs_institution_id',
        'phs_onboarding_request_id',
        'recipient_email',
        'subject',
        'body_html',
        'body_text',
        'message_id',
        'mailable',
        'mailer',
        'meta',
        'sent_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'sent_at' => 'datetime',
    ];

    public function institution()
    {
        return $this->belongsTo(PhsInstitution::class, 'phs_institution_id');
    }

    public function onboardingRequest()
    {
        return $this->belongsTo(PhsOnboardingRequest::class, 'phs_onboarding_request_id');
    }
}