<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reset your password</title>
</head>
<body style="font-family: Inter, system-ui, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e2e8f0;">
        <h1 style="font-size: 22px; color: #0f172a; margin: 0 0 12px;">Reset your password</h1>
        <p style="color: #475569; margin: 0 0 24px;">Hi {{ $name }}, we received a request to reset your Marketly password. Click the button below to choose a new one.</p>
        <a href="{{ $resetUrl }}" style="display: inline-block; padding: 12px 24px; background: #f59e0b; color: #0f172a; border-radius: 8px; text-decoration: none; font-weight: 600;">Reset password</a>
        <p style="color: #94a3b8; font-size: 12px; margin-top: 24px;">If the button doesn't work, paste this URL into your browser:<br>{{ $resetUrl }}</p>
        <p style="color: #94a3b8; font-size: 12px; margin-top: 24px;">This link expires in 60 minutes. If you didn't request this, you can safely ignore the email.</p>
    </div>
</body>
</html>