<?php

if (!function_exists('readingTeamPassively')) {
    /**
     * Run reads that must not change auth state. The safe* helpers below still resolve
     * the current team role, and that resolution switches teams or logs the user out
     * when none is usable — so an observer (analytics, logging, a debug dump) has to
     * say it is only looking. Falls through untouched when kompo/auth is absent.
     */
    function readingTeamPassively(callable $callback)
    {
        if (method_exists(\Kompo\Auth\Models\User::class, 'readingTeamRolePassively')) {
            return \Kompo\Auth\Models\User::readingTeamRolePassively($callback);
        }

        return $callback();
    }
}

if (!function_exists('safeIsSuperAdmin')) {
    function safeIsSuperAdmin(): bool
    {
        return secureCall('isSuperAdmin') ?? false;
    }
}

if (!function_exists('safeCurrentTeam')) {
    function safeCurrentTeam()
    {
        return secureCall('currentTeam') ?? auth()->user()?->currentTeam;
    }
}

if (!function_exists('safeCurrentTeamId')) {
    function safeCurrentTeamId()
    {
        return safeCurrentTeam()?->getKey();
    }
}

if (!function_exists('safeCurrentTeamRole')) {
    function safeCurrentTeamRole()
    {
        return secureCall('currentTeamRole') ?? null;
    }
}

if (!function_exists('safeGetAllTeamChildrenIds')) {
    function safeGetAllTeamChildrenIds()
    {
        return secureCall('getAllChildrenRawSolution', safeCurrentTeam()) ?? [safeCurrentTeamId()];
    }
}