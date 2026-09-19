{{-- resources/views/public/jobs/apply.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Apply for {{ $job->title }} | {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Job Header - Compact */
        .job-header {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .job-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 8px;
        }

        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            color: #64748b;
            background: #f8fafc;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .job-meta-item i {
            color: #1e3a8a;
            font-size: 11px;
        }

        .job-type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .job-type-full_time { background: #dbeafe; color: #1e40af; }
        .job-type-part_time { background: #fef3c7; color: #92400e; }
        .job-type-contract { background: #f1f5f9; color: #475569; }
        .job-type-internship { background: #e0e7ff; color: #3730a3; }

        /* Form Container - Compact */
        .form-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .form-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 5px;
        }

        .form-subtitle {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eef2f6;
        }

        /* Form Sections - Compact */
        .form-section {
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #1e3a8a;
            display: inline-block;
        }

        .section-title i {
            font-size: 13px;
            margin-right: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 4px;
        }

        .required::after {
            content: "*";
            color: #ef4444;
            margin-left: 3px;
        }

        input, select, textarea {
            width: 100%;
            padding: 8px 12px;
            font-size: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.2s;
            font-family: inherit;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.1);
        }

        .error {
            border-color: #ef4444 !important;
        }

        .error-message {
            color: #ef4444;
            font-size: 10px;
            margin-top: 3px;
            display: block;
        }

        .help-text {
            font-size: 10px;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* File Upload - Compact */
        .file-upload {
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-upload:hover {
            border-color: #1e3a8a;
            background: #f8fafc;
        }

        .file-upload input {
            display: none;
        }

        .file-upload-icon {
            font-size: 32px;
            color: #1e3a8a;
            margin-bottom: 5px;
        }

        .file-upload-text {
            font-size: 11px;
            color: #64748b;
        }

        .file-name {
            margin-top: 5px;
            font-size: 10px;
            color: #1e3a8a;
        }

        /* Submit Button - Compact */
        .submit-btn {
            width: 100%;
            padding: 10px;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 15px;
        }

        .submit-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        /* Alert Messages - Compact */
        .alert {
            padding: 10px 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 12px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Loading Spinner */
        .loading {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-right: 6px;
            vertical-align: middle;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Success Page - Compact */
        .success-container {
            background: white;
            border-radius: 12px;
            padding: 30px 25px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .success-icon {
            width: 70px;
            height: 70px;
            background: #d1fae5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon i {
            font-size: 35px;
            color: #059669;
        }

        .success-title {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }

        .success-message {
            color: #64748b;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .btn-primary {
            background: #1e3a8a;
            border: none;
            padding: 8px 20px;
            font-size: 12px;
            border-radius: 8px;
            text-decoration: none;
            color: white;
            display: inline-block;
        }

        .btn-primary:hover {
            background: #5a67d8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            body { padding: 15px; }
            .job-title { font-size: 18px; }
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .job-header, .form-container { padding: 15px; }
            .success-container { padding: 25px 20px; }
            .success-title { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        @if(isset($success))
            <!-- Success Page -->
            <div class="success-container">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="success-title">Application Submitted!</h1>
                <p class="success-message">
                    Thank you for applying for <strong>{{ $job->title }}</strong>.<br>
                    We'll review your application shortly.
                </p>
                <a href="{{ route('public.jobs.list') }}" class="btn-primary">
                    <i class="fas fa-search"></i> Browse More Jobs
                </a>
            </div>
        @else
            <!-- Job Header -->
            <div class="job-header">
                <h1 class="job-title">{{ $job->title }}</h1>
                <div class="job-meta">
                    <span class="job-meta-item"><i class="fas fa-building"></i> {{ $job->department->name ?? config('app.name') }}</span>
                    <span class="job-meta-item"><i class="fas fa-map-marker-alt"></i> {{ $job->location ?? 'Remote' }}</span>
                    @if($job->experience_required)
                    <span class="job-meta-item"><i class="fas fa-chart-line"></i> {{ $job->experience_required }}</span>
                    @endif
                    <span class="job-meta-item">
                        <i class="fas fa-tag"></i>
                        <span class="job-type-badge job-type-{{ $job->employment_type }}">
                            {{ ucfirst(str_replace('_', ' ', $job->employment_type)) }}
                        </span>
                    </span>
                </div>
            </div>

            <!-- Application Form -->
            <div class="form-container">
                <h2 class="form-title">Application Form</h2>
                <p class="form-subtitle">Fill out the form below to apply</p>

                <div id="alertContainer"></div>

                <form id="applicationForm" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="job_opening_id" value="{{ $job->id }}">

                    <!-- Personal Information -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-user"></i> Personal Info</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">First Name</label>
                                <input type="text" name="first_name" id="first_name" placeholder="First name">
                                <span class="error-message" id="first_name_error"></span>
                            </div>
                            <div class="form-group">
                                <label class="required">Last Name</label>
                                <input type="text" name="last_name" id="last_name" placeholder="Last name">
                                <span class="error-message" id="last_name_error"></span>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="required">Email</label>
                                <input type="email" name="email" id="email" placeholder="you@example.com">
                                <span class="error-message" id="email_error"></span>
                            </div>
                            <div class="form-group">
                                <label class="required">Phone</label>
                                <input type="tel" name="phone" id="phone" placeholder="+91 XXXXX XXXXX">
                                <span class="error-message" id="phone_error"></span>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Alternate Phone</label>
                                <input type="tel" name="alternate_phone" placeholder="Alternate number">
                            </div>
                            <div class="form-group">
                                <label>Current Location</label>
                                <input type="text" name="current_location" placeholder="City, State">
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-briefcase"></i> Professional Info</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Total Experience</label>
                                <select name="total_experience">
                                    <option value="">Select</option>
                                    <option>Fresher</option>
                                    <option>1 year</option>
                                    <option>2 years</option>
                                    <option>3 years</option>
                                    <option>4 years</option>
                                    <option>5 years</option>
                                    <option>6-8 years</option>
                                    <option>9+ years</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Current Company</label>
                                <input type="text" name="current_company" placeholder="Current employer">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Current CTC</label>
                                <input type="number" name="current_ctc" step="10000" placeholder="Annual in INR">
                                <div class="help-text">Leave blank if N/A</div>
                            </div>
                            <div class="form-group">
                                <label>Expected CTC</label>
                                <input type="number" name="expected_ctc" step="10000" placeholder="Annual in INR">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Notice Period</label>
                                <select name="notice_period">
                                    <option value="">Select</option>
                                    <option>Immediate</option>
                                    <option>15 days</option>
                                    <option>30 days</option>
                                    <option>45 days</option>
                                    <option>60 days</option>
                                    <option>90 days</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Preferred Location</label>
                                <input type="text" name="preferred_location" placeholder="Preferred location">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Skills</label>
                            <textarea name="skills" rows="2" placeholder="PHP, Laravel, MySQL, JavaScript"></textarea>
                            <div class="help-text">Separate skills with commas</div>
                        </div>

                        <div class="form-group">
                            <label>Qualification</label>
                            <input type="text" name="qualification" placeholder="B.Tech, MBA, etc.">
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="form-section">
                        <h3 class="section-title"><i class="fas fa-info-circle"></i> Additional</h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label>How did you hear about us?</label>
                                <select name="source">
                                    <option value="">Select</option>
                                    <option value="career_page">Career Page</option>
                                    <option value="linkedin">LinkedIn</option>
                                    <option value="naukri">Naukri</option>
                                    <option value="indeed">Indeed</option>
                                    <option value="referral">Referral</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Referral Name</label>
                                <input type="text" name="source_detail" placeholder="Who referred you?">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Cover Letter</label>
                            <textarea name="cover_letter" rows="3" placeholder="Why are you interested in this position?"></textarea>
                        </div>

                        <div class="form-group">
                            <label class="required">Resume / CV</label>
                            <div class="file-upload" onclick="document.getElementById('resume').click()">
                                <input type="file" name="resume" id="resume" accept=".pdf,.doc,.docx" required>
                                <div class="file-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="file-upload-text">Click to upload resume</div>
                                <div class="file-upload-text" style="font-size: 10px;">PDF, DOC (Max 5MB)</div>
                                <div id="fileName" class="file-name"></div>
                            </div>
                            <span class="error-message" id="resume_error"></span>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </form>
            </div>
        @endif
    </div>

    <script>
        // File upload display
        document.getElementById('resume')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            document.getElementById('fileName').textContent = fileName || '';
        });

        // Form submission
        document.getElementById('applicationForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="loading"></span> Submitting...';
            submitBtn.disabled = true;
            
            // Clear previous errors
            document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
            document.querySelectorAll('input, select, textarea').forEach(el => el.classList.remove('error'));
            document.getElementById('alertContainer').innerHTML = '';
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('{{ route("public.jobs.apply") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (response.ok && data.success) {
                    window.location.href = '{{ route("public.jobs.apply.form", $job->id) }}?success=1';
                } else if (data.errors) {
                    for (const [field, messages] of Object.entries(data.errors)) {
                        const errorSpan = document.getElementById(`${field}_error`);
                        if (errorSpan) errorSpan.textContent = messages[0];
                        const input = document.getElementById(field);
                        if (input) input.classList.add('error');
                    }
                    const firstError = document.querySelector('.error');
                    if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.innerHTML = data.message || 'Submission failed';
                    document.getElementById('alertContainer').appendChild(alertDiv);
                }
            } catch (error) {
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger';
                alertDiv.innerHTML = 'Network error. Please try again.';
                document.getElementById('alertContainer').appendChild(alertDiv);
            } finally {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    </script>
</body>
</html>