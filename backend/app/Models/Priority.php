<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Priority extends Model
{
    protected $table = 'priorities';

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'priorityName',
        'description',
        'priorityLevel',
        'isActive',
    ];

    protected $casts = [
        'priorityLevel' => 'integer',
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'priorityId');
    }
}