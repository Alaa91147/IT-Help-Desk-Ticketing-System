<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    protected $table = 'ticketattachments';

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'ticketId',
        'uploadedByUserId',
        'fileName',
        'filePath',
        'fileType',
        'fileSize',
    ];

    protected $casts = [
        'fileSize' => 'integer',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticketId');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploadedByUserId'
        );
    }
}