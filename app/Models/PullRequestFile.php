<?php

namespace App\Models;

use App\Enums\PrFileStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PullRequestFile extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'pull_request_id',
        'file_path',
        'status',
        'additions',
        'deletions',
        'bytes',
        'is_sensitive',
        'is_binary',
        'raw_patch',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PrFileStatus::class,
            'additions' => 'integer',
            'deletions' => 'integer',
            'bytes' => 'integer',
            'is_sensitive' => 'boolean',
            'is_binary' => 'boolean',
        ];
    }

    /**
     * The pull request this file belongs to.
     *
     * @return BelongsTo<PullRequest, $this>
     */
    public function pullRequest(): BelongsTo
    {
        return $this->belongsTo(PullRequest::class);
    }
}
