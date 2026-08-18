<?php

namespace App\Models;

use App\Enums\PrState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PullRequest extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'github_repo_id',
        'repo_full_name',
        'github_pr_number',
        'title',
        'author_username',
        'author_avatar_url',
        'base_ref',
        'head_ref',
        'head_sha',
        'state',
        'is_draft',
        'closed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => PrState::class,
            'is_draft' => 'boolean',
            'closed_at' => 'datetime',
            'github_repo_id' => 'integer',
            'github_pr_number' => 'integer',
        ];
    }

    /**
     * The organization this pull request belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Files changed by this pull request.
     *
     * @return HasMany<PullRequestFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(PullRequestFile::class);
    }

    /**
     * Risk assessments produced for this pull request.
     *
     * @return HasMany<RiskAssessment, $this>
     */
    public function riskAssessments(): HasMany
    {
        return $this->hasMany(RiskAssessment::class);
    }
}
