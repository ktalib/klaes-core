<?php

namespace App\Models\CadastralDept;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One Attach / Detach of a case's correspondence file at Registry. */
class CadastralCorrespondenceCustody extends Model
{
    public const DETACH = 'detach';
    public const ATTACH = 'attach';

    protected $connection = 'sqlsrv';
    protected $table = 'cadastral_correspondence_custody';

    protected $guarded = ['id'];

    protected $casts = ['done_at' => 'datetime'];

    public function doer()
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    public function label(): string
    {
        return $this->action === self::DETACH ? 'Detached' : 'Attached';
    }
}
