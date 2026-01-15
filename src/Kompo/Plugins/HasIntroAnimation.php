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
        $prefixForSpecificUser = $this->getPrefixFile();
        
        $componentName = Str::slug(camelToSnake(class_basename($this->component)));
        $filePath = resource_path("views/scripts/intro-{$prefixForSpecificUser}{$componentName}.js");
        $filePath = !file_exists($filePath) && !$this->getComponentProperty('strictByUser') ? 
            resource_path("views/scripts/intro-{$componentName}.js") : $filePath; 

        if (!file_exists($filePath)) {
            return;
        }

        if (!in_array(HasUserSettings::class, class_uses(UserModel::getClass()))) {
            \Log::error('The component must implement HasUserSettings trait to use HasIntroAnimation plugin. If you are not using it you can disable the plugin using `excludePlugins` method');
            return;
        }

        if (!$skipIntro && !componentIntroViewed($this->component)) {
            auth()->user()->saveSetting(componentIntroViewedKey($this->component), true);

            $translatedContent = $this->translateContent(file_get_contents($filePath));
            $replacedContent = $this->replaceContentWithVariables($translatedContent);
        	$introJs = $this->managePhpInjections($replacedContent);

            try {
                $filesIncluded = array_diff(
                    scandir(resource_path('views/scripts/animation_dependencies')), 
                    ['.', '..']
                );

                foreach ($filesIncluded as $file) {
                    $introJs = file_get_contents(resource_path("views/scripts/animation_dependencies/{$file}")) . "\n" . $introJs;
                }
            } catch (\Exception $e) {
                // No dependencies found, continue
            }

            return $introJs;
        }

        return '';
    }

    protected function getPrefixFile()
    {
        $prefixForSpecificUser = $this->componentHasMethod('prefixForSpecificUser') ? $this->callComponentMethod('prefixForSpecificUser') : '';

        if (!$prefixForSpecificUser && app()->has('prefixIntroJsByUserType')) {
            $prefixForSpecificUser = app('prefixIntroJsByUserType')();
        }

        $prefixForSpecificUser = $prefixForSpecificUser ? ($prefixForSpecificUser . '-') : '';

        return $prefixForSpecificUser;
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
            'user_last_name' => explode(' ', auth()->user()?->name ?: '')[1] ?? '',
            'user_name' => auth()->user()?->name ?: '',
        ];
    }

    public function managePhpInjections($content)
    {
        // Handle condition blocks first (they remove entire blocks)
        $content = $this->processConditionalBlocks($content);

        // Clean up comma issues after block removal
        $content = $this->cleanupCommas($content);
    
        // Then handle other simple injections
        $content = $this->processSimpleInjections($content);
        
        return $content;
    }

    private function processConditionalBlocks($content)
    {
        // Match complete blocks that contain @condition comments
        $pattern = '/\{[^{}]*?\/\*\s*@condition\s*:\s*([^*]*?)\*\/[^{}]*?\}/s';
        
        return preg_replace_callback($pattern, function($matches) {
            $condition = trim($matches[1]);
            $fullBlock = $matches[0];
            
            // Evaluate condition
            $shouldKeep = $this->evaluateCondition($condition);
            
            if ($shouldKeep) {
                // Remove only the comment, keep the block
                return preg_replace('/\/\*\s*@condition\s*:[^*]*?\*\//', '', $fullBlock);
            } else {
                // Remove entire block
                return '';
            }
        }, $content);
    }

    private function processSimpleInjections($content)
    {
        // Handle other injection types that don't remove blocks
        $pattern = '/\/\*\s*@([a-zA-Z]+)\s*:\s*([^*]*?)\*\//';
        
        return preg_replace_callback($pattern, function($matches) {
            $type = $matches[1];
            $code = trim($matches[2]);
            $fullMatch = $matches[0];
            
            $method = 'manage' . ucfirst($type);
            
            if ($type === 'condition') {
                // Already handled in processConditionalBlocks
                return $fullMatch;
            }
            
            if (method_exists($this, $method)) {
                return $this->$method($code, $fullMatch);
            }
            
            if (app()->has($method)) {
                return app()->get($method)($code, $fullMatch);
            }
            
            // Unknown injection type, remove the comment
            return '';
        }, $content);
    }

    private function evaluateCondition($condition)
    {
        try {
            $result = false;
            eval('$result = ' . $condition . ';');
            return (bool) $result;
        } catch (Exception $e) {
            \Log::warning("Error evaluating condition: {$condition}. Error: " . $e->getMessage());
            return false;
        }
    }

    private function cleanupCommas($content)
    {
        // Remove double commas
        $content = preg_replace('/,\s*,/', ',', $content);
        
        // Remove comma before closing bracket/brace
        $content = preg_replace('/,\s*(\]|\})/', '$1', $content);
        
        // Remove comma after opening bracket/brace
        $content = preg_replace('/(\[|\{)\s*,/', '$1', $content);
        
        return $content;
    }
}
