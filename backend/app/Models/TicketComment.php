<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketComment extends Model
{
    protected $table = 'ticketcomments';

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'ticketId',
        'userId',
        'parentCommentId',
        'comment',
        'isInternal',
    ];

    protected $casts = [
        'isInternal' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(
            Ticket::class,
            'ticketId'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'userId'
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            TicketComment::class,
            'parentCommentId'
        );
    }

    public function replies(): HasMany
    {
        return $this->hasMany(
            TicketComment::class,
            'parentCommentId'
        )->orderBy('createdAt');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            TicketAttachment::class,
            'commentId'
        )->orderBy('createdAt');
    }
}