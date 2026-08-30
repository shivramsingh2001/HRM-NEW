<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - HRM</title>
    <style>
        /* Base Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f7f9fc;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
        }
        
        .logo {
            max-width: 180px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        /* Content */
        .content {
            padding: 40px 30px;
        }
        
        .greeting {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 25px;
        }
        
        .message {
            color: #4a5568;
            font-size: 16px;
            margin-bottom: 30px;
        }
        
        /* Button */
        .reset-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            text-align: center;
            margin: 25px 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .reset-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }
        
        /* Link Box */
        .link-box {
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin: 25px 0;
            word-break: break-all;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
            color: #4a5568;
        }
        
        /* Warning */
        .warning {
            background-color: #fffaf0;
            border-left: 4px solid #ed8936;
            padding: 15px;
            margin: 25px 0;
            border-radius: 4px;
        }
        
        .warning-title {
            color: #c05621;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        /* Footer */
        .footer {
            background-color: #f7f9fc;
            padding: 25px 30px;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .expiry-notice {
            background-color: #fed7d7;
            color: #9b2c2c;
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .company-info {
            color: #718096;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .social-links {
            margin-top: 20px;
        }
        
        .social-icon {
            display: inline-block;
            margin: 0 10px;
            color: #667eea;
            text-decoration: none;
        }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .header {
                padding: 30px 20px;
            }
            
            .content {
                padding: 30px 20px;
            }
            
            .footer {
                padding: 20px;
            }
            
            .reset-button {
                display: block;
                margin: 20px auto;
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <!-- Uncomment if you have a logo -->
            <!-- <img src="{{ asset('assets/images/logo/logo-white.png') }}" alt="{{ config('app.name') }}" class="logo"> -->
            <h1>Reset Your Password</h1>
        </div>
        
        <!-- Content -->
        <div class="content">
            <p class="greeting">Hello <strong>{{ $user->name }}</strong>,</p>
            
            <p class="message">We received a request to reset the password for your Shurt HRM account. Click the button below to choose a new password:</p>
            
            <div style="text-align: center;color:#ffffff !Important;">
                <a href="{{ $resetLink }}" class="reset-button">
                    Reset Password
                </a>
            </div>
            
            <p class="message" style="text-align: center; color: #718096;">
                Or copy and paste this link into your browser:
            </p>
            
            <div class="link-box">
                {{ $resetLink }}
            </div>
            
            <div class="warning">
                <div class="warning-title">⚠️ Important Security Notice</div>
                <p style="margin: 0; color: #744210;">
                    This password reset link will expire in <strong>5 minutes</strong> for security reasons. 
                    If you didn't request this password reset, please ignore this email or contact our support team immediately.
                </p>
            </div>
            
            <p class="message" style="color: #4a5568;">
                For security purposes, this link can only be used once. After resetting your password, you'll be able to log in with your new credentials.
            </p>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="expiry-notice">
                ⏰ Link expires in 5 minutes
            </div>
            
            <div class="company-info">
                <p style="margin: 0 0 10px 0;">
                    Need help? Contact our support team at 
                    <a href="mailto:support@shurttech.com" style="color: #667eea; text-decoration: none;">
                        support@shurttech.com
                    </a>
                </p>
                
                <p style="margin: 0 0 15px 0; color: #a0aec0;">
                    This is an automated message from HRM. Please do not reply to this email.
                </p>
                
                <div style="border-top: 1px solid #e2e8f0; padding-top: 15px; margin-top: 15px;">
                    <p style="margin: 0; color: #a0aec0; font-size: 12px;">
                        &copy; {{ date('Y') }} HRM. All rights reserved.<br>
                        Shurt Tech Solutions Pvt. Ltd.
                    </p>
                </div>
                
                <!-- Uncomment if you want social links -->
                <!-- 
                <div class="social-links">
                    <a href="#" class="social-icon">Website</a> | 
                    <a href="#" class="social-icon">LinkedIn</a> | 
                    <a href="#" class="social-icon">Twitter</a>
                </div>
                -->
            </div>
        </div>
    </div>
</body>
</html>