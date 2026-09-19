<!DOCTYPE html>
<html>
<head>
    <title>Welcome Aboard - {{ $company->company_name ?? config('app.name') }}</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f7fa; margin:0; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; }
        .header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: white; padding: 28px 20px; text-align: center; }
        .content { padding: 24px; }
        .info-box { background: #eef2ff; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #1e3a8a; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h2>Welcome Aboard, {{ $candidate->first_name }}!</h2></div>
        <div class="content">
            <p>Congratulations again on joining <strong>{{ $company->company_name ?? config('app.name') }}</strong> as
                <strong>{{ $jobOpening->title }}</strong>.</p>
            <p>Your onboarding process has now started (reference <strong>{{ $assignment->assignment_code }}</strong>).
                Our HR team will guide you through document verification and the rest of the onboarding checklist
                before your start date.</p>
            <div class="info-box">
                <p><strong>Start Date:</strong> {{ optional($assignment->start_date)->format('d M Y') }}</p>
            </div>
            <p>If you have any questions, please reach out to our HR team.</p>
            <p>Best regards,<br>{{ $company->company_name ?? config('app.name') }} HR Team</p>
        </div>
        <div class="footer">&copy; {{ date('Y') }} {{ $company->company_name ?? config('app.name') }}. All rights reserved.</div>
    </div>
</body>
</html>
