<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantTrait;

class InterviewFeedback extends Model
{
    use TenantTrait;

    protected $table = 'interview_feedbacks';

    protected $fillable = [
        'tenant_id',
        'interview_id',
        'interviewer_id',
        'technical_skill',
        'communication_skill',
        'problem_solving',
        'cultural_fit',
        'experience_relevance',
        'overall_rating',
        'strengths',
        'weaknesses',
        'comments',
        'recommendation',
        'next_round_suggested',
        'submitted_at'
    ];

    protected $casts = [
        'technical_skill' => 'integer',
        'communication_skill' => 'integer',
        'problem_solving' => 'integer',
        'cultural_fit' => 'integer',
        'experience_relevance' => 'integer',
        'overall_rating' => 'decimal:2',
        'submitted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Recommendation constants
    const RECOMMENDATION_STRONG_HIRE = 'strong_hire';
    const RECOMMENDATION_HIRE = 'hire';
    const RECOMMENDATION_MAYBE = 'maybe';
    const RECOMMENDATION_NO_HIRE = 'no_hire';

    public static $recommendations = [
        self::RECOMMENDATION_STRONG_HIRE => 'Strong Hire',
        self::RECOMMENDATION_HIRE => 'Hire',
        self::RECOMMENDATION_MAYBE => 'Maybe',
        self::RECOMMENDATION_NO_HIRE => 'No Hire'
    ];

    // Relationships
    public function interview()
    {
        return $this->belongsTo(Interview::class);
    }

    public function interviewer()
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    // Accessors
    public function getRecommendationLabelAttribute()
    {
        return self::$recommendations[$this->recommendation] ?? ucfirst($this->recommendation);
    }

    public function getAverageRatingAttribute()
    {
        $ratings = [
            $this->technical_skill,
            $this->communication_skill,
            $this->problem_solving,
            $this->cultural_fit,
            $this->experience_relevance
        ];
        
        $validRatings = array_filter($ratings);
        
        if (count($validRatings) > 0) {
            return round(array_sum($validRatings) / count($validRatings), 2);
        }
        
        return $this->overall_rating ?? 0;
    }

    public function getIsPositiveAttribute()
    {
        return in_array($this->recommendation, [self::RECOMMENDATION_STRONG_HIRE, self::RECOMMENDATION_HIRE]);
    }

    // Scopes
    public function scopePositive($query)
    {
        return $query->whereIn('recommendation', [self::RECOMMENDATION_STRONG_HIRE, self::RECOMMENDATION_HIRE]);
    }

    public function scopeNegative($query)
    {
        return $query->where('recommendation', self::RECOMMENDATION_NO_HIRE);
    }

    // Helper methods
    public function calculateOverallRating()
    {
        $ratings = [
            $this->technical_skill,
            $this->communication_skill,
            $this->problem_solving,
            $this->cultural_fit,
            $this->experience_relevance
        ];
        
        $validRatings = array_filter($ratings);
        
        if (count($validRatings) > 0) {
            $this->overall_rating = round(array_sum($validRatings) / count($validRatings), 2);
            $this->saveQuietly();
        }
    }
}