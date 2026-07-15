<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'roleId',
        'firstName',
        'lastName',
        'email',
        'phoneNumber',
        'password',
        'isActive',
        'emailVerifiedAt',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'emailVerifiedAt' => 'datetime',
        'isActive' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'roleId');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'userId');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignedUserId');
    }

    public function ticketComments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'userId');
    }

    public function uploadedAttachments(): HasMany
    {
        return $this->hasMany(
            TicketAttachment::class,
            'uploadedByUserId'
        );
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'userId');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'userId');
    }

    public function receivedAssignments(): HasMany
    {
        return $this->hasMany(
            TicketAssignment::class,
            'assignedUserId'
        );
    }

    public function createdAssignments(): HasMany
    {
        return $this->hasMany(
            TicketAssignment::class,
            'assignedByUserId'
        );
    }
}