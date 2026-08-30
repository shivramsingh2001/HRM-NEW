<!DOCTYPE html>
<html>
<head>
    <title>Application Status Update - {{ $company->company_name ?? config('app.name') }}</title>
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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
        .message-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .message-box h3 {
            margin: 0 0 10px 0;
            color: #991b1b;
            font-size: 18px;
        }
        .reason-box {
            background: #fffbeb;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #f59e0b;
        }
        .encouragement {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
        }
        .encouragement p {
            margin: 5px 0;
            color: #475569;
        }
        .button {
            display: inline-block;
            background: #4f46e5;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            margin: 10px 0;
            text-align: center;
        }
        .button:hover {
            background: #4338ca;
        }
        .company-footer {
            background: #1f2937;
            color: #9ca3af;
            padding: 25px;
            text-align: center;
            font-size: 12px;
        }
        .company-footer a {
            color: #ef4444;
            text-decoration: none;
        }
        .company-footer strong {
            color: #ffffff;
        }
        .social-links {
            margin-top: 15px;
        }
        .social-links a {
            color: #9ca3af;
            margin: 0 10px;
            text-decoration: none;
        }
        hr {
            border: none;
            border-top: 1px solid #e5e7eb;
            margin: 20px 0;
        }
        @media (max-width: 600px) {
            .container {
                margin: 10px;
            }
            .content {
                padding: 20px 15px;
            }
            .header h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📋 Application Status Update</h2>
            <p>{{ $company->company_name ?? config('app.name') }}</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $candidate->first_name }} {{ $candidate->last_name }},
            </div>
            
            <p>Thank you for your interest in the <strong>{{ $jobOpening->title }}</strong> position at <strong>{{ $company->company_name }}</strong>.</p>
            
            <div class="message-box">
                <h3>❌ Application Status: Not Selected</h3>
                <p>We regret to inform you that your application has not been selected to proceed further in the recruitment process.</p>
            </div>

            @if($reason)
            <div class="reason-box">
                <p><strong>📝 Reason for not moving forward:</strong></p>
                <p style="font-style: italic; margin-top: 5px;">{{ $reason }}</p>
            </div>
            @endif

            <div class="encouragement">
                <p><strong>💡 We encourage you to:</strong></p>
                <p>• Keep improving your skills and profile</p>
                <p>• Visit our careers page for future opportunities</p>
                <p>• Follow us on LinkedIn for job alerts</p>
                <p>• Apply again when you find a suitable role</p>
            </div>

            <p>This was a competitive process with many qualified candidates. We appreciate the time and effort you put into your application.</p>
            
            <p>We wish you the very best in your future endeavors and hope to see you apply for other opportunities at {{ $company->company_name }} in the future.</p>

            <hr>

            <p>If you have any questions or would like feedback on your application, please feel free to reach out to our HR team:</p>
            <p>
                📧 <a href="mailto:{{ $company->email ?? 'hr@company.com' }}" style="color: #ef4444;">{{ $company->email ?? 'hr@company.com' }}</a><br>
                📞 {{ $company->phone ?? '+91 0000000000' }}
            </p>
            
            <div style="text-align: center;">
                <a href="{{ url('/careers') }}" class="button">
                    🔍 View Other Openings
                </a>
            </div>
            
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
            {{-- <div class="social-links">
                <a href="#">LinkedIn</a> | 
                <a href="#">Twitter</a> | 
                <a href="#">Facebook</a>
            </div> --}}
            <p style="margin-top: 15px;">&copy; {{ date('Y') }} {{ $company->company_name }}. All rights reserved.</p>
            <p style="font-size: 10px; margin-top: 10px;">
                This is an automated email from our recruitment system. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>