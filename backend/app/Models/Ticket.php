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
        'ai_metadata',
        'ai_category_id',
        'ai_priority_id',
        'duplicate_of',
        'agent_requester_id',
        'agent_request_status',
        'agent_requested_at',
        'dueAt',
        'startedAt',
        'escalatedAt',
        'cancelledAt',
        'resolutionNote',
        'escalationReason',
        'cancellationReason',
        'resolvedAt',
        'closedAt',
    ];

    protected $casts = [
        'dueAt' => 'datetime',
        'startedAt' => 'datetime',
        'escalatedAt' => 'datetime',
        'cancelledAt' => 'datetime',
        'resolvedAt' => 'datetime',
        'closedAt' => 'datetime',
        'ai_metadata' => 'array',
        'agent_requested_at' => 'datetime',
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

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_requester_id');
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
        return $this->hasMany(TicketAttachment::class, 'ticketId');
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
        return $this->hasMany(TicketAssignment::class, 'ticketId');
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(TicketWorkSession::class, 'ticketId');
    }
}