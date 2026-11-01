<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Order #{{ $order->id }} confirmed</title>
</head>
<body style="font-family: Inter, system-ui, sans-serif; background-color: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e2e8f0;">
        <h1 style="font-size: 22px; color: #0f172a; margin: 0 0 12px;">Thanks for your order</h1>
        <p style="color: #475569; margin: 0 0 24px;">Your order <strong>#{{ $order->id }}</strong> has been received and is being processed.</p>
        <table style="width: 100%; border-collapse: collapse;">
            <tr><td style="padding: 6px 0; color: #64748b;">Order ID</td><td style="padding: 6px 0; text-align: right;">#{{ $order->id }}</td></tr>
            <tr><td style="padding: 6px 0; color: #64748b;">Total</td><td style="padding: 6px 0; text-align: right;">${{ number_format($order->total, 2) }}</td></tr>
            <tr><td style="padding: 6px 0; color: #64748b;">Payment method</td><td style="padding: 6px 0; text-align: right;">{{ $order->payment_method }}</td></tr>
            <tr><td style="padding: 6px 0; color: #64748b;">Status</td><td style="padding: 6px 0; text-align: right;">{{ $order->status }}</td></tr>
        </table>
        <p style="color: #94a3b8; font-size: 12px; margin-top: 24px;">If you have any questions, just reply to this email.</p>
    </div>
</body>
</html>