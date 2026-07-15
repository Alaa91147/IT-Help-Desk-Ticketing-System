<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAssignment extends Model
{
    protected $table = 'ticketassignments';

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'ticketId',
        'assignedUserId',
        'assignedByUserId',
        'assignedAt',
    ];

    protected $casts = [
        'assignedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticketId');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assignedUserId'
        );
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'assignedByUserId'
        );
    }
}