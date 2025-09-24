<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Input;

class PasswordInput extends Input
{
    public $vueComponent = 'PasswordInput'; // Resolves to VlPasswordInput

    protected function initialize($label)
    {
        parent::initialize($label);

        $this->config([
            'showToggle' => true,
            'togglePosition' => 'right', // left|right
            'toggleIcon' => null, // Custom icons
            'toggleIconShow' => null, // Will use SVG eye icon
            'toggleIconHide' => null, // Will use SVG eye-slash icon
            'allowToggle' => true,
            'strengthIndicator' => false,
        ]);
    }

    public function showToggle(bool $show = true)
    {
        return $this->config(['showToggle' => $show]);
    }

    public function hideToggle()
    {
        return $this->showToggle(false);
    }

    public function togglePosition(string $position)
    {
        return $this->config(['togglePosition' => $position]);
    }

    public function toggleIcon(string $showIcon = null, string $hideIcon = null)
    {
        $config = [];
        if ($showIcon !== null) {
            $config['toggleIconShow'] = $showIcon;
        }
        if ($hideIcon !== null) {
            $config['toggleIconHide'] = $hideIcon;
        }
        return $this->config($config);
    }

    public function allowToggle(bool $allow = true)
    {
        return $this->config(['allowToggle' => $allow]);
    }

    public function strengthIndicator(bool $show = true)
    {
        return $this->config(['strengthIndicator' => $show]);
    }
}