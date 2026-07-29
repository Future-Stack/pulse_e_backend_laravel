<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirm Your Email</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #2a9d8f; color: #ffffff; padding: 20px; text-align: center; }
        .content { padding: 30px; color: #333333; }
        .button { display: inline-block; padding: 12px 20px; background: #2a9d8f; color: #ffffff; text-decoration: none; border-radius: 4px; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #888888; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>Welcome to Our Beta Waitlist</h2>
    </div>
    <div class="content">
        <p>Hi {{ $entry->full_name?? 'there' }}, thanks for your interest in Neumera.</p>
        <p>Please confirm you'd like to join the waitlist. One click and you're set:</p>
        <p style="text-align:center; color: white">
            <a href="{{ $confirmationUrl }}" class="button">Confirm My Email</a>
        </p>
        <p>If you didn't request this, you can ignore this email and nothing will happen. This link expires in 14 days.</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} The Neumera team</p>
        <p> <a href="{{ $unsubscribeUrl }}"> [Unsubscribe] </a></p>
    </div>
</div>
</body>
</html>
