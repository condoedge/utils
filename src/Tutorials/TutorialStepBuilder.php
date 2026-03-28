<?php

namespace Condoedge\Utils\Tutorials;

use Kompo\Form;

class TutorialStepBuilder extends Form
{
    public $tutorialName;

    public function created()
    {
        $this->tutorialName = $this->prop('tutorial_name') ?? request('tutorialName');

        $this->onLoad(fn($e) => $e->run('async ({ $k, field, panel, state, watch, watchAll, on, el }) => {
            const { StepBuilderBridge } = await import("./tutorials/step-builder/step-builder-bridge.js");
            window._stepBuilder = new StepBuilderBridge({ $k, field, panel, state, watch, watchAll, on, el }, window.TutorialEngine);
        }'));
    }

    public function render()
    {
        return _Rows(
            // Header with actions
            _FlexBetween(
                _Html('Tutorial Step Builder')->class('font-bold text-lg'),
                _FlexEnd(
                    _Link()->icon('eye')->run('() => window._stepBuilder?.spectatorMode()'),
                    _Link()->icon('clipboard')->run('() => window._stepBuilder?.copyAll()'),
                )->class('space-x-2'),
            ),

            // Step list (filled by JS)
            _Panel()->id('step-builder-step-list'),

            // Base Config
            _Collapse('Base Config')->expandByDefault()->submenu(
                _Textarea('HTML Content')->name('sb-html'),
                _Toggle('Overlay')->name('sb-overlay'),
                _Select('Position')->name('sb-position')
                    ->options(['left' => 'Left', 'right' => 'Right', 'top' => 'Top', 'bottom' => 'Bottom']),
                _Select('Align')->name('sb-align')
                    ->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right']),
                _Select('Advance')->name('sb-advance')
                    ->options(['click' => 'Click', 'auto' => 'Auto Next', 'afterAnimation' => 'After Animation']),
                _Toggle('Silent Click')->name('sb-silent-click'),
            ),

            // Cursor Animation
            _Collapse('Cursor Animation')->submenu(
                _FlexBetween(
                    _Input('From')->name('sb-cursor-from')->class('flex-1'),
                    _Link()->icon('crosshair')->run('() => window._stepBuilder?.pickElementFor("sb-cursor-from")'),
                ),
                _FlexBetween(
                    _Input('To')->name('sb-cursor-to')->class('flex-1'),
                    _Link()->icon('crosshair')->run('() => window._stepBuilder?.pickElementFor("sb-cursor-to")'),
                ),
                _InputNumber('Duration')->name('sb-cursor-duration'),
                _Toggle('Click at end')->name('sb-cursor-click'),
                _Toggle('Loop')->name('sb-cursor-loop'),
                _Panel()->id('sb-bezier-editor'),
            ),

            // Highlight
            _Collapse('Highlight')->submenu(
                _FlexBetween(
                    _Input('Element(s)')->name('sb-highlight-elements'),
                    _Link()->icon('crosshair')->run('() => window._stepBuilder?.pickElementFor("sb-highlight-elements")'),
                ),
                _Select('Group Mode')->name('sb-highlight-mode')
                    ->options(['together' => 'Together', 'separate' => 'Separate', 'custom' => 'Custom']),
            ),

            // Hover
            _Collapse('Hover')->submenu(
                _FlexBetween(
                    _Input('Hover Selector')->name('sb-hover'),
                    _Link()->icon('crosshair')->run('() => window._stepBuilder?.pickElementFor("sb-hover")'),
                ),
            ),

            // Scroll
            _Collapse('Scroll')->submenu(
                _FlexBetween(
                    _Input('Scroll To')->name('sb-scroll-to'),
                    _Link()->icon('crosshair')->run('() => window._stepBuilder?.pickElementFor("sb-scroll-to")'),
                ),
                _Input('Scroll Inside Selector')->name('sb-scroll-inside-selector'),
                _InputNumber('Scroll Amount')->name('sb-scroll-inside-amount'),
            ),

            // Options / Branching
            _Collapse('Options / Branching')->submenu(
                _Panel()->id('sb-options-editor'),
                _Link('+ Add Option')->run('() => window._stepBuilder?.addOption()'),
            ),

            // Output (JSON)
            _Collapse('Output (JSON)')->submenu(
                _Html('<pre id="sb-output" class="bg-gray-900 text-green-400 p-4 rounded text-sm overflow-auto max-h-64"></pre>'),
                _FlexEnd(
                    _Link('Copy Step')->run('() => window._stepBuilder?.copyStep()'),
                    _Link('Copy All')->run('() => window._stepBuilder?.copyAll()'),
                )->class('space-x-2'),
            ),
        );
    }
}
