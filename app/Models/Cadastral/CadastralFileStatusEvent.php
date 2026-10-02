<?php

namespace App\Models\Cadastral;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a file's status history (revoked, reinstated, withdrawn, change
 * of purpose, opened, closed). Append-only: the card carries the current status,
 * this carries how it got there.
 */
class CadastralFileStatusEvent extends CadastralModel
{
    protected $table = 'cadastral_file_status_events';

    protected $casts = [
        'cadastral_index_card_id' => 'integer',
        'effective_date' => 'date',
        'notified'       => 'boolean',
        'notified_at'    => 'datetime',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(CadastralIndexCard::class, 'cadastral_index_card_id');
    }
}
