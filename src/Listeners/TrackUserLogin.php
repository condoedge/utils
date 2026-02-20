<?php

namespace Condoedge\Utils\Listeners;

use Condoedge\Utils\Services\Analytics\GoogleTagManager;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Log;

class TrackUserLogin
{
    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (!GoogleTagManager::isEnabled()) {
            return;
        }

        try {
            $user = $event->user;

            // Store login time for session duration calculation on logout
            session()->put('login_time', now());

            $eventData = [
                'app_user_id' => GoogleTagManager::hashUserId($user->id),
                'userEmail' => $user->email,
                'userName' => $user->name,
            ];

            // Add role if available
            if (method_exists($user, 'getRoleNames')) {
                $eventData['userRole'] = $user->getRoleNames()->first() ?? 'user';
            }

            // Add team context if available
            $team = safeCurrentTeam();
            if ($team) {
                $eventData['teamId'] = $team->id;
                $eventData['teamLevel'] = $team->team_level->value ?? ($team->level ?? 'unknown');
            }

            // Add login method (web, api, remember)
            $eventData['loginMethod'] = $event->guard ?? 'web';
            $eventData['rememberMe'] = request()->filled('remember');

            // Add metadata
            $eventData['timestamp'] = now()->toIso8601String();
            $eventData['ipAddress'] = request()->ip();
            $eventData['userAgent'] = request()->userAgent();

            // Store in session to be pushed on next page load
            session()->put('gtm_login_event', $eventData);

            Log::info('GA4: User login tracked', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('GA4: Failed to track login', [
                'error' => $e->getMessage(),
                'user_id' => $event->user->id ?? null,
            ]);
        }
    }
}
