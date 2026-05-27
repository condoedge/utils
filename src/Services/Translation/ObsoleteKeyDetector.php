<?php

namespace Condoedge\Utils\Services\Translation;

/**
 * Finds keys that live in a locale JSON file but are never referenced from
 * PHP/Blade/Vue/JS/TS source. Runs in two passes:
 *
 *   1. compare each locale's JSON against the list of keys the
 *      {@see KeyCodeScanner} extracted, dropping anything that matches a
 *      dynamic prefix (e.g. `<ns>.` when code does __("<ns>." . $var));
 *   2. for the remaining candidates, grep the whole codebase for the key as
 *      a quoted string — if it appears anywhere, rescue it from the report.
 */
class ObsoleteKeyDetector
{
    public function __construct(
        private readonly LocaleFilesRepository $localeFiles,
        private readonly KeyCodeScanner $codeScanner,
    ) {}

    /**
     * @param string[] $usedKeys  keys extracted by {@see KeyCodeScanner::scan()}
     * @param string[] $locales
     * @return array{
     *   report: array<string, string[]>,
     *   skippedByDynamicPrefix: int,
     *   rescued: int,
     *   prefixes: string[]
     * }
     */
    public function detect(array $usedKeys, array $locales): array
    {
        $usedSet                = array_flip($usedKeys);
        $candidatesPerLocale    = [];
        $allCandidates          = [];
        $skippedByDynamicPrefix = 0;

        foreach ($locales as $locale) {
            $data       = $this->localeFiles->load($locale);
            $candidates = [];
            foreach (array_keys($data) as $key) {
                if (isset($usedSet[$key])) {
                    continue;
                }
                if ($this->codeScanner->dynamicDetector()->matches($key)) {
                    $skippedByDynamicPrefix++;
                    continue;
                }
                $candidates[]         = $key;
                $allCandidates[$key]  = true;
            }
            $candidatesPerLocale[$locale] = $candidates;
        }

        $reallyObsolete = $this->filterByLiteralGrep(array_keys($allCandidates));

        $report = [];
        foreach ($candidatesPerLocale as $locale => $candidates) {
            $report[$locale] = array_values(array_intersect($candidates, $reallyObsolete));
        }

        return [
            'report'                 => $report,
            'skippedByDynamicPrefix' => $skippedByDynamicPrefix,
            'rescued'                => count($allCandidates) - count($reallyObsolete),
            'prefixes'               => $this->codeScanner->dynamicDetector()->prefixes(),
        ];
    }

    /**
     * Slurp every scanned source file once, then check every candidate for a
     * quoted-literal occurrence. Same file set as {@see KeyCodeScanner::scan()}.
     *
     * @param string[] $candidates
     * @return string[]
     */
    private function filterByLiteralGrep(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        $haystack = '';
        foreach ($this->codeScanner->buildCodeFilesFinder($this->localeFiles->linkedPackages()) as $file) {
            $haystack .= file_get_contents($file->getRealPath()) . "\n";
        }

        $reallyObsolete = [];
        foreach ($candidates as $key) {
            if (
                !str_contains($haystack, "'" . $key . "'")
                && !str_contains($haystack, '"' . $key . '"')
            ) {
                $reallyObsolete[] = $key;
            }
        }
        return $reallyObsolete;
    }
}
