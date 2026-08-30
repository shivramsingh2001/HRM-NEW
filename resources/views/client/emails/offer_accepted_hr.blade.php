<!DOCTYPE html>
<html>
<head>
    <title>Offer Accepted - {{ $candidate->first_name }} {{ $candidate->last_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #10b981; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9fafb; }
        .info-box { background: #d1fae5; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Offer Accepted!</h2>
        </div>
        <div class="content">
            <p>Dear HR Team,</p>
            
            <div class="info-box">
                <h3>Candidate has accepted the offer!</h3>
                <p><strong>Candidate Name:</strong> {{ $candidate->first_name }} {{ $candidate->last_name }}</p>
                <p><strong>Position:</strong> {{ $jobOpening->title }}</p>
                <p><strong>Email:</strong> {{ $candidate->email }}</p>
                <p><strong>Phone:</strong> {{ $candidate->phone }}</p>
            </div>

            <p><strong>Action Required:</strong></p>
            <ul>
                <li>✅ Initiate onboarding process immediately</li>
                <li>📧 Send onboarding documentation to the candidate</li>
                <li>💻 Coordinate with IT for system access and laptop allocation</li>
                <li>📅 Schedule first-day orientation</li>
                <li>💰 Add candidate to payroll and attendance system</li>
                <li>🪪 Generate employee ID and email credentials</li>
            </ul>

            <p>Please start the onboarding workflow for this candidate.</p>
            
            <p>Best regards,<br>System Notification</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $company->name ?? config('app.name') }}</p>
        </div>
    </div>
</body>
</html>