<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CandidateDocument extends Model
{
    protected $table = 'candidate_documents';

    protected $fillable = [
        'tenant_id',
        'candidate_id',
        'document_type',
        'document_name',
        'file_url',
        'file_size',
        'mime_type',
        'uploaded_by',
        'is_verified',
        'verified_by',
        'verified_at',
        'uploaded_at'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Document type constants
    const TYPE_RESUME = 'resume';
    const TYPE_COVER_LETTER = 'cover_letter';
    const TYPE_OFFER_LETTER = 'offer_letter';
    const TYPE_ACCEPTANCE_LETTER = 'acceptance_letter';
    const TYPE_PHOTO = 'photo';
    const TYPE_ID_PROOF = 'id_proof';
    const TYPE_ADDRESS_PROOF = 'address_proof';
    const TYPE_EDUCATIONAL_CERTIFICATE = 'educational_certificate';
    const TYPE_EXPERIENCE_LETTER = 'experience_letter';
    const TYPE_RELIEVING_LETTER = 'relieving_letter';
    const TYPE_SALARY_SLIP = 'salary_slip';
    const TYPE_OTHER = 'other';

    public static $documentTypes = [
        self::TYPE_RESUME => 'Resume',
        self::TYPE_COVER_LETTER => 'Cover Letter',
        self::TYPE_OFFER_LETTER => 'Offer Letter',
        self::TYPE_ACCEPTANCE_LETTER => 'Acceptance Letter',
        self::TYPE_PHOTO => 'Photo',
        self::TYPE_ID_PROOF => 'ID Proof',
        self::TYPE_ADDRESS_PROOF => 'Address Proof',
        self::TYPE_EDUCATIONAL_CERTIFICATE => 'Educational Certificate',
        self::TYPE_EXPERIENCE_LETTER => 'Experience Letter',
        self::TYPE_RELIEVING_LETTER => 'Relieving Letter',
        self::TYPE_SALARY_SLIP => 'Salary Slip',
        self::TYPE_OTHER => 'Other'
    ];

    // Relationships
    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Accessors
    public function getDocumentTypeLabelAttribute()
    {
        return self::$documentTypes[$this->document_type] ?? ucfirst(str_replace('_', ' ', $this->document_type));
    }

    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }

    public function getIsVerifiedAttribute()
    {
        return (bool) $this->attributes['is_verified'];
    }

    // Scopes
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeUnverified($query)
    {
        return $query->where('is_verified', false);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    // Helper methods
    public function verify($remarks = null)
    {
        $this->update([
            'is_verified' => true,
            'verified_by' => auth()->id(),
            'verified_at' => now()
        ]);
    }

    public function reject()
    {
        $this->update([
            'is_verified' => false,
            'verified_by' => auth()->id(),
            'verified_at' => now()
        ]);
    }
}