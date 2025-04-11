<?php

namespace Condoedge\Utils\Kompo\Elements;

use Kompo\Html;

class Text extends Html
{
	public $vueComponent = 'Text';

	public function initialize($label)
	{
		parent::initialize($label);

        // Default config
        $this->config([
            'content' => str_replace("\r\n", "<br>", $label),
            'showMoreText' => __('kompo.show-more'),
            'showLessText' => __('kompo.show-less'),
            'buttonClass' => '',
            'buttonStyle' => '',
        ]);
	}

    /** TEXTS */

    /**
     * Set the text to display.
     * @param  string $content The text to display.
     * @return self
     */
    public function content($content = '')
    {
        return $this->config([
            'content' => $content
        ]);
    }

    /**
     * Set the "show more" text.
     * @param  string $showMoreText The text to show in the button when the text is collapsed.
     * @return self
     */
    public function showMoreText($showMoreText)
    {
        return $this->config([
            'showMoreText' => $showMoreText
        ]);
    }

    /**
     * Set the "show less" text.
     * @param  string $showLessText The text to show in the button when the text is expanded.
     * @return self
     */
    public function showLessText($showLessText)
    {
        return $this->config([
            'showLessText' => $showLessText
        ]);
    }

    /* LIMITS TEXT */
    /**
     * Set the maximum number of lines to display when text is collapsed. Only works if text has \n characters.
     * @param  int $maxLines The maximum number of lines to display.
     * @return self
     */
    public function maxLines($maxLines)
    {
        return $this->config([
            'maxLines' => $maxLines
        ]);
    }

    /**
     * Set the maximum number of characters to display when text is collapsed.
     * @param  int $maxChars The maximum number of characters to display.
     * @return self
     */
    public function maxChars($maxChars)
    {
        return $this->config([
            'maxChars' => $maxChars
        ]);
    }

    /* STYLES */

    /**
     * Set the "show more" button styles.
     * @param  string $buttonStyle The style to add to the button.
     * @return self
     */
    public function buttonStyle($buttonStyle)
    {
        return $this->config([
            'buttonStyle' => $buttonStyle
        ]);
    }

    /**
     * Set the "show more" button classes.
     * @param  string $buttonClass The classes to add to the button.
     * @return self
     */
    public function buttonClass($buttonClass)
    {
        return $this->config([
            'buttonClass' => $buttonClass
        ]);
    }
}
