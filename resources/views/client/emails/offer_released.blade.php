<!DOCTYPE html>
<html>
<head>
    <title>Job Offer Letter - {{ $company->company_name ?? config('app.name') }}</title>
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
        .greeting {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 20px;
        }
        .offer-box {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            text-align: center;
            border: 1px solid #10b981;
        }
        .offer-box h3 {
            margin: 0 0 15px 0;
            color: #065f46;
        }
        .offer-details {
            text-align: left;
            margin-top: 10px;
        }
        .offer-details p {
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px dashed #a7f3d0;
        }
        .highlight {
            font-size: 20px;
            font-weight: 700;
            color: #065f46;
        }
        .button-group {
            text-align: center;
            margin: 25px 0;
        }
        .btn-accept {
            display: inline-block;
            background: #10b981;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            margin: 0 10px;
            transition: background 0.3s;
        }
        .btn-accept:hover {
            background: #059669;
        }
        .btn-decline {
            display: inline-block;
            background: #ef4444;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            margin: 0 10px;
            transition: background 0.3s;
        }
        .btn-decline:hover {
            background: #dc2626;
        }
        .info-box {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #10b981;
        }
        .info-box p {
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
            color: #10b981;
            font-weight: bold;
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
            .btn-accept, .btn-decline {
                display: block;
                margin: 10px 0;
                text-align: center;
            }
            .offer-box h3 {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🎉 Job Offer Letter</h2>
            <p>{{ $company->company_name ?? config('app.name') }}</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear {{ $candidate->first_name }} {{ $candidate->last_name }},
            </div>
            
            <p>We are delighted to offer you the position of <strong>{{ $jobOpening->title }}</strong> at <strong>{{ $company->company_name }}</strong>.</p>
            
            <div class="offer-box">
                <h3>📄 Offer Details</h3>
                <div class="offer-details">
                    <p><strong>Position:</strong> {{ $jobOpening->title }}</p>
                    <p><strong>Department:</strong> {{ $jobOpening->department->name ?? 'N/A' }}</p>
                    <p><strong>Offer Code:</strong> <code>{{ $offer->offer_code }}</code></p>
                    <p><strong>Employment Type:</strong> {{ ucfirst(str_replace('_', ' ', $offer->employment_type ?? $jobOpening->employment_type)) }}</p>
                    <p><strong>Offered CTC:</strong> <span class="highlight">₹{{ number_format($offer->offered_ctc) }}</span> per annum</p>
                    <p><strong>Joining Date:</strong> {{ \Carbon\Carbon::parse($joiningDate)->format('l, d M Y') }}</p>
                    @if($offer->basic_salary)
                    <p><strong>Basic Salary:</strong> ₹{{ number_format($offer->basic_salary) }}/month</p>
                    @endif
                    @if($offer->hra)
                    <p><strong>HRA:</strong> ₹{{ number_format($offer->hra) }}/month</p>
                    @endif
                </div>
            </div>

            <div class="info-box">
                <p><strong>📌 Important Information:</strong></p>
                <p>• Please review the attached detailed offer letter for complete terms and conditions.</p>
                <p>• The offer is valid for <strong>7 days</strong> from the date of issue.</p>
                <p>• Your employment will be subject to successful background verification.</p>
                <p>• Please submit the required documents within the stipulated time.</p>
            </div>


            <div class="next-steps">
                <p><strong>📌 Next Steps after acceptance:</strong></p>
                <li>Complete the online onboarding form</li>
                <li>Submit your educational and experience documents</li>
                <li>Complete background verification form</li>
                <li>Attend the pre-joining orientation</li>
                <li>Prepare for your first day on {{ \Carbon\Carbon::parse($joiningDate)->format('l, d M Y') }}</li>
            </div>

            <hr>

            <p>We are excited about the possibility of you joining our team and believe you will make a valuable contribution to our organization.</p>
            
            <p>Should you have any questions, please feel free to contact our HR team:</p>
            <p>
                📧 <a href="mailto:{{ $company->email ?? 'hr@company.com' }}" style="color: #10b981;">{{ $company->email ?? 'hr@company.com' }}</a><br>
                📞 {{ $company->phone ?? '+91 0000000000' }}
            </p>
            
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