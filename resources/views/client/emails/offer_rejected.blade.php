<!DOCTYPE html>
<html>
<head>
    <title>Alert: Offer Rejected by Candidate - {{ $company->company_name ?? config('app.name') }}</title>
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
        .alert-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .alert-box h3 {
            margin: 0 0 10px 0;
            color: #991b1b;
            font-size: 18px;
        }
        .info-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #ef4444;
        }
        .info-box p {
            margin: 8px 0;
        }
        .info-box strong {
            color: #1f2937;
        }
        .candidate-details {
            background: #fef2f2;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
        .reason-box {
            background: #fffbeb;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #f59e0b;
        }
        .action-items {
            background: #f0fdf4;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        .action-items h4 {
            margin: 0 0 15px 0;
            color: #166534;
        }
        .action-items ul {
            margin: 0;
            padding-left: 20px;
        }
        .action-items li {
            margin-bottom: 10px;
            color: #14532d;
        }
        .button {
            display: inline-block;
            background: #ef4444;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            margin: 10px 0;
            font-size: 12px;
        }
        .button:hover {
            background: #dc2626;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>⚠️ Offer Rejected - Action Required</h2>
            <p>{{ $company->company_name ?? config('app.name') }} - Recruitment Alert</p>
        </div>
        
        <div class="content">
            <div class="greeting">
                Dear HR Team,
            </div>
            
            <div class="alert-box">
                <h3>❌ Candidate has REJECTED the offer</h3>
                <p>Immediate action required for this position</p>
            </div>
            
            <div class="info-box">
                <h4 style="margin: 0 0 15px 0; color: #1f2937;">📋 Candidate Information</h4>
                <p><strong>Candidate Name:</strong> {{ $candidate->first_name }} {{ $candidate->last_name }}</p>
                <p><strong>Position Applied:</strong> {{ $jobOpening->title }} ({{ $jobOpening->job_code }})</p>
                <p><strong>Email:</strong> <a href="mailto:{{ $candidate->email }}" style="color: #ef4444;">{{ $candidate->email }}</a></p>
                <p><strong>Phone:</strong> {{ $candidate->phone }}</p>
                @if($candidate->current_company)
                <p><strong>Current Company:</strong> {{ $candidate->current_company }}</p>
                @endif
                <p><strong>Application Date:</strong> {{ $candidate->created_at->format('d M Y') }}</p>
            </div>

            @if($reason)
            <div class="reason-box">
                <p><strong>📝 Reason provided by candidate:</strong></p>
                <p style="font-style: italic; margin-top: 5px;">"{{ $reason }}"</p>
            </div>
            @endif

            <div class="action-items">
                <h4>✅ Action Required - Immediate:</h4>
                <ul>
                    <li>🔍 <strong>Review the next best candidate</strong> from the recruitment pipeline</li>
                    <li>📧 <strong>Release offer to the next candidate</strong> if available in the shortlist</li>
                    <li>📊 <strong>Update recruitment tracker</strong> with this candidate's status</li>
                    <li>📝 <strong>Document the rejection reason</strong> for process improvement analysis</li>
                    <li>🔄 <strong>Re-evaluate the offer package</strong> if multiple candidates reject</li>
                    <li>📞 <strong>Consider scheduling a feedback call</strong> with the candidate (optional)</li>
                </ul>
            </div>

            <hr>

            <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin: 15px 0;">
                <p><strong>📌 Quick Stats for this position:</strong></p>
                <p>• Total Applications: {{ $jobOpening->applications()->count() }}</p>
                <p>• Shortlisted Candidates: {{ $jobOpening->applications()->where('current_stage', 'cv_shortlisted')->count() }}</p>
                <p>• Offers Released: {{ $jobOpening->applications()->whereIn('current_stage', ['offer_released', 'offer_accepted'])->count() }}</p>
                <p>• Vacancies Left: {{ $jobOpening->no_of_vacancies - $jobOpening->applications()->where('current_stage', 'onboarded')->count() }}</p>
            </div>

            <div style="text-align: center; margin-top: 20px;">
                <a href="{{ route('job-openings.show', $jobOpening->id) }}" class="button">
                    📋 View Job & Candidates
                </a>
                <a href="{{ route('job-openings.applications', $jobOpening->id) }}" class="button" style="background: #3b82f6;">
                    👥 View All Applications
                </a>
            </div>

            <p style="margin-top: 20px;">Please take appropriate action at the earliest to fill this position.</p>
            
            <p>Best regards,<br>
            <strong>Recruitment System</strong><br>
            {{ $company->company_name }}</p>
        </div>
        
        <div class="company-footer">
            <p><strong>{{ $company->company_name }}</strong></p>
            <p>{{ $company->address ?? 'Corporate Office' }}</p>
            <p>
                📞 {{ $company->phone ?? '+91 0000000000' }} | 
                ✉️ {{ $company->email ?? 'hr@company.com' }}
            </p>
            <p style="margin-top: 15px;">&copy; {{ date('Y') }} {{ $company->company_name }}. All rights reserved.</p>
            <p style="font-size: 10px; margin-top: 10px;">
                This is an automated system notification. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>