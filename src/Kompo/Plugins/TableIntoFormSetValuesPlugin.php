<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;

class TableIntoFormSetValuesPlugin extends ComponentPlugin
{
    public function managableMethods()
    {
        return [
            'setInputValue'
        ];
    }

    public function setInputValue()
    {
        $timeId = $this->getSessionTimeId();

        $data = request()->except('method');

        $sessionKey = 'session-form-values-' . $timeId;

        $currentSession = session()->get($sessionKey, []);

        session()->put($sessionKey, array_merge($currentSession, $data));
    }

    public function onBoot()
    {
        $this->getSessionTimeId();
    }

    public function onCreatedDisplay()
    {
        $this->getSessionTimeId();
    }

    public function onCreatedAction()
    {
        $this->getSessionTimeId();
    }

    protected function getSessionTimeId()
    {
        $timeId = $this->component->prop('session_time_id') ?? request('session_time_id');

        $this->setComponentProperty('timeId', $timeId);

        $this->component->store([
            'session_time_id' => $timeId,
        ]);

        return $timeId;
    }
}