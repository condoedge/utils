<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Facades\UserModel;
use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;
use Condoedge\Utils\Models\Traits\HasUserSettings;
use Illuminate\Support\Str;

class HasTutorial extends ComponentPlugin
{
    public function onBoot()
    {
        //
    }

    public function js()
    {
        if ($this->shouldSkipEntirely()) {
            return '';
        }

        $slug = $this->getComponentSlug();

        if (!file_exists(resource_path("tutorials/{$slug}.json"))) {
            return '
                document.getElementById("tutorial-replay-btn")?.remove();
            ';
        }

        $js = "window._currentTutorial = '{$slug}';";

        if ($this->shouldAutoStart($slug)) {
            $this->markAsViewed($slug);
            $js .= "window.TutorialEngine.start('{$slug}');";
            // Don't show replay button — tutorial is auto-starting
        } else {
            // User already saw it — show the replay button
            $js .= "document.getElementById('tutorial-replay-btn')?.style.removeProperty('display');";
        }

        return $js;
    }

    protected function shouldSkipEntirely(): bool
    {
        return $this->componentHasMethod('skipIntroAnimation')
            && $this->callComponentMethod('skipIntroAnimation');
    }

    protected function shouldAutoStart(string $slug): bool
    {
        if ($this->componentHasMethod('alwaysShowIntro') && $this->callComponentMethod('alwaysShowIntro')) {
            return true;
        }

        return !$this->alreadyViewed($slug);
    }

    protected function alreadyViewed(string $slug): bool
    {
        if (!in_array(HasUserSettings::class, class_uses(UserModel::getClass()))) {
            return true;
        }

        return (bool) auth()->user()?->getSettingValue($this->viewedKey($slug));
    }

    protected function markAsViewed(string $slug): void
    {
        auth()->user()?->saveSetting($this->viewedKey($slug), true);
    }

    protected function viewedKey(string $slug): string
    {
        return $slug . '_tutorial_viewed';
    }

    protected function getComponentSlug(): string
    {
        return Str::slug(camelToSnake(class_basename($this->component)));
    }
}
