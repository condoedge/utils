<?php

namespace Condoedge\Utils\Kompo\Plugins;

use Condoedge\Utils\Facades\UserModel;
use Condoedge\Utils\Kompo\Plugins\Base\ComponentPlugin;
use Condoedge\Utils\Models\Traits\HasUserSettings;
use Illuminate\Support\Str;

class HasIntroAnimation extends ComponentPlugin
{
    protected $component;

    public function onBoot()
    {
        // Nothing
    }

    public function js()
    {
        $skipIntro = $this->componentHasMethod('skipIntroAnimation') ? $this->callComponentMethod('skipIntroAnimation') : false;
        $prefixForSpecificUser = $this->componentHasMethod('prefixForSpecificUser') ? $this->callComponentMethod('prefixForSpecificUser') : '';

        $componentName = Str::slug(camelToSnake(class_basename($this->component)));
        $filePath = resource_path("views/scripts/intro-{$prefixForSpecificUser}{$componentName}.js");

        if (!file_exists($filePath)) {
            return;
        }

        if (!in_array(HasUserSettings::class, class_uses(UserModel::getClass()))) {
            \Log::error('The component must implement HasUserSettings trait to use HasIntroAnimation plugin. If you are not using it you can disable the plugin using `excludePlugins` method');
            return;
        }

        if (!$skipIntro && !componentIntroViewed($this->component)) {
            auth()->user()->saveSetting(componentIntroViewedKey($this->component), true);

        	$introJs = $this->replaceContentWithVariables($this->translateContent(file_get_contents($filePath)));

            return $introJs;
        }

        return '';
    }

    public function translateContent($content)
    {
        $matches = [];
        preg_match_all('/"(.*?)"/', $content, $matches);

        foreach ($matches[1] as $match) {
            $content = str_replace("\"{$match}\"", '"'. __($match) . '"', $content);
        }

        return $content;
    }

    public function replaceContentWithVariables($content)
    {
        $variables = $this->variables();

        foreach ($variables as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
        }

        return $content;
    }

    public function variables()
    {
        return [
            'user_first_name' => explode(' ', auth()->user()?->name ?: '')[0] ?: '',
            'user_last_name' => explode(' ', auth()->user()?->name ?: '')[1] ?: '',
            'user_name' => auth()->user()?->name ?: '',
        ];
    }
}
