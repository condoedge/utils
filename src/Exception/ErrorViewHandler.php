<?php

namespace Condoedge\Utils\Exception;

use Condoedge\Utils\Kompo\HttpExceptions\GenericErrorView;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ErrorViewHandler
{
    protected $error;
    protected $request;

    public function __construct(Throwable $e, $request = null)
    {
        $this->error = $e;
        $this->request = $request;
    }

    public function handle()
    {
        $e = $this->error;
        $request = $this->request;

        if ($this->shouldRenderCustomView($e, $request)) {
            $e = $e instanceof HttpException ? $e : new HttpException(500, $e->getMessage());

            $component = $this->statusComponent($e->getStatusCode());

            try {
                $this->ensureKompoHelpersAreLoaded();

                $komponent = $component::boot([
                    'principal_message' => $this->statusCodeTitle($e->getStatusCode(), $e->getMessage()),
                    'secondary_message' => $this->statusCodeSubtitle($e->getStatusCode(), $e->getMessage()),
                ]);

                return $this->renderKompoExceptionView($komponent);
            } catch (\Throwable $ex) {
                return null;
            }
        }

        return false;
    }

    /**
     * Error views are Kompo Forms, but Kompo's element helpers (_Rows, _Html, …)
     * are `require_once`d by KompoServiceProvider::boot() — they are NOT part of
     * composer's autoload. An exception raised before that provider boots therefore
     * reaches this handler with the helpers undefined, and the error page itself
     * dies with "Call to undefined function _Rows()". Load them on demand; the
     * provider's loader is require_once-based, so re-running it after a normal
     * boot is a no-op.
     */
    protected function ensureKompoHelpersAreLoaded(): void
    {
        if (function_exists('_Rows') || !class_exists(\Kompo\KompoServiceProvider::class)) {
            return;
        }

        if (!config('kompo.helpers_dir')) {
            return;
        }

        \Kompo\KompoServiceProvider::registerHelpers();
    }

    protected function shouldRenderCustomView(Throwable $e, $request = null)
    {
        if (env('APP_DEBUG')) {
            return false;
        }

        // Already carries the response the app built (a redirect, a kompo response).
        // It is not an HttpException, so the check below would read it as a crash and
        // throw that response away. Thrown from a route action Laravel unwraps it
        // itself, but from middleware it arrives here instead.
        if ($e instanceof HttpResponseException) {
            return false;
        }

        if ($request?->expectsJson()) {
            return false;
        }

        if (
            !($e instanceof HttpException)
            && !($e instanceof \Illuminate\Auth\AuthenticationException)
            && !($e instanceof \Illuminate\Validation\ValidationException)
        ) {
            return true;
        }

        return $e instanceof HttpException
            && in_array($e->getStatusCode(), self::validErrorsToRender(), true);
    }

    protected static function validErrorsToRender()
    {
        return [403, 404, 500, 503];
    }

    protected function statusComponent($statusCode)
    {
        return config('errors-views.error_view_map')[$statusCode] 
            ?? config('errors-views.error_view_map')['default'] 
            ?? GenericErrorView::class;
    }

    protected function statusCodeTitle($statusCode, $message = '')
    {
        return match ($statusCode) {
            403 => __('errors.forbidden-title'),
            500 => __('errors.server-error-title'),
            503 => __('errors.maintenance-mode-title'),
            default => $message,
        };
    }

    protected function statusCodeSubtitle($statusCode, $message = '')
    {
        return match ($statusCode) {
            403 => __('errors.forbidden-subtitle'),
            500 => __('errors.server-error-subtitle'),
            503 => __('errors.maintenance-mode-subtitle'),
            default => '',
        };
    }


    protected function renderKompoExceptionView($komponent)
    {
        return response()->view('kompo::view', [
            'vueComponent'   => $komponent->toHtml(),
            'containerClass' => property_exists($komponent, 'containerClass') ? $komponent->containerClass : 'container',
            'metaTags'       => $komponent->getMetaTags($komponent),
            'js'             => method_exists($komponent, 'js') ? $komponent->js() : null,
            'layout'         => 'layouts.main',
            'section' => 'content',
        ]);
    }
}