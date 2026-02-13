<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Input;

class SignaturePad extends Input
{
    public $vueComponent = 'SignaturePad';

    public function initialize($label)
    {
        parent::initialize($label);

        // Default config
        $this->config([
            'penColor' => '#000000',
            'penWidth' => 2,
            'canvasWidth' => 500,
            'canvasHeight' => 200,
            'backgroundColor' => '#ffffff',
            'clearButtonText' => __('Effacer'),
            'clearButtonClass' => 'px-4 py-2 bg-gray-200 hover:bg-gray-300 rounded text-sm',
        ]);
    }

    /**
     * Set the pen color for the signature.
     * @param  string $color Hex color code.
     * @return self
     */
    public function penColor($color)
    {
        return $this->config(['penColor' => $color]);
    }

    /**
     * Set the pen width (line thickness).
     * @param  int $width Width in pixels.
     * @return self
     */
    public function penWidth($width)
    {
        return $this->config(['penWidth' => $width]);
    }

    /**
     * Set the canvas dimensions.
     * @param  int $width Canvas width in pixels.
     * @param  int $height Canvas height in pixels.
     * @return self
     */
    public function canvasSize($width, $height)
    {
        return $this->config([
            'canvasWidth' => $width,
            'canvasHeight' => $height
        ]);
    }

    /**
     * Set the background color of the canvas.
     * @param  string $color Hex color code.
     * @return self
     */
    public function backgroundColor($color)
    {
        return $this->config(['backgroundColor' => $color]);
    }

    /**
     * Set the text for the clear button.
     * @param  string $text Button text.
     * @return self
     */
    public function clearButtonText($text)
    {
        return $this->config(['clearButtonText' => $text]);
    }

    /**
     * Set custom classes for the clear button.
     * @param  string $class CSS classes.
     * @return self
     */
    public function clearButtonClass($class)
    {
        return $this->config(['clearButtonClass' => $class]);
    }
}
