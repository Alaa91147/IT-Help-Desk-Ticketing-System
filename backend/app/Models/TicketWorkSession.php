<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketWorkSession extends Model
{
    protected $table = 'ticketworksessions';

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'ticketId',
        'userId',
        'startedAt',
        'endedAt',
        'durationSeconds',
        'stopReason',
    ];

    protected $casts = [
        'startedAt' => 'datetime',
        'endedAt' => 'datetime',
        'durationSeconds' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticketId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function isActive(): bool
    {
        return $this->endedAt === null;
    }
}