<?php

namespace Condoedge\Utils\Http\Middleware;

use Condoedge\Utils\Services\Analytics\GoogleTagManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogPageView
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Process the request first
        $response = $next($request);

        // Log page view asynchronously after response (doesn't block user)
        if ($this->shouldLogPageView($request)) {
            $this->logPageView($request);
        }

        return $response;
    }

    /**
     * Determine if the page view should be logged.
     */
    protected function shouldLogPageView(Request $request): bool
    {
        // Skip if analytics is disabled
        if (!config('analytics.enabled', true)) {
            return false;
        }

        // Skip excluded paths
        $excludedPaths = config('analytics.excluded_paths', []);
        foreach ($excludedPaths as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        // Skip excluded routes
        $excludedRoutes = config('analytics.excluded_routes', []);
        if ($request->route() && in_array($request->route()->getName(), $excludedRoutes)) {
            return false;
        }

        // Skip API requests (unless explicitly enabled)
        if ($request->is('api/*') && !config('analytics.log_api_requests', false)) {
            return false;
        }

        // Only log successful responses (2xx and 3xx)
        // Skip 4xx and 5xx errors
        if ($request->route() && !$request->route()->matches($request)) {
            return false;
        }

        return true;
    }

    /**
     * Log the page view to the database.
     */
    protected function logPageView(Request $request): void
    {
        try {
            $user = auth()->user();
            $team = safeCurrentTeam();
            $teamRole = safeCurrentTeamRole();

            // Prepare log data
            $logData = [
                // User identification
                'user_id' => $user?->id,
                'user_id_hashed' => $user ? GoogleTagManager::hashUserId($user->id) : null,

                // Team context
                'team_id' => $team?->id,
                'team_level' => $team?->team_level?->value ?? ($team?->level ?? null),
                'user_role' => $teamRole?->roleRelation?->name ?? ($teamRole?->role?->name ?? null),
                'user_type' => $this->getUserType($user),

                // Page information
                'page_url' => $request->fullUrl(),
                'page_title' => $this->extractPageTitle($request),
                'http_method' => $request->method(),
                'query_params' => $request->query() ?: null,

                // Request metadata
                'referer_url' => $request->header('referer'),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
                'session_id' => session()->getId(),

                // User flags
                'is_authenticated' => auth()->check(),
                'is_super_admin' => safeIsSuperAdmin(),

                // Environment
                'environment' => config('app.env', 'production'),

                // Timestamp
                'viewed_at' => now(),
            ];

            // Resolve model class dynamically
            $modelClass = config('analytics.page-view-log-model',
                \Condoedge\Utils\Models\Analytics\PageViewLog::class);
            $modelClass::create($logData);

        } catch (\Exception $e) {
            // Silently fail - don't break the application for analytics
            Log::warning('Failed to log page view', [
                'url' => $request->fullUrl(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract page title from request (route name or path).
     */
    protected function extractPageTitle(Request $request): ?string
    {
        // Try to get route name first
        if ($request->route() && $request->route()->getName()) {
            return $request->route()->getName();
        }

        // Fallback to path
        return $request->path();
    }

    /**
     * Determine user type based on user model.
     */
    protected function getUserType($user): ?string
    {
        if (!$user) {
            return null;
        }

        // Check if user has specific type methods
        if (method_exists($user, 'isScout') && $user->isScout()) {
            return 'SCOUT';
        }

        if (method_exists($user, 'isLeader') && $user->isLeader()) {
            return 'LEADER';
        }

        if (method_exists($user, 'isVolunteer') && $user->isVolunteer()) {
            return 'VOLUNTEER';
        }

        if (method_exists($user, 'isParent') && $user->isParent()) {
            return 'PARENT';
        }

        // Default to generic user type
        return 'USER';
    }
}
