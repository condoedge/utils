<?php

namespace Condoedge\Utils\Tutorials;

use Kompo\Form;

class TutorialStepBuilder extends Form
{
    public $tutorialName;

    protected $panelAttributes = ['class' => 'tutorial-step-builder-panel'];

    public function created()
    {
        $this->tutorialName = $this->prop('tutorial_name') ?? request('tutorialName') ?? 'default';

        $this->onLoad(fn($e) => $e->run('async ({ $k, field, panel, state, watch, watchAll, on, el, form }) => {
            const { StepBuilderBridge } = await import("./tutorials/step-builder/step-builder-bridge.js");
            window._stepBuilder = new StepBuilderBridge({ $k, field, panel, state, watch, watchAll, on, el, form }, window.TutorialEngine);
        }'));
    }

    public function render()
    {
        return _Rows(
            // Header
            _FlexBetween(
                _Html('Step Builder')->class('font-bold text-lg'),
                _FlexEnd(
                    _Link()->icon('eye')->class('text-gray-400 hover:text-white')->run('() => window._stepBuilder?.spectatorMode()'),
                    _Link()->icon('clipboard')->class('text-gray-400 hover:text-white')->run('() => window._stepBuilder?.copyAll()'),
                )->class('space-x-2'),
            )->class('pb-3 border-b border-white/10'),

            // Step list (filled dynamically by JS)
            _Flex(
                _Html('<span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Steps</span>'),
                _Link('+')->class('sb-btn sb-btn-sm sb-btn-green')->run('() => window._stepBuilder?.addStep()'),
            )->class('justify-between items-center pt-3 pb-1'),
            _Panel()->id('step-builder-step-list')->class('pb-3 border-b border-white/10'),

            // Step actions
            _Flex(
                _Link()->icon('arrow-up')->class('sb-btn sb-btn-ghost sb-btn-icon sb-btn-sm')->run('() => window._stepBuilder?.moveStepUp()'),
                _Link()->icon('arrow-down')->class('sb-btn sb-btn-ghost sb-btn-icon sb-btn-sm')->run('() => window._stepBuilder?.moveStepDown()'),
                _Link()->icon('copy')->class('sb-btn sb-btn-ghost sb-btn-icon sb-btn-sm')->run('() => window._stepBuilder?.duplicateStep()'),
                _Link()->icon(_Sax('trash',16))->class('sb-btn sb-btn-ghost sb-btn-icon sb-btn-sm text-red-400')->run('() => window._stepBuilder?.deleteStep()'),
            )->class('gap-1 py-2 border-b border-white/10'),

            // ─── Content Card ───
            $this->card('content', 'Content', true, [
                _Textarea('HTML Content')->name('sb-html')->class('text-sm'),
                _Flex(
                    _Toggle('overlay')->name('sb-overlay')->default(true),
                    _Toggle('showBack')->name('sb-show-back'),
                )->class('gap-4'),
            ]),

            // ─── Position Card ───
            $this->card('position', 'Position', false, [
                _Select('Position')->name('sb-position')
                    ->options(['left' => 'Left', 'right' => 'Right', 'top' => 'Top', 'bottom' => 'Bottom'])
                    ->default('left'),
                _Select('Align')->name('sb-align')
                    ->options(['left' => 'Left', 'center' => 'Center', 'right' => 'Right'])
                    ->default('center'),
                _Toggle('Chat Mode')->name('sb-chat-mode'),
                $this->selectorField('Position Target', 'sb-position-target'),
            ]),

            // ─── Cursor Card ───
            $this->card('cursor', 'Cursor', false, [
                $this->selectorField('From', 'sb-cursor-from'),
                $this->selectorField('To', 'sb-cursor-to'),
                _Flex(
                    _InputNumber('Duration')->name('sb-cursor-duration')->default(1.5)->class('w-20'),
                    _Toggle('Click')->name('sb-cursor-click'),
                    _Toggle('Loop')->name('sb-cursor-loop'),
                )->class('gap-4 items-end'),
            ]),

            // ─── Highlight Card ───
            $this->card('highlight', 'Highlight', false, [
                $this->selectorField('Elements', 'sb-highlight-elements'),
                _Select('Group Mode')->name('sb-highlight-mode')
                    ->options(['together' => 'Together', 'separate' => 'Separate']),
            ]),

            // ─── Hover Card ───
            $this->card('hover', 'Hover', false, [
                $this->selectorField('Hover Selector', 'sb-hover'),
            ]),

            // ─── Scroll Card ───
            $this->card('scroll', 'Scroll', false, [
                $this->selectorField('Scroll To', 'sb-scroll-to'),
                _Input('Scroll Inside Selector')->name('sb-scroll-inside-selector'),
                _InputNumber('Scroll Amount')->name('sb-scroll-inside-amount'),
            ]),

            // ─── Options / Branching Card ───
            $this->card('options', 'Options / Branching', false, [
                _Panel()->id('sb-options-editor'),
                _Link('+ Add Option')->class('sb-btn sb-btn-sm sb-btn-green mt-2')
                    ->run('() => window._stepBuilder?.addOption()'),
            ]),

            // ─── Actions Card ───
            $this->card('actions', 'Actions', false, [
                $this->selectorField('Silent Click', 'sb-silent-click'),
                _Input('Redirect URL')->name('sb-redirect'),
            ]),

            // ─── Advance Card ───
            $this->card('advance', 'Advance', false, [
                _Select('Mode')->name('sb-advance')
                    ->options(['click' => 'Click', 'auto' => 'Auto Next', 'afterAnimation' => 'After Animation']),
                _InputNumber('Auto Next (seconds)')->name('sb-auto-next-delay')->default(3),
            ]),

            // ─── Conditions Card ───
            $this->card('conditions', 'Conditions', false, [
                _Input('Show If (selector)')->name('sb-show-if'),
            ]),

            // ─── Output (JSON) ───
            _Collapse(
                _Html('<span class="text-xs font-semibold text-blue-400 uppercase tracking-wide">Output (JSON)</span>')
            )->expandByDefault(false)->submenu(
                _Html('<pre id="sb-output" class="bg-black/30 text-green-400 p-3 rounded-lg text-xs overflow-auto max-h-64 font-mono"></pre>'),
                _Flex(
                    _Link('Copy Step')->class('sb-btn sb-btn-sm sb-btn-ghost')->run('() => window._stepBuilder?.copyStep()'),
                    _Link('Copy All')->class('sb-btn sb-btn-sm sb-btn-ghost')->run('() => window._stepBuilder?.copyAll()'),
                )->class('gap-2 mt-2 justify-end'),
            ),
        )->attr(['data-sb' => ''])->class('p-4 space-y-1');
    }

    /**
     * Create a collapsible card section with a title.
     */
    protected function card(string $id, string $title, bool $expanded, array $fields)
    {
        return _Collapse(
            _Html('<span class="text-xs font-semibold text-blue-400 uppercase tracking-wide">' . $title . '</span>')
        )->expandByDefault($expanded)->submenu(...$fields)->class('sb-card');
    }

    /**
     * Create a selector input with a Pick button.
     */
    protected function selectorField(string $label, string $name)
    {
        return _Flex(
            _Input($label)->name($name)->class('flex-1'),
            _Link()->icon('crosshair')->class('sb-btn sb-btn-ghost sb-btn-icon sb-btn-sm')
                ->run('() => window._stepBuilder?.pickElementFor("' . $name . '")'),
        )->class('gap-2 items-end');
    }
}
