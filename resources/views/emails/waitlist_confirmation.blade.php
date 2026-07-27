<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirm Your Email</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f9f9f9; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; }
        .header { background: #4CAF50; color: #ffffff; padding: 20px; text-align: center; }
        .content { padding: 30px; color: #333333; }
        .button { display: inline-block; padding: 12px 20px; background: #4CAF50; color: #ffffff; text-decoration: none; border-radius: 4px; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #888888; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h2>Welcome to Our Beta Waitlist</h2>
    </div>
    <div class="content">
        <p>Hi {{ $user->name ?? 'there' }}, </p>
        <p>Thank you for joining our waitlist! To confirm your spot, please click the button below:</p>
        <p style="text-align:center;">
            <a href="{{ $confirmationUrl }}" class="button">Confirm My Email</a>
        </p>
        <p>If you did not request to join, you can safely ignore this message.</p>
    </div>
    <div class="footer">
        <p>&copy; {{ date('Y') }} Your Company. All rights reserved.</p>
    </div>
</div>
</body>
</html>
