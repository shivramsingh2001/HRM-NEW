<!DOCTYPE html>
<html>
<head>
    <title>Interview Rescheduled</title>
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
        <div class="header"><h2>Interview Rescheduled</h2></div>
        <div class="content">
            <p>Dear {{ $candidate->first_name }},</p>
            <p>Your interview for <strong>{{ $jobOpening->title }}</strong> has been rescheduled.</p>
            <div class="info-box">
                <p><strong>New Date:</strong> {{ \Carbon\Carbon::parse($newInterview->scheduled_date)->format('l, d M Y') }}</p>
                <p><strong>New Time:</strong> {{ \Carbon\Carbon::parse($newInterview->scheduled_time)->format('h:i A') }}</p>
                <p><strong>Round:</strong> {{ $newInterview->round_name }}</p>
                @if($newInterview->meeting_link)
                    <p><strong>Meeting Link:</strong> <a href="{{ $newInterview->meeting_link }}">{{ $newInterview->meeting_link }}</a></p>
                @endif
                @if($newInterview->location)
                    <p><strong>Location:</strong> {{ $newInterview->location }}</p>
                @endif
            </div>
            @if($reason)
                <p><strong>Reason:</strong> {{ $reason }}</p>
            @endif
            <p>Best regards,<br>{{ config('app.name') }} Recruitment Team</p>
        </div>
        <div class="footer">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</div>
    </div>
</body>
</html>
