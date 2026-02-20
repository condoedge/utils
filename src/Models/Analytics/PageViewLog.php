<?php

namespace Condoedge\Utils\Models\Analytics;

use Condoedge\Utils\Facades\TeamModel;
use Condoedge\Utils\Models\Traits\BelongsToUserTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PageViewLog extends Model
{
    use BelongsToUserTrait;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'page_view_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'user_id_hashed',
        'team_id',
        'team_level',
        'user_role',
        'user_type',
        'page_url',
        'page_title',
        'http_method',
        'query_params',
        'referer_url',
        'user_agent',
        'ip_address',
        'session_id',
        'is_authenticated',
        'is_super_admin',
        'environment',
        'viewed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'query_params' => 'array',
        'is_authenticated' => 'boolean',
        'is_super_admin' => 'boolean',
        'viewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'ip_address',
        'user_agent',
        'session_id',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the team context for this page view.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(TeamModel::getClass());
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope query to a specific team.
     */
    public function scopeForTeam(Builder $query, $team): Builder
    {
        $teamId = is_object($team) ? $team->id : $team;

        return $query->where('team_id', $teamId);
    }

    /**
     * Scope query to a specific user role.
     */
    public function scopeByRole(Builder $query, string $role): Builder
    {
        return $query->where('user_role', $role);
    }

    /**
     * Scope query to a specific team level.
     */
    public function scopeByLevel(Builder $query, string $level): Builder
    {
        return $query->where('team_level', $level);
    }

    /**
     * Scope query to a specific user type.
     */
    public function scopeByUserType(Builder $query, string $userType): Builder
    {
        return $query->where('user_type', $userType);
    }

    /**
     * Scope query to authenticated users only.
     */
    public function scopeAuthenticated(Builder $query): Builder
    {
        return $query->where('is_authenticated', true);
    }

    /**
     * Scope query to guest (unauthenticated) users only.
     */
    public function scopeGuests(Builder $query): Builder
    {
        return $query->where('is_authenticated', false);
    }

    /**
     * Scope query to super admins only.
     */
    public function scopeSuperAdmins(Builder $query): Builder
    {
        return $query->where('is_super_admin', true);
    }

    /**
     * Scope query to a date range.
     */
    public function scopeDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('viewed_at', [$startDate, $endDate]);
    }

    /**
     * Scope query to today's views.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('viewed_at', today());
    }

    /**
     * Scope query to this week's views.
     */
    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('viewed_at', [
            now()->startOfWeek(),
            now()->endOfWeek(),
        ]);
    }

    /**
     * Scope query to this month's views.
     */
    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('viewed_at', now()->month)
            ->whereYear('viewed_at', now()->year);
    }

    /**
     * Scope query to specific page URL.
     */
    public function scopeForPage(Builder $query, string $pageUrl): Builder
    {
        return $query->where('page_url', $pageUrl);
    }

    /**
     * Scope query to specific HTTP method.
     */
    public function scopeByMethod(Builder $query, string $method): Builder
    {
        return $query->where('http_method', strtoupper($method));
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    /**
     * Get the browser name from user agent.
     */
    public function getBrowserAttribute(): ?string
    {
        if (!$this->user_agent) {
            return null;
        }

        if (str_contains($this->user_agent, 'Chrome')) {
            return 'Chrome';
        } elseif (str_contains($this->user_agent, 'Firefox')) {
            return 'Firefox';
        } elseif (str_contains($this->user_agent, 'Safari')) {
            return 'Safari';
        } elseif (str_contains($this->user_agent, 'Edge')) {
            return 'Edge';
        }

        return 'Other';
    }

    /**
     * Get the device type from user agent.
     */
    public function getDeviceTypeAttribute(): string
    {
        if (!$this->user_agent) {
            return 'Unknown';
        }

        if (str_contains($this->user_agent, 'Mobile')) {
            return 'Mobile';
        } elseif (str_contains($this->user_agent, 'Tablet')) {
            return 'Tablet';
        }

        return 'Desktop';
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this page view is from a super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    /**
     * Check if this page view is from an authenticated user.
     */
    public function isAuthenticated(): bool
    {
        return $this->is_authenticated === true;
    }

    /**
     * Get a human-readable time ago string.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->viewed_at?->diffForHumans() ?? 'Unknown';
    }
}
