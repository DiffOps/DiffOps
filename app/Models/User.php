<?php

namespace App\Models;

use App\Enums\OrganizationRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'supabase_uid',
        'github_username',
        'avatar_url',
        'is_commander',
        'is_active',
        'preferences',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_commander' => 'boolean',
            'is_active' => 'boolean',
            'preferences' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Whether this user holds the global commander flag.
     */
    public function isCommander(): bool
    {
        return (bool) $this->is_commander;
    }

    /**
     * Whether this user is a member of the given organization.
     */
    public function isMemberOf(Organization $org): bool
    {
        return $this->memberships()->where('organization_id', $org->id)->exists();
    }

    /**
     * The tactical role of this user inside the organization, if any.
     */
    public function roleIn(Organization $org): ?OrganizationRole
    {
        return $this->memberships()->where('organization_id', $org->id)->first()?->role;
    }

    /**
     * Organization memberships of this user.
     *
     * @return HasMany<OrganizationMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * Organizations this user belongs to (through the members pivot).
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members');
    }
}
