<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="shurt_techsol">
    <!--! The above 6 meta tags *must* come first in the head; any other head content must come *after* these tags !-->
    <!--! BEGIN: Apps Title-->
    <title>Forgot Password</title>
    <!--! END:  Apps Title-->
    <!--! BEGIN: Favicon-->
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/logo/shurt_logo_black.png') }}">
    <!--! END: Favicon-->
    <!--! BEGIN: Bootstrap CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <!--! END: Bootstrap CSS-->
    <!--! BEGIN: Vendors CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
    <!--! END: Vendors CSS-->
    <!--! BEGIN: Custom CSS-->
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/theme.min.css') }}">
    <!--! END: Custom CSS-->
    <!--! HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries !-->
    <!--! WARNING: Respond.js doesn"t work if you view the page via file: !-->
    <!--[if lt IE 9]>
   <script src="https:oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
   <script src="https:oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->
    <style>
        .alert {
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: none;
        }
        .alert-success {
            background-color: #d1e7dd;
            border-color: #badbcc;
            color: #0f5132;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
            border-width: 0.2em;
        }
        #btnSpinner {
            display: none;
        }
    </style>
</head>

<body>
    <!--! ================================================================ !-->
    <!--! [Start] Main Content !-->
    <!--! ================================================================ !-->
    <main class="auth-cover-wrapper">
        <div class="auth-cover-content-inner">
            <div class="auth-cover-content-wrapper">
                <div class="auth-img">
                    <img src="{{ asset('assets/images/auth/login.jpg') }}" alt="HRM" class="img-fluid">
                </div>
            </div>
        </div>
        <div class="auth-cover-sidebar-inner">
            <div class="auth-cover-card-wrapper">
                <div class="auth-cover-card p-sm-5 ">
                    <div class="wd-100 mb-5 text-center mx-auto">
                        <img src="{{ asset('assets/images/logo/shurt_logo_black.png') }}" alt="Shurt Tech"
                            class="img-fluid">
                    </div>
                    
                    <!-- Alert Message -->
                    <div id="message" class="alert" role="alert"></div>
                    
                    <h2 class="fs-20 fw-bolder mb-4">Forgot Password ?</h2>
                    <h4 class="fs-13 fw-bold mb-2">No worries !</h4>
                    <p class="fs-12 fw-medium text-muted">Enter your registered email address and we'll send you a password reset link.</p>
                    
                    <form id="forgotPasswordForm" class="w-100 mt-4 pt-2">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fs-12 fw-bold text-muted mb-2">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="Enter your registered email" required>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-lg btn-primary w-100" id="submitBtn">
                                <span id="btnText">Send Reset Link</span>
                                <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                        
                        <div class="d-flex align-items-center justify-content-between mt-3">
                            <div></div>
                            <div>
                                <a href="{{ route('login') }}" class="fs-11 text-primary">Back to Login</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    <!--! ================================================================ !-->
    <!--! [End] Main Content !-->
    <!--! ================================================================ !-->

    <!--! ================================================================ !-->
    <!--! Footer Script !-->
    <!--! ================================================================ !-->
    <!--! BEGIN: Vendors JS !-->
    <script src="{{ asset('assets/vendors/js/vendors.min.js') }}"></script>
    <!-- vendors.min.js {always must need to be top} -->
    <!--! END: Vendors JS !-->
    <!--! BEGIN: Apps Init  !-->
    <script src="{{ asset('assets/js/common-init.min.js') }}"></script>
    <!--! END: Apps Init !-->
    <!--! BEGIN: Theme Customizer  !-->
    <script src="{{ asset('assets/js/theme-customizer-init.min.js') }}"></script>
    <!--! END: Theme Customizer !-->
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotPasswordForm');
            const messageDiv = document.getElementById('message');
            const submitBtn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const email = document.getElementById('email').value.trim();
                
                // Clear previous messages
                hideMessage();
                
                // Basic email validation
                if (!email) {
                    showMessage('Please enter your email address', 'danger');
                    return;
                }
                
                if (!validateEmail(email)) {
                    showMessage('Please enter a valid email address', 'danger');
                    return;
                }
                
                // Show loading state
                submitBtn.disabled = true;
                btnText.textContent = 'Sending...';
                btnSpinner.style.display = 'inline-block';
                
                try {
                    // Call your API endpoint
                    const response = await fetch('https://hrm.shurttech.com/api/forgot-password', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                        },
                        body: JSON.stringify({
                            email: email
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showMessage('✅ ' + result.message, 'success');
                        
                        // Clear form
                        form.reset();
                        
                        // Optionally redirect or show countdown
                        setTimeout(() => {
                            hideMessage();
                        }, 5000);
                        
                    } else {
                        showMessage('❌ ' + result.message, 'danger');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showMessage('❌ Network error. Please check your connection and try again.', 'danger');
                } finally {
                    // Reset button state
                    submitBtn.disabled = false;
                    btnText.textContent = 'Send Reset Link';
                    btnSpinner.style.display = 'none';
                }
            });
            
            // Helper functions
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(String(email).toLowerCase());
            }
            
            function showMessage(text, type) {
                messageDiv.textContent = text;
                messageDiv.className = `alert alert-${type}`;
                messageDiv.style.display = 'block';
                
                // Auto-hide success messages after 5 seconds
                if (type === 'success') {
                    setTimeout(() => {
                        hideMessage();
                    }, 5000);
                }
            }
            
            function hideMessage() {
                messageDiv.style.display = 'none';
            }
        });
    </script>
</body>

</html>