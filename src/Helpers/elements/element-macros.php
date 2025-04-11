<?php

use Kompo\Elements\Element;
use Kompo\Elements\Field;
use Kompo\Rows;

/* TABS */

/**
 * Set managment of current tab to kept it even after page reload.
 */
\Kompo\Tabs::macro('holdActualTab', function() {
    return $this->run(getPushParameterFn('tab_number', 'getActualTab("' . ($this->id ?: '') . '")', true))
            ->activeTab(request('tab_number') ?: 0);
});

/**
 * Set clicker handler that will copy the text to clipboard and show alert message.
 * @param string $text The text to copy to clipboard.
 * @param string $alertMessage The alert message to show after copying.
 * @return \Kompo\Rows The current instance of the Rows class.
*/
\Kompo\Rows::macro('copyToClipboard', function ($text, $alertMessage = 'auth.copied-to-clipboard') {
    return $this->onClick(fn($e) => $e->run('() => {navigator.clipboard?.writeText("' . $text . '")}') &&
        $e->alert($alertMessage));
});

/**
 * Set clicker handler to put a partial view in a panel and removing it after a query is done.
 * @param string $view The blade view to load.
 * @param string $divId The ID of the div to load the view into.
 * @param array $data The data to pass to the view.
 * @param callable|null $successCb The callback to execute on success.
 * @return \Kompo\Button The current instance of the Button class.
 */
\Kompo\Button::macro('loadPartialInEl', function($view, $divId, $data = [], $successCb = null){
    $viewHtml = view($view, $data)->render();

    $baseCb = fn($e) => $e->get('void-element')->inPanel($divId);
    $successCb = !$successCb ? $baseCb : fn($e) => $successCb(call_user_func($baseCb, $e));

    return $this->onSuccess($successCb)->onError(fn($e) => $e->inAlert('icon-times', 'vlAlertError') && $baseCb($e))->onClick(fn($e) => $e->run('() => {
        $("#'.$divId.'")
            .html(`' . $viewHtml . '`);
    }'));
        
});

/**
 * Set clicker handler to put a loading view in a panel and removing it after a query is done.
 * @param string $message The message to show in the loading view.
 * @param callable|null $successCb The callback to execute on success.
 * @return \Kompo\Button The current instance of the Button class.
 */
\Kompo\Button::macro('loadLoading', function($message = '', $successCb = null){
    return $this->loadPartialInEl('partials.loading', 'load-panel', [
        'text' => $message,
    ], $successCb);    
});

Rows::macro('copyImageToClipboard', function ($text, $alertMessage = 'campaign.copied-to-clipboard') {
    return $this->onClick(fn($e) => $e->run('() => {copyImageToClipboard(`'. $text .'`)}') &&
        $e->alert($alertMessage)
    );
});

\Kompo\Toggle::macro('fixToggleId', function($divId, $internalToggleId){
    $js = '() => {
        if($("#'. $internalToggleId .'")[0].checked) {
            $("#'. $divId .'").removeClass("hidden")
        } else {
            $("#'. $divId .'").addClass("hidden")
        }
    }';

    return $this->run($js);
});

\Kompo\Elements\Element::macro('disableAction', function($condition = true){
    if(!$condition) return $this;

    $this->interactions = [];

    if (property_exists($this, 'href')) {
        $this->href('javascript:void(0)');
        $this->target(null);
    }

    return $this;
});

Element::macro('when', function ($condition, $callback) {
    if ($condition) {
        $callback($this);
    }

    return $this;
});

Field::macro('holdInSession', function() {
    $routeName = \Route::currentRouteName();

    $itemName = $routeName . '_'. $this->name;

    return $this
        ->value(session($itemName) ?? $this->value)
        ->onChange(fn($e) => $e
            ->post(route('hold-field-in-session', ['field_name' => $this->name, 'item_name' => $itemName]))
        );
});
