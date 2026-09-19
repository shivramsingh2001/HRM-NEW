<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Traits\TenantTrait;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use TenantTrait, HasFactory, Notifiable;

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'employee_id',
        'card_number',
        'password',
        'role',
        'contact',
        'status',
        'company_id',
         'fcm_tokens' => 'array', // If multi-company SaaS

    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
              'fcm_tokens' => 'array', 
        ];
    }
    /**
     * Get the basic details for the user.
     */
    public function basicDetails()
    {
        return $this->hasOne(UserBasicDetail::class);
    }

    /**
     * Get the job details for the user.
     */
    public function jobDetails()
    {
        return $this->hasOne(UserJobDetail::class);
    }
    public function payrolls()
    {
        return $this->hasOne(UserPayroll::class);
    }

    /**
     * Get the bank details for the user.
     */
    public function bankDetails()
    {
        return $this->hasOne(UserBankDetail::class);
    }

    /**
     * Get the location details for the user.
     */
    public function location()
    {
        return $this->hasOne(UserLocation::class);
    }
    public function shifts()
    {
        return $this->hasMany(UserShift::class, 'user_id');
    }

    /**
     * Get the week offs for this user
     */
    public function weekoffs()
    {
        return $this->hasMany(UserWeekoffs::class, 'user_id');
    }
    public function scopeFindByEmployeeId($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId)
            ->where('tenant_id', session('tenant_id'));
    }
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function teamMembers()
    {
        return $this->hasMany(UserJobDetail::class, 'reporting_head', 'user_id');
    }

    /**
     * Full set of reporting heads (multi-head support). Kept alongside
     * user_job_details.reporting_head, which remains the denormalized
     * primary head for backward compatibility. See docs/modules.md.
     */
    public function reportingHeads()
    {
        return $this->belongsToMany(User::class, 'user_reporting_heads', 'user_id', 'reporting_head_id')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function directReports()
    {
        return $this->belongsToMany(User::class, 'user_reporting_heads', 'reporting_head_id', 'user_id')
            ->withTimestamps();
    }

    public function documents()
    {
        return $this->hasMany(EmployeeDocument::class, 'user_id');
    }

    public function assetAssignments()
    {
        return $this->hasMany(AssetAssignment::class, 'user_id');
    }

    /**
     * Canonical "my team" query: users who report to $headId via ANY of
     * their reporting heads, not just the primary. Replaces ad hoc
     * UserJobDetail::where('reporting_head', $headId) call sites.
     */
    public function scopeManagedBy($query, $headId)
    {
        return $query->whereHas('reportingHeads', function ($q) use ($headId) {
            $q->where('reporting_head_id', $headId);
        });
    }
    public function UserPayrolls()
    {
        return $this->hasMany(UserPayroll::class, 'user_id');
    }

    public function currentPayroll()
    {
        return $this->hasOne(UserPayroll::class, 'user_id')->where('is_current', true);
    }

    /** Payroll rebuild — Phase 8: the dynamic-engine equivalent of currentPayroll(). */
    public function dynamicPayrollStructures()
    {
        return $this->hasMany(\App\Models\PayrollEmployeeStructure::class, 'user_id');
    }

    public function currentDynamicPayrollStructure()
    {
        return $this->hasOne(\App\Models\PayrollEmployeeStructure::class, 'user_id')->where('is_current', true);
    }

    public function monthlyPayrolls()
    {
        return $this->hasMany(MonthlyPayroll::class, 'user_id');
    }

    // Add these relationships to your User model

    public function loans()
    {
        return $this->hasMany(Loan::class);
    }

    public function activeLoans()
    {
        return $this->hasMany(Loan::class)->where('status', 'active');
    }

    public function loanRepayments()
    {
        return $this->hasManyThrough(LoanRepayment::class, Loan::class);
    }

    public function getTotalLoanAmountAttribute()
    {
        return $this->loans()->sum('amount');
    }

    public function getRemainingLoanAmountAttribute()
    {
        return $this->loans()->sum('remaining_amount');
    }

    public function getMonthlyLoanDeductionAttribute()
    {
        return $this->activeLoans()
            ->join('loan_repayments', 'loans.id', '=', 'loan_repayments.loan_id')
            ->where('loan_repayments.status', 'pending')
            ->where('loan_repayments.month', date('Y-m'))
            ->sum('loan_repayments.emi_amount');
    }
    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }

    public function getActiveFcmTokens()
    {
        return collect($this->fcm_tokens ?? [])->pluck('token')->toArray();
    }


    public function regularizationRequests()
    {
        return $this->hasMany(AttendanceRegularization::class);
    }
    
     public function leaveBalance()
    {
        return $this->hasMany(LeaveBalance::class);  // ✅ Correct for one-to-one relationship
    }

    /**
     * Get all leave transactions for the user
     * One user can have many transaction records
     */
    public function leaveTransactions()
    {
        return $this->hasMany(LeaveTransaction::class);
    }

    // Helper method to get joining date from job details
    public function getJoiningDateAttribute()
    {
        return $this->jobDetails?->joining_date;
    }

    // Helper method to get current leave balance
    public function getCurrentLeaveBalanceAttribute()
    {
        return $this->leaveBalance?->balance ?? 0;
    }
      public function offboardingRequests()
    {
        return $this->hasMany(OffboardingRequest::class, 'employee_id');
    }

    public function activeOffboardingRequest()
    {
        return $this->hasOne(OffboardingRequest::class, 'employee_id')
            ->whereIn('status', ['pending_approval', 'approved']);
    }

    public function completedOffboardingRequest()
    {
        return $this->hasOne(OffboardingRequest::class, 'employee_id')
            ->where('status', 'completed');
    }

    public function exitInterviews()
    {
        return $this->hasMany(ExitInterview::class, 'employee_id');
    }

    public function conductedExitInterviews()
    {
        return $this->hasMany(ExitInterview::class, 'interviewer_id');
    }

    public function approvedOffboardings()
    {
        return $this->hasMany(OffboardingRequest::class, 'approved_by');
    }

    public function createdOffboardings()
    {
        return $this->hasMany(OffboardingRequest::class, 'created_by');
    }

    // Accessor to check if employee is offboarded
    public function getIsOffboardedAttribute()
    {
        return $this->offboardingRequests()
            ->where('status', 'completed')
            ->exists();
    }

    // Accessor to get active offboarding
    public function getActiveOffboardingAttribute()
    {
        return $this->offboardingRequests()
            ->whereIn('status', ['pending_approval', 'approved'])
            ->first();
    }
}
