<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportComment extends Model
{
    use HasUuids;

    /**
     * Report comments are immutable evidence: there is no updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'risk_assessment_id',
        'github_comment_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'github_comment_id' => 'integer',
        ];
    }

    /**
     * The risk assessment this comment reports on.
     *
     * @return BelongsTo<RiskAssessment, $this>
     */
    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(RiskAssessment::class);
    }
}
