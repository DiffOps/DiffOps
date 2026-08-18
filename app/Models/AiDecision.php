<?php

namespace App\Models;

use App\Enums\AiDecisionValidity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiDecision extends Model
{
    use HasUuids;

    /**
     * AiDecision rows are immutable evidence: there is no updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'risk_assessment_id',
        'model_used',
        'attempt',
        'validity',
        'raw_response',
        'ai_signals',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'latency_ms',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'validity' => AiDecisionValidity::class,
            'ai_signals' => 'array',
            'attempt' => 'integer',
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'total_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    /**
     * The risk assessment this decision belongs to.
     *
     * @return BelongsTo<RiskAssessment, $this>
     */
    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(RiskAssessment::class);
    }
}
