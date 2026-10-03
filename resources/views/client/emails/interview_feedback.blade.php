<!DOCTYPE html>
<html>
<head>
    <title>Interview Feedback & Decision - {{ $company->company_name ?? config('app.name') }}</title>
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
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
        .decision-box {
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
        }
        .decision-selected {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #10b981;
        }
        .decision-next_round {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #0D6EFD;
            border: 1px solid #3b82f6;
        }
        .decision-rejected {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #ef4444;
        }
        .decision-box h3 {
            margin: 0 0 5px 0;
            font-size: 20px;
        }
        .decision-box p {
            margin: 5px 0 0;
            font-size: 14px;
        }
        .interview-details {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 3px solid #f59e0b;
        }
        .interview-details h4 {
            margin: 0 0 10px 0;
            color: #d97706;
        }
        .interview-details p {
            margin: 8px 0;
        }
        .feedback-box {
            background: #fefce8;
            border-left: 4px solid #eab308;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .feedback-box p {
            margin: 5px 0;
        }
        .next-steps {
            margin: 25px 0;
            padding: 0;
            list-style: none;
        }
        .next-steps li {
            margin-bottom: 12px;
            padding-left: 24px;
            position: relative;
        }
        .next-steps li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
        .button {
            display: inline-block;
            background: #f59e0b;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            margin: 10px 0;
            text-align: center;
        }
        .button:hover {
            background: #d97706;
        }
        .company-footer {
            background: #1f2937;
            color: #9ca3af;
            padding: 25px;
            text-align: center;
            font-size: 12px;
        }
        .company-footer a {
            color: #f59e0b;
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
            .decision-box h3 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📋 Interview Feedback & Decision</h2>
            <p>{{ $company->company_name ?? config('app.name') }}</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $candidate->first_name }} {{ $candidate->last_name }},
            </div>
            
            <p>Thank you for attending the interview for the position of <strong>{{ $jobOpening->title }}</strong> at <strong>{{ $company->company_name }}</strong>.</p>
            
            <!-- Interview Details -->
            <div class="interview-details">
                <h4>📅 Interview Summary</h4>
                <p><strong>Round:</strong> {{ $interview->interview_round }} - {{ $interview->round_name }}</p>
                <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($interview->scheduled_date)->format('l, d M Y') }}</p>
                <p><strong>Time:</strong> {{ \Carbon\Carbon::parse($interview->scheduled_time)->format('h:i A') }}</p>
                <p><strong>Interview Type:</strong> {{ ucfirst($interview->interview_type) }}</p>
            </div>
            
            <!-- Decision Box -->
            @if($decision == 'selected')
                <div class="decision-box decision-selected">
                    <h3>✅ Congratulations!</h3>
                    <p>You have been SELECTED for this position!</p>
                </div>
                <p>We are pleased to inform you that you have successfully cleared all interview rounds. Your skills and experience align perfectly with our requirements.</p>
                
                <div class="next-steps">
                    <p><strong>📌 Next Steps:</strong></p>
                    <li>Our HR team will contact you within 2-3 business days</li>
                    <li>You will receive the offer letter via email</li>
                    <li>Complete the pre-joining documentation</li>
                    <li>Prepare for the onboarding process</li>
                </div>
                
            @elseif($decision == 'next_round')
                <div class="decision-box decision-next_round">
                    <h3>🔄 Moving to Next Round</h3>
                    <p>You have been shortlisted for the next round of interviews!</p>
                </div>
                <p>Great news! You have impressed the interview panel and we would like to proceed with the next round of interviews.</p>
                
                <div class="next-steps">
                    <p><strong>📌 What happens next:</strong></p>
                    <li>Our recruitment team will contact you shortly</li>
                    <li>You will receive the schedule for the next round</li>
                    <li>Prepare for the upcoming interview</li>
                    <li>Keep your documents ready for verification</li>
                </div>
                
            @elseif($decision == 'rejected')
                <div class="decision-box decision-rejected">
                    <h3>❌ Application Status Update</h3>
                    <p>Update on your application</p>
                </div>
                <p>After careful evaluation of your interview performance and qualifications against the role requirements, we regret to inform you that you have not been selected to proceed further in the recruitment process.</p>
                <p>This was a competitive process with many qualified candidates, and we encourage you to apply for future opportunities that match your profile.</p>
            @endif

            <!-- Interviewer Comments -->
            @if($comments)
                <div class="feedback-box">
                    <p><strong>📝 Interviewer's Feedback:</strong></p>
                    <p>{{ $comments }}</p>
                </div>
            @endif

            <hr>

            <p>We appreciate your interest in joining <strong>{{ $company->company_name }}</strong> and wish you the best in your future endeavors.</p>
            
            <p>If you have any questions, please feel free to contact our HR team:</p>
            <p>
                📧 <a href="mailto:{{ $company->email ?? 'hr@company.com' }}" style="color: #f59e0b;">{{ $company->email ?? 'hr@company.com' }}</a><br>
                📞 {{ $company->phone ?? '+91 0000000000' }}
            </p>
            
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
            {{-- @endif
            <div class="social-links">
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