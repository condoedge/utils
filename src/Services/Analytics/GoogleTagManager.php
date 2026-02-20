<?php

namespace Condoedge\Utils\Services\Analytics;

class GoogleTagManager
{
    /**
     * Check if analytics tracking is enabled (GA4 measurement ID configured)
     */
    public static function isEnabled(): bool
    {
        return !empty(config('services.google_analytics.measurement_id'));
    }

    /**
     * Get the GA4 measurement ID
     */
    public static function getMeasurementId(): ?string
    {
        return config('services.google_analytics.measurement_id');
    }

    /**
     * Get current user data for GA4 user properties
     * Includes user identity, team context, and metadata
     */
    public static function getUserData(): array
    {
        if (!auth()->check()) {
            return [];
        }

        $user = auth()->user();

        // Basic user identity (hashed for privacy)
        $userData = [
            'app_user_id' => self::hashUserId($user->id),
            'userIdRaw' => $user->id, // Keep raw for internal tracking (will filter in blade)
            'userEmail' => $user->email,
            'userName' => $user->name,
        ];

        // User role
        if (method_exists($user, 'getRoleNames')) {
            $userData['userRole'] = $user->getRoleNames()->first() ?? 'user';
        }

        // Team context (multi-tenancy)
        $team = safeCurrentTeam();
        if ($team) {
            $userData['teamId'] = $team->id;
            $userData['teamLevel'] = $team->team_level->value ?? ($team->level ?? 'unknown');
        }

        $teamRole = safeCurrentTeamRole();
        if ($teamRole) {
            $userData['teamRoleId'] = $teamRole->id;
            $userData['teamRoleName'] = $teamRole->roleRelation->name ?? ($teamRole->role->name ?? 'unknown');
        }

        // User type flags
        $userData['isParent'] = method_exists($user, 'isParent') ? $user->isParent() : false;
        $userData['isSuperAdmin'] = safeIsSuperAdmin();

        // Metadata
        $userData['environment'] = config('app.env');
        $userData['timestamp'] = now()->toIso8601String();

        return $userData;
    }

    /**
     * Hash user ID for privacy (GDPR compliance)
     * Uses SHA-256 with app key as salt
     */
    public static function hashUserId($userId): string
    {
        return hash('sha256', config('app.key') . $userId);
    }
}
