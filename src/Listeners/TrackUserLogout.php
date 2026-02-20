<?php

namespace Condoedge\Utils\Listeners;

use Condoedge\Utils\Services\Analytics\GoogleTagManager;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class TrackUserLogout
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        if (!GoogleTagManager::isEnabled()) {
            return;
        }

        try {
            $user = $event->user;

            if (!$user) {
                return;
            }

            $eventData = [
                'app_user_id' => GoogleTagManager::hashUserId($user->id),
                'sessionDuration' => session()->get('login_time')
                    ? now()->diffInMinutes(session()->get('login_time'))
                    : null,
                'timestamp' => now()->toIso8601String(),
            ];

            // Store in session (will be pushed before session is destroyed)
            session()->put('gtm_logout_event', $eventData);

            Log::info('GA4: User logout tracked', ['user_id' => $user->id]);
        } catch (\Exception $e) {
            Log::error('GA4: Failed to track logout', [
                'error' => $e->getMessage(),
                'user_id' => $event->user->id ?? null,
            ]);
        }
    }
}
