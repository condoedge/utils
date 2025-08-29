<?php

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

if (!function_exists('safeGetAllTeamChildrenIds')) {
    function safeGetAllTeamChildrenIds()
    {
        return secureCall('getAllChildrenRawSolution', safeCurrentTeam()) ?? [safeCurrentTeamId()];
    }
}