<?php

namespace App\DTOs;

use App\Http\Requests\RegisterRequest;

readonly class RegisterData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $phoneNumber,
        public string $password,
    ) {
    }

    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            firstName: $request->string('firstName')->toString(),
            lastName: $request->string('lastName')->toString(),
            email: strtolower($request->string('email')->toString()),
            phoneNumber: $request->filled('phoneNumber')
                ? $request->string('phoneNumber')->toString()
                : null,
            password: $request->string('password')->toString(),
        );
    }
}