<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'roleId' => $this->roleId,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'fullName' => trim(
                $this->firstName . ' ' . $this->lastName
            ),
            'email' => $this->email,
            'phoneNumber' => $this->phoneNumber,
            'isActive' => $this->isActive,

            'isEmailVerified' => $this->emailVerifiedAt !== null,
            'emailVerifiedAt' => $this->emailVerifiedAt,

            'role' => $this->whenLoaded(
                'role',
                function (): ?array {
                    if (!$this->role) {
                        return null;
                    }

                    return [
                        'id' => $this->role->id,
                        'roleName' => $this->role->roleName,
                    ];
                }
            ),

            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}