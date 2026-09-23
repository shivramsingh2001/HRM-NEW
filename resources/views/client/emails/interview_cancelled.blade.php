<!DOCTYPE html>
<html>
<head>
    <title>Interview Cancelled</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f7fa; margin:0; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: white; padding: 24px 20px; text-align: center; }
        .content { padding: 24px; }
        .info-box { background: #eef2ff; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #1e3a8a; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h2>Interview Cancelled</h2></div>
        <div class="content">
            <p>Dear {{ $candidate->first_name }},</p>
            <p>Your interview for <strong>{{ $jobOpening->title }}</strong> scheduled on
                {{ \Carbon\Carbon::parse($interview->scheduled_date)->format('l, d M Y') }} at
                {{ \Carbon\Carbon::parse($interview->scheduled_time)->format('h:i A') }} has been cancelled.</p>
            @if($reason)
                <div class="info-box"><strong>Reason:</strong> {{ $reason }}</div>
            @endif
            <p>Our recruitment team will reach out if a new interview needs to be scheduled.</p>
            <p>Best regards,<br>{{ config('app.name') }} Recruitment Team</p>
        </div>
        <div class="footer">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</div>
    </div>
</body>
</html>
