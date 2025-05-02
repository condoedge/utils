<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;

class FormCanHaveTableWithFields extends ComponentPlugin
{
    public function onCreatedDisplay()
    {
        $timeId = $this->setTimeId();

        $this->component->elements = array_merge($this->component->elements, [
            _Hidden()->name('session_time_id', false)->value($timeId),
        ]);
    }

    public function onBoot()
    {
        $this->setTimeId();
    }

    public function onCreatedAction()
    {
        $timeId = $this->setTimeId();

        request()->offsetUnset('session_time_id');
        request()->merge(session('session-form-values-' . $timeId, []));
    }

    protected function setTimeId()
    {
        $timeId = $this->component->prop('session_time_id') ?? request('session_time_id') ?? time();
        $this->setComponentProperty('timeId', $timeId);

        $this->component->store([
            'session_time_id' => $timeId,
        ]);

        request()->merge([
            'session_time_id' => $this->component->prop('session_time_id') ?? request('session_time_id') ?? time(),
        ]);

        return $timeId;
    }
}