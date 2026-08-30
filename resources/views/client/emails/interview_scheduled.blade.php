<!DOCTYPE html>
<html>
<head>
    <title>Interview Scheduled - {{ $company->company_name ?? config('app.name') }}</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .content {
            padding: 30px 25px;
            background: #ffffff;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
        }
        .info-box {
            background: #eef2ff;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border-left: 4px solid #3b82f6;
        }
        .info-box h3 {
            margin: 0 0 15px 0;
            color: #1e40af;
            font-size: 16px;
        }
        .info-row {
            display: flex;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px dashed #c7d2fe;
        }
        .info-label {
            width: 130px;
            font-weight: 600;
            color: #4b5563;
        }
        .info-value {
            flex: 1;
            color: #1f2937;
        }
        .important-note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .important-note p {
            margin: 0;
            font-size: 13px;
        }
        .calendar-btn {
            display: inline-block;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            margin: 15px 0;
            text-align: center;
        }
        .calendar-btn:hover {
            background: #2563eb;
        }
        .company-footer {
            background: #1f2937;
            color: #9ca3af;
            padding: 25px;
            text-align: center;
            font-size: 12px;
        }
        .company-footer a {
            color: #3b82f6;
            text-decoration: none;
        }
        .social-links {
            margin-top: 15px;
        }
        .social-links a {
            color: #9ca3af;
            margin: 0 10px;
            text-decoration: none;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
            }
            .content {
                padding: 20px 15px;
            }
            .info-row {
                flex-direction: column;
            }
            .info-label {
                width: 100%;
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📅 Interview Scheduled</h2>
            <p>{{ $company->company_name ?? config('app.name') }} - Recruitment Process</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $candidate->first_name }} {{ $candidate->last_name }},
            </div>
            
            <p>We are pleased to inform you that your interview for the position of <strong>{{ $jobOpening->title }}</strong> at <strong>{{ $company->company_name }}</strong> has been scheduled.</p>
            
            <div class="info-box">
                <h3>📋 Interview Details</h3>
                <div class="info-row">
                    <div class="info-label">Round:</div>
                    <div class="info-value"><strong>Round {{ $interview->interview_round }}</strong> - {{ $interview->round_name }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Date:</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($interview->scheduled_date)->format('l, d M Y') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Time:</div>
                    <div class="info-value">{{ \Carbon\Carbon::parse($interview->scheduled_time)->format('h:i A') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Duration:</div>
                    <div class="info-value">{{ $interview->duration_minutes }} minutes</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Interview Type:</div>
                    <div class="info-value">
                        @if($interview->interview_type == 'online')
                            🖥️ Online (Video Call)
                        @elseif($interview->interview_type == 'offline')
                            🏢 Offline (In-Person)
                        @elseif($interview->interview_type == 'telephonic')
                            📞 Telephonic
                        @else
                            🎥 Video Call
                        @endif
                    </div>
                </div>
                @if($interview->meeting_link)
                <div class="info-row">
                    <div class="info-label">Meeting Link:</div>
                    <div class="info-value">
                        <a href="{{ $interview->meeting_link }}" target="_blank" style="color: #3b82f6;">{{ $interview->meeting_link }}</a>
                    </div>
                </div>
                @endif
                @if($interview->location)
                <div class="info-row">
                    <div class="info-label">Venue/Location:</div>
                    <div class="info-value">{{ $interview->location }}</div>
                </div>
                @endif
                @if($interview->instructions)
                <div class="info-row">
                    <div class="info-label">Instructions:</div>
                    <div class="info-value">{{ $interview->instructions }}</div>
                </div>
                @endif
            </div>

            @if($interview->meeting_link)
            <div style="text-align: center;">
                <a href="{{ $interview->meeting_link }}" class="calendar-btn" target="_blank">
                    🚀 Join Interview
                </a>
            </div>
            @endif

            <div class="important-note">
                <p><strong>⚠️ Important Notes:</strong></p>
                <p>• Please join 5-10 minutes before the scheduled time.</p>
                <p>• Ensure you have a stable internet connection (for online interviews).</p>
                <p>• Keep your updated resume and documents ready.</p>
                <p>• Test your camera and microphone before the interview (if applicable).</p>
                <p>• Dress professionally for the interview.</p>
            </div>

            <p>If you need to reschedule or have any questions, please contact our HR team at <strong>{{ $company->email ?? 'hr@company.com' }}</strong> or call <strong>{{ $company->phone ?? '+91 0000000000' }}</strong>.</p>
            
            <p>We look forward to speaking with you!</p>
            
            <p>Best regards,<br>
            <strong>{{ $company->company_name }} Recruitment Team</strong></p>
        </div>
        
        <div class="company-footer">
            <p><strong>{{ $company->company_name }}</strong></p>
            <p>{{ $company->address ?? 'Corporate Office' }}</p>
            <p>
                📞 {{ $company->phone ?? '+91 0000000000' }} | 
                ✉️ {{ $company->email ?? 'hr@company.com' }}
            </p>
            @if($company->website)
            <p>🌐 <a href="{{ $company->website }}">{{ $company->website }}</a></p>
            @endif
            <!--<div class="social-links">-->
            <!--    <a href="#">LinkedIn</a> | -->
            <!--    <a href="#">Twitter</a> | -->
            <!--    <a href="#">Facebook</a>-->
            <!--</div>-->
            <p style="margin-top: 15px;">&copy; {{ date('Y') }} {{ $company->company_name }}. All rights reserved.</p>
            <p style="font-size: 10px; margin-top: 10px;">
                This is an automated email from our recruitment system. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>