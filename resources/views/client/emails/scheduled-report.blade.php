<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $reportLabel }} - {{ $companyName }}</title>
</head>
<body style="margin:0;padding:24px 12px;background:#EFF6FF;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#0f172a;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:10px;">
        <tr>
            <td style="background:#0D6EFD;color:#ffffff;padding:18px 24px;border-radius:10px 10px 0 0;">
                <div style="font-size:12px;opacity:.85;">{{ $companyName }}</div>
                <div style="font-size:18px;font-weight:600;margin-top:2px;">{{ $reportLabel }}</div>
            </td>
        </tr>
        <tr>
            <td style="padding:22px 24px;font-size:14px;line-height:1.6;">
                @if ($customMessage)
                    <p style="margin:0 0 16px;white-space:pre-line;">{{ $customMessage }}</p>
                @else
                    <p style="margin:0 0 16px;">Please find the scheduled report below.</p>
                @endif

                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="font-size:13px;border:1px solid #e2e8f0;border-radius:8px;">
                    <tr>
                        <td style="padding:8px 12px;color:#64748b;width:120px;">Report</td>
                        <td style="padding:8px 12px;font-weight:600;">{{ $reportLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 12px;color:#64748b;border-top:1px solid #e2e8f0;">Period</td>
                        <td style="padding:8px 12px;border-top:1px solid #e2e8f0;">{{ $periodLabel }}</td>
                    </tr>
                    <tr>
                        <td style="padding:8px 12px;color:#64748b;border-top:1px solid #e2e8f0;">File</td>
                        <td style="padding:8px 12px;border-top:1px solid #e2e8f0;">
                            {{ $fileName }} ({{ number_format(max($fileSize, 1) / 1024, 1) }} KB)
                        </td>
                    </tr>
                </table>

                @if ($fileContent === null)
                    <p style="margin:16px 0 0;padding:10px 12px;background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;color:#9A3412;font-size:13px;">
                        The file is too large to attach to an email. Please download it from Reports in the HRMS.
                    </p>
                @else
                    <p style="margin:16px 0 0;font-size:13px;color:#64748b;">The report is attached to this email.</p>
                @endif
            </td>
        </tr>
        <tr>
            <td style="padding:14px 24px;border-top:1px solid #e2e8f0;font-size:11.5px;color:#64748b;">
                Sent automatically by the "{{ $scheduleName }}" schedule on {{ now()->format('d M Y, h:i A') }}.
                To stop these emails, ask your HR / admin team to pause the schedule.
            </td>
        </tr>
    </table>
</body>
</html>
