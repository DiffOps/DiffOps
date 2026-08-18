<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepoWatchlist extends Model
{
    /**
     * Repo watchlist entries are immutable: there is no updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * The table does not follow Laravel's pluralization.
     */
    protected $table = 'repo_watchlist';

    /**
     * There is no surrogate primary key: the composite primary key
     * (user_id, repository_id) is enforced by the database. A composite
     * $primaryKey array is NOT declared because Eloquent would throw a
     * TypeError on find()/update()/delete(). Access entries with a
     * composite where('user_id', ...)->where('repository_id', ...) query.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'repository_id',
    ];

    /**
     * The user watching the repository.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The repository being watched.
     *
     * @return BelongsTo<Repository, $this>
     */
    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }
}
