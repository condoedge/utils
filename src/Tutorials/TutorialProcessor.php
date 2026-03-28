<?php

namespace Condoedge\Utils\Tutorials;

class TutorialProcessor
{
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

    protected function evaluateShowIf(array $step): bool
    {
        if (!isset($step['showIf'])) return true;

        return app(TutorialConditionEvaluator::class)->evaluate($step['showIf']);
    }

    protected function replaceVariables(array $step): array
    {
        $user = auth()->user();
        if (!$user) return $step;

        $nameParts = explode(' ', $user->name ?? '', 2);
        $variables = [
            'user_first_name' => $nameParts[0] ?? '',
            'user_last_name' => $nameParts[1] ?? '',
            'user_name' => $user->name ?? '',
        ];

        array_walk_recursive($step, function (&$value) use ($variables) {
            if (is_string($value)) {
                foreach ($variables as $key => $replacement) {
                    $value = str_replace("{{{$key}}}", $replacement, $value);
                }
            }
        });

        return $step;
    }
}
