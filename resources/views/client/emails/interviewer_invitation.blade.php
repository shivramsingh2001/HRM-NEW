<!DOCTYPE html>
<html>
<head>
    <title>Interview Invitation - You are scheduled as interviewer</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #8b5cf6; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9fafb; }
        .info-box { background: #f3e8ff; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Interview Scheduled - Action Required</h2>
        </div>
        <div class="content">
            <p>Dear {{ $interviewer->name }},</p>
            <p>You have been scheduled as an interviewer for the following interview:</p>
            
            <div class="info-box">
                <h3>Interview Details:</h3>
                <p><strong>Candidate:</strong> {{ $candidate->first_name }} {{ $candidate->last_name }}</p>
                <p><strong>Position:</strong> {{ $jobOpening->title }}</p>
                <p><strong>Round:</strong> {{ $interview->interview_round }} - {{ $interview->round_name }}</p>
                <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($interview->scheduled_date)->format('l, d M Y') }}</p>
                <p><strong>Time:</strong> {{ \Carbon\Carbon::parse($interview->scheduled_time)->format('h:i A') }}</p>
                <p><strong>Duration:</strong> {{ $interview->duration_minutes }} minutes</p>
                <p><strong>Type:</strong> {{ ucfirst($interview->interview_type) }}</p>
                @if($interview->meeting_link)
                    <p><strong>Meeting Link:</strong> <a href="{{ $interview->meeting_link }}">{{ $interview->meeting_link }}</a></p>
                @endif
                @if($interview->location)
                    <p><strong>Location:</strong> {{ $interview->location }}</p>
                @endif
            </div>

            <p>Please be available at the scheduled time. You will need to submit your feedback after the interview.</p>
            <br>
            <p>Best regards,<br>{{ config('app.name') }} HR Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>