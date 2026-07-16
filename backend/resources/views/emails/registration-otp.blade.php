<x-mail::message>
# Verify Your Email

Hello {{ $firstName }},

Thank you for registering for the IT Help Desk System.

Use the verification code below to complete your registration:

<x-mail::panel>
# {{ $otp }}
</x-mail::panel>

This code expires in {{ $expiresInMinutes }} minutes.

If you did not create this account, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>