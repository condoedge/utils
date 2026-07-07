<?php

if(!function_exists('balanceLockedMessage')) {
    function balanceLockedMessage($date)
    {
        return __('finance-balance-locked', ['date' => $date]); 
    }
}

if(!function_exists('throwValidationError')) {
    function throwValidationError($key, $message)
    {
        throw \Illuminate\Validation\ValidationException::withMessages([
            $key => [__($message)],
        ]);
    }
}

if(!function_exists('throwValidationConfirmation')) {
    function throwValidationConfirmation($message)
    {
        abort(449, $message);
    }
}

if(!function_exists('secureCallCb')) {
    /**
     * Run a callback and swallow any Throwable, returning $default instead. For best-effort reads
     * where a failure must not break the caller (e.g. a recipient whose getEmail() throws).
     *
     * @template T
     * @param callable():T $callback
     * @param T $default
     * @return T
     */
    function secureCallCb(callable $callback, $default = null)
    {
        try {
            return $callback();
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
