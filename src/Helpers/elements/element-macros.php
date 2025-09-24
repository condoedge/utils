<?php

use Illuminate\Support\Facades\Route;
use Kompo\Elements\Element;
use Kompo\Elements\Field;
use Kompo\Interactions\Action;
use Kompo\Rows;
use Kompo\Elements\Layout;
use Kompo\Img;
use Kompo\Interactions\Interaction;

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

Kompo\Elements\Trigger::macro('panelLoading', function ($id) {
 	Interaction::appendToWithAction($this, new Action($this, 'run', ['() => {
 		const panel = document.getElementById("' . $id . '");
 
 		if(panel) {
 			panel.innerHTML = "<div></div>" + panel.innerHTML;
 			panel.classList.add("vlPanelLoading");
 		}
 	}']));
 
 	return $this;
 });

Rows::macro('copyImageToClipboard', function ($text, $alertMessage = 'campaign.copied-to-clipboard') {
    return $this->onClick(fn($e) => $e->run('() => {copyImageToClipboard(`'. $text .'`)}') &&
        $e->alert($alertMessage)
    );
});

\Kompo\Toggle::macro('fixToggleId', function($divId, $internalToggleId, $patchToggleId = ''){
    $js = '() => {
        if($("#'. $internalToggleId .'")[0].checked) {
            $("#'. $divId .'").removeClass("hidden")
        } else {
            $("#'. $divId .'").addClass("hidden")
        }
        
        ' . ($patchToggleId ? '$("#'.$patchToggleId.'").click();' : '') .'
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
    $routeName = Route::currentRouteName();

    $itemName = $routeName . '_'. $this->name;

    return $this
        ->value(session($itemName) ?? $this->value)
        ->onChange(fn($e) => $e
            ->post(route('hold-field-in-session', ['field_name' => $this->name, 'item_name' => $itemName]))
        );
});


Kompo\Elements\Layout::macro('applyToAllElements', function ($callback, $exclude = []) {
	$this->elements = collect($this->elements)->map(function ($el, $i) use ($callback, $exclude) {
		if (!in_array($i, $exclude)) {
			return $callback($el);
		}

		return $el;
	})->all();

	return $this;
});

Kompo\Elements\Layout::macro('stopPropagation', function () {
	return $this->attr([
		'onclick' => 'event.stopPropagation()',
	]);
});

Element::macro('decorate', function ($callback) {
    return $callback($this);
});

Element::macro('getFieldNames', function () {
    $fieldsNames = [];
    $elements = [$this];

    while (count($elements)) {
        $currentElement = array_shift($elements);
        if ($currentElement instanceof Layout) {
            $elements = array_merge($elements, $currentElement->elements);
        } elseif ($currentElement instanceof Field) {
            array_push($fieldsNames, $currentElement->name);
        }
    }

    return $fieldsNames;
});

Element::macro('findElementById', function ($id) {
    $elements = [$this, ...$this->getConfigElements()];

    while (count($elements)) {
        $currentElement = array_shift($elements);
        if ($currentElement->id == $id) {
            return $currentElement;
        }

        if ($currentElement instanceof Layout) {
            $elements = array_merge($elements, $currentElement->elements);
        }
    }

    return null;
});

Element::macro('getConfigElements', function () {
    $elements = [];

    foreach ($this->config as $configItem) {
        if ($configItem instanceof Element) {
            $elements[] = $configItem;
        }
    }

    return $elements;
});

Field::macro('shareToParentForm', function() {
    return $this->selfPost('pluginMethod', [
        'method' => 'setInputValue',
    ]); 
});

Img::macro('directSrc', function($src) {
    $this->src = $src;
    
    return $this;
});

Element::macro('ajaxPayload', function($payload = []) {
    return $this->config(['ajaxPayload' => $payload]);
});

Layout::macro('fixChildrenSpinners', function () {
    $id = $this->id ?? class_basename($this) . uniqid();

    return $this->id($id)->onClick->run('() => {
        $("#'.$id.'").find(".icon-spinner").addClass("hidden"); //remove loading spinner when the click is in the parent element
    }');
});