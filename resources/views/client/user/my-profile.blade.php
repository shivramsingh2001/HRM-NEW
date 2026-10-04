@extends('client.layout.master')

@section('title', 'My Profile')

@section('style')
<style>
    .mp-hero { display: flex; gap: 16px; align-items: center; background: #fff; border: 1px solid var(--border, #e5e7eb); border-radius: 10px; padding: 16px; margin-bottom: 14px; }
    .mp-avatar { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; flex: none; display: inline-flex; align-items: center; justify-content: center; background: #0D6EFD; color: #fff; font-size: 22px; font-weight: 700; }
    .mp-name { font-size: 16px; font-weight: 700; color: #0f172a; }
    .mp-sub { font-size: 12px; color: #475569; }
    .mp-chip { display: inline-block; font-size: 10px; font-weight: 600; padding: 2px 9px; border-radius: 20px; background: #EFF6FF; color: #0D6EFD; margin-right: 4px; margin-top: 6px; }
    .mp-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px; }
    @media (max-width: 992px) { .mp-grid { grid-template-columns: 1fr; } }
    .mp-card .card-header h6 { font-size: 12.5px; font-weight: 700; margin: 0; color: #0f172a; }
    .mp-dl { display: grid; grid-template-columns: 160px 1fr; margin: 0; }
    .mp-dl dt, .mp-dl dd { font-size: 12px; padding: 7px 16px; margin: 0; border-bottom: 1px solid #f1f5f9; }
    .mp-dl dt { color: #64748b; font-weight: 500; }
    .mp-dl dd { color: #0f172a; word-break: break-word; }
    .mp-note { font-size: 11px; color: #64748b; padding: 10px 16px; }
    .mp-form label { font-size: 11.5px; font-weight: 600; color: #334155; margin-bottom: 4px; }
    .mp-form .form-control { font-size: 12px; height: 34px; }
    .mp-form .hint { font-size: 10.5px; color: #64748b; }
</style>
@endsection

@section('content-area')
    @php
        $val = fn ($v) => ($v === null || $v === '') ? '—' : $v;
        $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->format('d M Y') : '—';
        $mask = fn ($v, $keep = 4) => $v ? str_repeat('•', max(0, strlen($v) - $keep)) . substr($v, -$keep) : '—';
        $photo = $basic?->profile_image ? file_url($basic->profile_image, 'profile_photo') : null;
        $initials = strtoupper(collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
    @endphp

    <x-ui.page-header class="content-area-header sticky-top" title="My Profile" current="Employee Details" />

    <div class="content-area-body" style="padding: 20px !important;">
        @if (session('success'))
            <div class="alert alert-success py-2" style="font-size:12px;">{{ session('success') }}</div>
        @endif

        <div class="mp-hero">
            @if ($photo)
                <img src="{{ $photo }}" alt="{{ $user->name }}" class="mp-avatar">
            @else
                <span class="mp-avatar">{{ $initials }}</span>
            @endif
            <div>
                <div class="mp-name">{{ $user->name }}</div>
                <div class="mp-sub">{{ $user->email }} · Employee ID: <strong>{{ $val($user->employee_id) }}</strong></div>
                <div>
                    <span class="mp-chip">{{ $user->role === 'hr' ? 'HR' : ucfirst($user->role) }}</span>
                    @if ($job?->designationRel)<span class="mp-chip">{{ $job->designationRel->name }}</span>@endif
                    @if ($job?->departmentRel)<span class="mp-chip">{{ $job->departmentRel->name }}</span>@endif
                </div>
            </div>
        </div>

        <div class="mp-grid">
            <div class="card mp-card mb-0">
                <div class="card-header"><h6><i class="feather-user me-1"></i> Personal Details</h6></div>
                <dl class="mp-dl">
                    <dt>Full name</dt><dd>{{ $user->name }}</dd>
                    <dt>Email</dt><dd>{{ $user->email }}</dd>
                    <dt>Phone</dt><dd>{{ $val($user->contact) }}</dd>
                    <dt>Alternate phone</dt><dd>{{ $val($basic?->alternate_phone) }}</dd>
                    <dt>Personal email</dt><dd>{{ $val($basic?->personal_email) }}</dd>
                    <dt>Date of birth</dt><dd>{{ $date($basic?->dob) }}</dd>
                    <dt>Gender</dt><dd>{{ $val($basic?->gender ? ucfirst($basic->gender) : null) }}</dd>
                    <dt>Blood group</dt><dd>{{ $val($basic?->blood_group) }}</dd>
                    <dt>Marital status</dt><dd>{{ $val($basic?->marital_status ? ucfirst($basic->marital_status) : null) }}</dd>
                    <dt>Father's name</dt><dd>{{ $val($basic?->father_name) }}</dd>
                    <dt>Nationality</dt><dd>{{ $val($basic?->nationality) }}</dd>
                </dl>
            </div>

            <div class="card mp-card mb-0">
                <div class="card-header"><h6><i class="feather-briefcase me-1"></i> Job Details</h6></div>
                <dl class="mp-dl">
                    <dt>Employee ID</dt><dd>{{ $val($user->employee_id) }}</dd>
                    <dt>Designation</dt><dd>{{ $val($job?->designationRel?->name) }}</dd>
                    <dt>Department</dt><dd>{{ $val($job?->departmentRel?->name) }}</dd>
                    @feature('branches')<dt>Branch</dt><dd>{{ $val($job?->branch?->name) }}</dd>@endfeature
                    <dt>Attendance location</dt><dd>{{ $attendanceLocation }}</dd>
                    <dt>Reporting to</dt><dd>{{ $reportingHeads ? implode(', ', $reportingHeads) : '—' }}</dd>
                    <dt>Joining date</dt><dd>{{ $date($job?->joining_date) }}</dd>
                    <dt>Employment type</dt><dd>{{ $val($job?->employment_type ? ucwords(str_replace('_', ' ', $job->employment_type)) : null) }}</dd>
                    <dt>Attendance type</dt><dd>{{ match ($job?->attendance_type) { 'face_verification' => 'Face Verification', 'biometric_only' => 'Biometric', 'manual_attendance' => 'Manual', default => '—' } }}</dd>
                </dl>
            </div>

            <div class="card mp-card mb-0">
                <div class="card-header"><h6><i class="feather-file-text me-1"></i> Identity &amp; Statutory</h6></div>
                <dl class="mp-dl">
                    <dt>Aadhaar no.</dt><dd>{{ $mask($basic?->aadhaar_no) }}</dd>
                    <dt>PAN no.</dt><dd>{{ $mask($basic?->pan_no) }}</dd>
                    <dt>UAN no.</dt><dd>{{ $val($basic?->uan_no ?: $bank?->uan_no) }}</dd>
                    <dt>PF no.</dt><dd>{{ $val($basic?->pf_no ?: $bank?->pf_no) }}</dd>
                    <dt>ESIC no.</dt><dd>{{ $val($basic?->esic_no ?: $bank?->esi_no) }}</dd>
                </dl>
                <div class="card-header border-top"><h6><i class="feather-credit-card me-1"></i> Bank Details</h6></div>
                <dl class="mp-dl">
                    <dt>Bank</dt><dd>{{ $val($bank?->bank_name) }}</dd>
                    <dt>Account no.</dt><dd>{{ $mask($bank?->account_number) }}</dd>
                    <dt>IFSC</dt><dd>{{ $val($bank?->ifsc) }}</dd>
                    <dt>Bank branch</dt><dd>{{ $val($bank?->branch_name) }}</dd>
                </dl>
                <div class="mp-note"><i class="feather-info me-1"></i> These details are maintained by HR. Contact HR if anything needs correcting.</div>
            </div>

            <div class="card mp-card mb-0" id="change-password">
                <div class="card-header"><h6><i class="feather-lock me-1"></i> Change Password</h6></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('my-profile.password') }}" class="mp-form" autocomplete="off">
                        @csrf
                        <div class="mb-3">
                            <label for="current_password">Current password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                            @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="new_password">New password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control @error('new_password') is-invalid @enderror" required>
                            @error('new_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="hint mt-1">At least 8 characters, with upper- and lower-case letters and a number.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password">Confirm new password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control @error('confirm_password') is-invalid @enderror" required>
                            @error('confirm_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="feather-save me-1"></i> Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
