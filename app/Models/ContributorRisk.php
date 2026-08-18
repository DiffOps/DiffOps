<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributorRisk extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'author_username',
        'score',
        'total_prs',
        'flagged_prs',
        'hostile_prs',
        'avg_findings_per_pr',
        'is_new_contributor',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'total_prs' => 'integer',
            'flagged_prs' => 'integer',
            'hostile_prs' => 'integer',
            'avg_findings_per_pr' => 'decimal:2',
            'is_new_contributor' => 'boolean',
        ];
    }

    /**
     * The organization this risk fingerprint belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
