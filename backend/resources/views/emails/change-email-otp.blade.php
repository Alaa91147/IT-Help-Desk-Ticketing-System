<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verify Email Change</title>
</head>
<body style="font-family: Arial, sans-serif;">

    <h2>Verify Your New Email</h2>

    <p>You requested to change the email address on your account.</p>

    <p>Your verification code is:</p>

    <h1 style="letter-spacing:5px;">
        {{ $otp }}
    </h1>

    <p>This code expires in 10 minutes.</p>

    <p>If you did not request this change, you can safely ignore this email.</p>

</body>
</html>