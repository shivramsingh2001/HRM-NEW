<!DOCTYPE html>
<html>
<head>
    <title>Application Received - {{ $company->company_name ?? config('app.name') }}</title>
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
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
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
        .application-details {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border-left: 4px solid #4f46e5;
        }
        .application-details p {
            margin: 8px 0;
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
            color: #4f46e5;
            font-weight: bold;
        }
        .info-box {
            background: #eef2ff;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .info-box p {
            margin: 5px 0;
            color: #3730a3;
        }
        .timeline {
            margin: 20px 0;
            padding: 0;
        }
        .timeline li {
            display: flex;
            margin-bottom: 15px;
            align-items: flex-start;
        }
        .timeline-icon {
            width: 30px;
            height: 30px;
            background: #e0e7ff;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: var(--icon-color, #0D6EFD);
            font-weight: bold;
        }
        .timeline-content {
            flex: 1;
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
            color: #4f46e5;
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
            .timeline li {
                flex-direction: column;
            }
            .timeline-icon {
                margin-bottom: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>📋 Application Received!</h2>
            <p>{{ $company->company_name ?? config('app.name') }}</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $candidateName }},
            </div>
            
            <p>Thank you for applying for the position of <strong>{{ $jobTitle }}</strong> at <strong>{{ $company->company_name }}</strong>.</p>
            
            <div class="application-details">
                <p><strong>📝 Application Details:</strong></p>
                <p><strong>Position:</strong> {{ $jobTitle }}</p>
                <p><strong>Job Code:</strong> {{ $jobCode }}</p>
                <p><strong>Application Date:</strong> {{ $applicationDate }}</p>
                <p><strong>Application ID:</strong> #{{ $jobCode }}-{{ date('Ymd') }}</p>
            </div>

            <div class="info-box">
                <p><strong>✅ What happens next?</strong></p>
                <p>Our recruitment team will review your application and get back to you within 5-7 business days.</p>
            </div>

            <div class="timeline">
                <p><strong>📌 Recruitment Process Timeline:</strong></p>
                <ul style="list-style: none; padding-left: 0;">
                    <li>
                        <span class="timeline-icon">1</span>
                        <div class="timeline-content">
                            <strong>Application Review</strong>
                            <span style="display: block; font-size: 12px; color: #6b7280;">1-2 business days</span>
                        </div>
                    </li>
                    <li>
                        <span class="timeline-icon">2</span>
                        <div class="timeline-content">
                            <strong>HR Screening Call</strong>
                            <span style="display: block; font-size: 12px; color: #6b7280;">If shortlisted, within 3 days</span>
                        </div>
                    </li>
                    <li>
                        <span class="timeline-icon">3</span>
                        <div class="timeline-content">
                            <strong>Technical/Managerial Interview</strong>
                            <span style="display: block; font-size: 12px; color: #6b7280;">Scheduled after screening</span>
                        </div>
                    </li>
                    <li>
                        <span class="timeline-icon">4</span>
                        <div class="timeline-content">
                            <strong>Offer Decision</strong>
                            <span style="display: block; font-size: 12px; color: #6b7280;">Within 2-3 days after final round</span>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="next-steps">
                <p><strong>💡 Tips for a successful application:</strong></p>
                <li>Keep your resume updated and ready</li>
                <li>Check your email (including spam folder) regularly</li>
                <li>Prepare for potential interview questions about your experience</li>
                <li>Research about {{ $company->company_name }} and our work culture</li>
            </div>

            <hr>

            <p>If you have any questions about your application status, please feel free to contact our HR team:</p>
            <p>
                📧 <a href="mailto:{{ $company->email ?? 'hr@company.com' }}" style="color: #4f46e5;">{{ $company->email ?? 'hr@company.com' }}</a><br>
                📞 {{ $company->phone ?? '+91 0000000000' }}
            </p>
            
            <div style="text-align: center;">
                <a href="{{ url('/careers/' . ($company->subdomain ?? '')) }}" class="button">
                    🔍 View Other Opportunities
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