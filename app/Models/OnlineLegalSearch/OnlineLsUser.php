<?php

namespace App\Models\OnlineLegalSearch;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class OnlineLsUser extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $connection = 'sqlsrv';
    protected $table = 'online_ls_users';

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'organization',
        'password',
        'status',
        'activation_token',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'activation_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
