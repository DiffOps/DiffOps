<?php

namespace App\Models;

use App\Enums\DefconLevel;
use App\Enums\RiskLevel;
use App\Enums\Verdict;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskAssessment extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pull_request_id',
        'head_sha',
        'verdict',
        'defcon_level',
        'security_score',
        'risk_level',
        'summary',
        'compliance_checks',
        'execution_time_ms',
        'is_degraded',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verdict' => Verdict::class,
            'defcon_level' => DefconLevel::class,
            'risk_level' => RiskLevel::class,
            'compliance_checks' => 'array',
            'security_score' => 'integer',
            'execution_time_ms' => 'integer',
            'is_degraded' => 'boolean',
        ];
    }

    /**
     * The pull request this assessment belongs to.
     *
     * @return BelongsTo<PullRequest, $this>
     */
    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }

    /**
     * AI model decisions recorded for this assessment.
     *
     * @return HasMany<AiDecision, $this>
     */
    public function aiDecisions(): HasMany
    {
        return $this->hasMany(AiDecision::class);
    }
}
