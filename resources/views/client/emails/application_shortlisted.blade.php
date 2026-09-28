<!DOCTYPE html>
<html>
<head>
    <title>Application Shortlisted - {{ $company->company_name ?? config('app.name') }}</title>
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
        .company-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .company-logo img {
            max-width: 150px;
            max-height: 60px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
        }
        .highlight-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .job-details {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .job-details p {
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
            .header h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🎉 Congratulations!</h2>
            <p>Your application has been shortlisted</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $candidate->first_name }} {{ $candidate->last_name }},
            </div>
            
            <p>We are pleased to inform you that your application for the position of <strong>{{ $jobOpening->title }}</strong> at <strong>{{ $company->company_name }}</strong> has been <strong>shortlisted</strong>!</p>
            
            <div class="highlight-box">
                <p><strong>📋 Position Details:</strong></p>
                <p><strong>Job Title:</strong> {{ $jobOpening->title }}</p>
                <p><strong>Job Code:</strong> {{ $jobOpening->job_code }}</p>
                <p><strong>Department:</strong> {{ $jobOpening->department->name ?? 'N/A' }}</p>
                @if($jobOpening->location)
                    <p><strong>Location:</strong> {{ $jobOpening->location }}</p>
                @endif
            </div>
            
            @if($remarks)
                <div class="job-details">
                    <p><strong>📝 Remarks from HR Team:</strong></p>
                    <p>{{ $remarks }}</p>
                </div>
            @endif
            
            <div class="next-steps">
                <p><strong>📌 Next Steps:</strong></p>
                <li>Our recruitment team will contact you within 2-3 business days</li>
                <li>You will be scheduled for an interview</li>
                <li>Please keep your documents ready for verification</li>
                <li>Check your email regularly for further updates</li>
            </div>
            
            <p>If you have any questions, please feel free to reach out to our HR team.</p>
            
            <!--<div style="text-align: center;">-->
            <!--    <a href="{{ $company->subdomain."/shurttech.com/careers"  ?? url('/') }}/careers" class="button">View Job Openings</a>-->
            <!--</div>-->
        </div>
        
        <div class="company-footer">
            <div class="company-logo">
                @if($company->logo)
                    <img src="{{ file_url($company->logo, 'tenant_logo') }}" alt="{{ $company->company_name }}" style="max-width: 120px;">
                @endif
            </div>
            <p><strong>{{ $company->company_name }}</strong></p>
            <p>{{ $company->address ?? 'Corporate Office' }}</p>
            <p>
                📞 {{ $company->phone ?? '+91 0000000000' }} | 
                ✉️ {{ $company->email ?? 'hr@company.com' }}
            </p>
            {{-- <div class="social-links">
                <a href="#">LinkedIn</a> | 
                <a href="#">Twitter</a> | 
                <a href="#">Facebook</a>
            </div> --}}
            <p style="margin-top: 15px;">&copy; {{ date('Y') }} {{ $company->company_name }}. All rights reserved.</p>
            <p style="font-size: 10px; margin-top: 10px;">
                This is an automated message from our recruitment system. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>