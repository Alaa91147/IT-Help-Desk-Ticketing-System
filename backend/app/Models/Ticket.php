<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $table = 'tickets';

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'ticketNumber',
        'userId',
        'assignedUserId',
        'categoryId',
        'priorityId',
        'statusId',
        'subject',
        'description',
        'resolvedAt',
        'closedAt',
    ];

    protected $casts = [
        'resolvedAt' => 'datetime',
        'closedAt' => 'datetime',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignedUserId');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'categoryId');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class, 'priorityId');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'statusId');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'ticketId');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            TicketAttachment::class,
            'ticketId'
        );
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'ticketId');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'ticketId');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(
            TicketAssignment::class,
            'ticketId'
        );
    }
}