<!DOCTYPE html>
<html>
<head>
    <title>Offer Accepted - Welcome to {{ $company->company_name ?? config('app.name') }}!</title>
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h2 {
            margin: 0;
            font-size: 28px;
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
        .welcome-box {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid #10b981;
        }
        .welcome-box h3 {
            margin: 0 0 10px 0;
            color: #065f46;
            font-size: 20px;
        }
        .welcome-box p {
            margin: 5px 0;
            color: #065f46;
        }
        .company-details {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }
        .company-details p {
            margin: 5px 0;
        }
        .next-steps {
            background: #fef3c7;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border-left: 4px solid #f59e0b;
        }
        .next-steps h3 {
            margin: 0 0 15px 0;
            color: #92400e;
            font-size: 16px;
        }
        .next-steps ul {
            margin: 0;
            padding-left: 20px;
        }
        .next-steps li {
            margin-bottom: 10px;
            color: #78350f;
        }
        .info-box {
            background: #e0e7ff;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .info-box p {
            margin: 5px 0;
            color: #3730a3;
        }
        .button {
            display: inline-block;
            background: #10b981;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            margin: 10px 0;
            text-align: center;
        }
        .button:hover {
            background: #059669;
        }
        .company-footer {
            background: #1f2937;
            color: #9ca3af;
            padding: 25px;
            text-align: center;
            font-size: 12px;
        }
        .company-footer a {
            color: #10b981;
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
                font-size: 24px;
            }
            .welcome-box h3 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🎉 Welcome to the Team!</h2>
            <p>{{ $company->company_name ?? config('app.name') }}</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $candidate->first_name }} {{ $candidate->last_name }},
            </div>
            
            <div class="welcome-box">
                <h3>Offer Accepted! 🎊</h3>
                <p>We are thrilled to welcome you to <strong>{{ $company->company_name }}</strong> as <strong>{{ $jobOpening->title }}</strong>.</p>
            </div>
            
            <p>Thank you for accepting our offer. We are excited to have you on board and look forward to your contributions to our team.</p>
            
            <div class="company-details">
                <p><strong>🏢 About {{ $company->company_name }}:</strong></p>
                <p>{{ $company->description ?? 'A leading organization committed to excellence and innovation.' }}</p>
                <p>📍 {{ $company->address ?? 'Corporate Office' }}</p>
                <p>🌐 {{ $company->website ?? config('app.url') }}</p>
            </div>

            <div class="next-steps">
                <h3>📋 Next Steps - What to Expect:</h3>
                <ul>
                    <li><strong>Step 1:</strong> You will receive onboarding details within 2-3 business days</li>
                    <li><strong>Step 2:</strong> Complete the pre-joining documentation and forms</li>
                    <li><strong>Step 3:</strong> Submit your educational and experience certificates for verification</li>
                    <li><strong>Step 4:</strong> Complete the background verification process</li>
                    <li><strong>Step 5:</strong> Attend the virtual pre-joining orientation session</li>
                    <li><strong>Your Joining Date:</strong> <strong>{{ \Carbon\Carbon::parse($joiningDate)->format('l, d M Y') }}</strong></li>
                    <li><strong>Reporting Time:</strong> 9:30 AM IST</li>
                    <li><strong>Reporting Location:</strong> {{ $company->address ?? 'Corporate Office' }}</li>
                </ul>
            </div>

            <div class="info-box">
                <p><strong>📌 Important Notes:</strong></p>
                <p>• Please bring original documents for verification on your joining day</p>
                <p>• You will receive a welcome kit and laptop on your first day</p>
                <p>• A company representative will guide you through the onboarding process</p>
                <p>• First week will include orientation and training sessions</p>
            </div>

            <p>Our HR team will contact you shortly with detailed onboarding instructions and document requirements.</p>
            
            <!--<div style="text-align: center;">-->
            <!--    <a href="{{ $company->website ?? url('/') }}/onboarding" class="button">-->
            <!--        📝 Access Onboarding Portal-->
            <!--    </a>-->
            <!--</div>-->

            <hr>
            
            <p>If you have any questions before your joining date, please feel free to reach out to our HR team:</p>
            <p>
                📧 <a href="mailto:{{ $company->email ?? 'hr@company.com' }}" style="color: #10b981;">{{ $company->email ?? 'hr@company.com' }}</a><br>
                📞 {{ $company->phone ?? '+91 0000000000' }}
            </p>
            
            <p>Once again, congratulations and welcome aboard!</p>
            
            <p>Warm regards,<br>
            <strong>{{ $company->company_name }} HR Team</strong></p>
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