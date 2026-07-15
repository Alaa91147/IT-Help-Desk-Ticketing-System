<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activitylogs';

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'userId',
        'ticketId',
        'action',
        'description',
        'oldValues',
        'newValues',
        'ipAddress',
    ];

    protected $casts = [
        'oldValues' => 'array',
        'newValues' => 'array',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticketId');
    }
}