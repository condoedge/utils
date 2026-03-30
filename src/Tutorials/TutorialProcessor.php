<?php

namespace Condoedge\Utils\Tutorials;

class TutorialProcessor
{
    protected static array $variables = [];

    /**
     * Register a custom variable for tutorial text replacement.
     * Usage in service provider: TutorialProcessor::registerVariable('org_name', fn() => auth()->user()->organization->name);
     * Usage in JSON: "Welcome to {{org_name}}"
     */
    public static function registerVariable(string $name, \Closure $resolver): void
    {
        static::$variables[$name] = $resolver;
    }

    /**
     * Register multiple variables at once.
     * Usage: TutorialProcessor::registerVariables(['org_name' => fn() => ..., 'plan' => fn() => ...]);
     */
    public static function registerVariables(array $variables): void
    {
        foreach ($variables as $name => $resolver) {
            static::registerVariable($name, $resolver);
        }
    }

    public function process(string $tutorialName): array
    {
        $path = $this->resolvePath($tutorialName);

        if (!$path || !file_exists($path)) {
            return ['steps' => []];
        }

        $data = json_decode(file_get_contents($path), true);

        $data['steps'] = collect($data['steps'])
            ->filter(fn($step) => $this->evaluateShowIf($step))
            ->map(fn($step) => $this->translateStep($step))
            ->map(fn($step) => $this->replaceVariables($step))
            ->values()
            ->all();

        $data['options'] = $this->processOptions($data['options'] ?? []);

        return $data;
    }

    protected function resolvePath(string $tutorialName): ?string
    {
        $paths = [
            resource_path("tutorials/{$tutorialName}.json"),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) return $path;
        }

        return null;
    }

    protected function translateStep(array $step): array
    {
        array_walk_recursive($step, function (&$value) {
            if (!is_string($value)) return;

            // Pure translation key (e.g., "tutorial.next")
            if (str_contains($value, '.') && !str_contains($value, ' ') && !str_contains($value, '<')) {
                $translated = __($value);
                if ($translated !== $value) {
                    $value = $translated;
                }
                return;
            }

            // HTML or mixed content — find and translate embedded keys
            // Matches strings like "tutorial.some-key" within HTML
            $value = preg_replace_callback(
                '/\b([a-z][a-z0-9_-]*\.[a-z0-9._-]+)\b/i',
                function ($matches) {
                    $key = $matches[1];
                    $translated = __($key);
                    return $translated !== $key ? $translated : $key;
                },
                $value
            );
        });

        return $step;
    }

    protected function processOptions(array $options): array
    {
        // Ensure translated labels are always present
        $defaults = [
            'nextLabel' => 'tutorial.next',
            'doneLabel' => 'tutorial.done',
        ];

        foreach ($defaults as $key => $defaultKey) {
            $raw = $options[$key] ?? $defaultKey;
            $options[$key] = __($raw);
        }

        // Translate any other string values in options
        foreach ($options as $key => &$value) {
            if (is_string($value) && !isset($defaults[$key])) {
                $translated = __($value);
                if ($translated !== $value) {
                    $value = $translated;
                }
            }
        }

        return $options;
    }

    protected function evaluateShowIf(array $step): bool
    {
        if (!isset($step['showIf'])) return true;

        return app(TutorialConditionEvaluator::class)->evaluate($step['showIf']);
    }

    protected function replaceVariables(array $step): array
    {
        $variables = $this->resolveVariables();

        array_walk_recursive($step, function (&$value) use ($variables) {
            if (is_string($value)) {
                foreach ($variables as $key => $replacement) {
                    $value = str_replace("{{{$key}}}", $replacement, $value);
                }
            }
        });

        return $step;
    }

    protected function resolveVariables(): array
    {
        $user = auth()->user();
        $nameParts = explode(' ', $user->name ?? '', 2);

        // Built-in variables
        $variables = [
            'user_first_name' => $nameParts[0] ?? '',
            'user_last_name' => $nameParts[1] ?? '',
            'user_name' => $user->name ?? '',
        ];

        // Registered custom variables
        foreach (static::$variables as $name => $resolver) {
            try {
                $variables[$name] = (string) $resolver();
            } catch (\Throwable $e) {
                $variables[$name] = '';
            }
        }

        return $variables;
    }
}
