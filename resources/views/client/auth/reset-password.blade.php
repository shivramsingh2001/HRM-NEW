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
    <title>Reset Password</title>
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
                    <h2 class="fs-20 fw-bolder mb-4">Reset Password</h2>
                    <h4 class="fs-13 fw-bold mb-2">Create New Password</h4>
                    <p class="fs-12 fw-medium text-muted">Enter your new password below. Make sure it's 6-10 characters long.</p>
                    
                    <!-- Alert Message -->
                    <div id="message" class="alert" role="alert"></div>
                    
                    <form id="resetForm" class="w-100 mt-4 pt-2">
                        <input type="hidden" id="token" value="{{ $token }}">
                        <input type="hidden" id="email" value="{{ $email }}">
                        
                        <div class="mb-4">
                            <label class="form-label fs-12 fw-bold text-muted mb-2">New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" 
                                       placeholder="Enter new password" required minlength="6" maxlength="10">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="feather-eye"></i>
                                </button>
                            </div>
                            <div class="form-text fs-11 text-muted">Password must be 6-10 characters</div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label fs-12 fw-bold text-muted mb-2">Confirm Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password_confirmation" 
                                       placeholder="Confirm new password" required minlength="6" maxlength="10">
                                <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                                    <i class="feather-eye"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button type="submit" class="btn btn-lg btn-primary w-100" id="submitBtn">
                                <span id="btnText">Reset Password</span>
                                <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2" role="status" aria-hidden="true"></span>
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-3 text-center">
                        <p class="fs-12 text-muted mb-0">Remember your password?</p>
                        <a href="{{ route('login') }}" class="fs-11 text-primary fw-bold">Back to Login</a>
                    </div>
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
            // Password toggle functionality
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('password_confirmation');
            
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('feather-eye');
                this.querySelector('i').classList.toggle('feather-eye-off');
            });
            
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                this.querySelector('i').classList.toggle('feather-eye');
                this.querySelector('i').classList.toggle('feather-eye-off');
            });
            
            // Check if token and email are valid
            const token = document.getElementById('token').value;
            const email = document.getElementById('email').value;
            
            if (!token || !email) {
                showMessage('Invalid or expired reset link. Please request a new password reset.', 'danger');
                document.getElementById('resetForm').style.display = 'none';
                return;
            }
            
            // Form submission
            document.getElementById('resetForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // Get form data
                const password = document.getElementById('password').value;
                const password_confirmation = document.getElementById('password_confirmation').value;
                
                // Hide previous message
                hideMessage();
                
                // Validation
                if (password !== password_confirmation) {
                    showMessage('Passwords do not match!', 'danger');
                    return;
                }
                
                if (password.length < 6 || password.length > 10) {
                    showMessage('Password must be 6-10 characters long', 'danger');
                    return;
                }
                
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                const btnText = document.getElementById('btnText');
                const btnSpinner = document.getElementById('btnSpinner');
                
                submitBtn.disabled = true;
                btnText.textContent = 'Processing...';
                btnSpinner.style.display = 'inline-block';
                
                try {
                    // Call API endpoint
                    const response = await fetch('/api/reset-password', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            token: token,
                            email: email,
                            password: password,
                            password_confirmation: password_confirmation
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showMessage('✅ ' + result.message + ' Redirecting to login page...', 'success');
                        document.getElementById('resetForm').reset();
                        submitBtn.style.display = 'none';
                        
                        // Redirect to login after 3 seconds
                        setTimeout(() => {
                            window.location.href = '/';
                        }, 3000);
                    } else {
                        showMessage('❌ ' + result.message, 'danger');
                    }
                } catch (error) {
                    showMessage('❌ Network error. Please try again.', 'danger');
                    console.error('Reset password error:', error);
                } finally {
                    // Reset button state
                    submitBtn.disabled = false;
                    btnText.textContent = 'Reset Password';
                    btnSpinner.style.display = 'none';
                }
            });
            
            // Message functions
            function showMessage(text, type) {
                const messageDiv = document.getElementById('message');
                messageDiv.textContent = text;
                messageDiv.className = `alert alert-${type}`;
                messageDiv.style.display = 'block';
                
                // Auto-hide error messages after 5 seconds
                if (type === 'danger') {
                    setTimeout(() => {
                        messageDiv.style.display = 'none';
                    }, 5000);
                }
            }
            
            function hideMessage() {
                const messageDiv = document.getElementById('message');
                messageDiv.style.display = 'none';
            }
        });
    </script>
</body>

</html>