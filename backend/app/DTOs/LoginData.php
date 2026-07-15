<?php

namespace App\DTOs;

use App\Http\Requests\LoginRequest;

readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public string $deviceName,
    ) {
    }

    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            email: strtolower($request->string('email')->toString()),
            password: $request->string('password')->toString(),
            deviceName: $request->filled('deviceName')
                ? $request->string('deviceName')->toString()
                : 'react-frontend',
        );
    }
}