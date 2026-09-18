<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TenantTrait;

class EmployeeDocument extends Model
{
    use TenantTrait, SoftDeletes;

    protected $table = 'employee_documents';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'document_type',
        'document_type_other',
        'document_name',
        'file_path',
        'original_filename',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const TYPE_AADHAAR_CARD = 'aadhaar_card';
    const TYPE_PAN_CARD = 'pan_card';
    const TYPE_PASSPORT = 'passport';
    const TYPE_TENTH_MARKSHEET = 'tenth_marksheet';
    const TYPE_TWELFTH_MARKSHEET = 'twelfth_marksheet';
    const TYPE_HIGHEST_QUALIFICATION_CERTIFICATE = 'highest_qualification_certificate';
    const TYPE_EXPERIENCE_LETTER = 'experience_letter';
    const TYPE_RELIEVING_LETTER = 'relieving_letter';
    const TYPE_OFFER_LETTER = 'offer_letter';
    const TYPE_SALARY_SLIP = 'salary_slip';
    const TYPE_ADDRESS_PROOF = 'address_proof';
    const TYPE_PHOTO_ID = 'photo_id';
    const TYPE_OTHER = 'other';

    public static $documentTypes = [
        self::TYPE_AADHAAR_CARD => 'Aadhaar Card',
        self::TYPE_PAN_CARD => 'PAN Card',
        self::TYPE_PASSPORT => 'Passport',
        self::TYPE_TENTH_MARKSHEET => '10th Marksheet',
        self::TYPE_TWELFTH_MARKSHEET => '12th Marksheet',
        self::TYPE_HIGHEST_QUALIFICATION_CERTIFICATE => 'Highest Qualification Certificate',
        self::TYPE_EXPERIENCE_LETTER => 'Experience Letter',
        self::TYPE_RELIEVING_LETTER => 'Relieving Letter',
        self::TYPE_OFFER_LETTER => 'Offer Letter',
        self::TYPE_SALARY_SLIP => 'Salary Slip',
        self::TYPE_ADDRESS_PROOF => 'Address Proof',
        self::TYPE_PHOTO_ID => 'Photo ID',
        self::TYPE_OTHER => 'Other',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDocumentTypeLabelAttribute()
    {
        if ($this->document_type === self::TYPE_OTHER && $this->document_type_other) {
            return $this->document_type_other;
        }

        return self::$documentTypes[$this->document_type] ?? ucfirst(str_replace('_', ' ', $this->document_type));
    }

    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }
}
