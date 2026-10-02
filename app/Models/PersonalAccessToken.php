<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Force Sanctum to look up tokens in the same SQL Server DB
     * where the personal_access_tokens table was migrated.
     */
    protected $connection = 'sqlsrv';
}
