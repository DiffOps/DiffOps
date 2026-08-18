<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Repository extends Model
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
        'full_name',
        'is_private',
        'comment_on_pr',
        'escalate_on_hostile',
        'escalation_webhook_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'comment_on_pr' => 'boolean',
            'escalate_on_hostile' => 'boolean',
            'github_repo_id' => 'integer',
        ];
    }

    /**
     * The organization this repository belongs to.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Users watching this repository for tactical incursions.
     *
     * @return BelongsToMany<User, $this>
     */
    public function watchlistUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'repo_watchlist', 'repository_id', 'user_id');
    }

    /**
     * Raw watchlist entries (the append-only pivot) for this repository.
     *
     * @return HasMany<RepoWatchlist, $this>
     */
    public function watchlistEntries(): HasMany
    {
        return $this->hasMany(RepoWatchlist::class);
    }

    /**
     * No pullRequests() relation: the pull_requests table is denormalized
     * (no FK to repositories). Fetch PRs per repository with a query on
     * organization_id + github_repo_id instead.
     */
}
