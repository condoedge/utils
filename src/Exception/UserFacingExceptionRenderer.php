<?php

namespace Condoedge\Utils\Exception;

use Kompo\Core\KompoAction;

/**
 * Renders a UserFacingException as a Kompo error alert. Register it as a `renderable`
 * before the generic Throwable one; a null return leaves the exception to the next renderer.
 */
class UserFacingExceptionRenderer
{
    // Only actions whose JS reads a dynamic response; browse/refresh would take the alert as data.
    public const ALERTABLE_KOMPO_ACTIONS = ['handle-submit', 'eloquent-submit', 'self-method', 'load-komponent', 'delete-item'];

    public function __invoke(UserFacingException $e, $request = null)
    {
        $request = $request ?: request();

        if (!in_array($request->header(KompoAction::$key), self::ALERTABLE_KOMPO_ACTIONS, true)) {
            return null;
        }

        return response()->kompoAlert('', options: [
            'alertClass' => 'vlAlertError',
            'message' => $e->userMessage(),
        ]);
    }
}
