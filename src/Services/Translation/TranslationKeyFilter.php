<?php

namespace Condoedge\Utils\Services\Translation;

class TranslationKeyFilter
{
    private const EXCLUSION_RULES = [
        'contexts' => [
            'config(', 'env(', '->get(', 'Config::', 'config/', 'config.',
            'rules(', 'validator(', 'validate(', 'Validator::', 'function_exists(',
            'class_exists(', 'method_exists(', 'interface_exists(', 'trait_exists(',
            'defined(', 'constant(', 'storage_path(', 'resource_path(', '_Sax(', 
            'icon(', 'svg(', 'path(', 'url(', 'route(', 'asset(',
            'Auth::', 'auth(', 'Gate::', 'gate(', 'DB::', 'db(',
            'Session::', 'session(', 'Cache::', 'cache(', 'Log::', 'log(',
            'Mail::', 'mail(', 'View::', 'view(', 'Event::', 'event(',
            'Queue::', 'queue(', 'Storage::', 'storage(', 'Response::', 'response(',
            'Request::', 'request(', 'Route::', 'route(', 'URL::', 'url(',
            'Redirect::', 'redirect(', 'Schema::', 'schema(', 'Artisan::', 'artisan(',
            'Broadcast::', 'broadcast(', 'Password::', 'password(', 'Notification::', 'notification(',
        ],
        'config_patterns' => [
            'app.', 'database.', 'cache.', 'queue.', 'mail.', 'session.',
            'filesystems.', 'broadcasting.', 'auth.', 'services.', 'logging.',
            'view.', 'sanctum.', 'cors.',
        ],
        'validation_rules' => [
            'required', 'nullable', 'string', 'integer', 'numeric', 'boolean',
            'email', 'url', 'date', 'datetime', 'image', 'file', 'unique', 'exists',
            'min', 'max', 'between', 'size', 'regex', 'confirmed', 'sometimes'
        ],
        'validation_patterns' => [
            'min:', 'max:', 'between:', 'size:', 'digits:', 'mimes:', 'dimensions:',
            'regex:', 'unique:', 'exists:', 'required_if:', 'in:', 'not_in:'
        ],
        'db_fields' => [
            'id', 'created_at', 'updated_at', 'deleted_at', 'remember_token',
            'password', 'uuid', 'slug', 'status', 'type', 'active', 'visible'
        ],
        'common_words' => [
            'yes', 'no', 'ok', 'true', 'false', 'null', 'get', 'set', 'add',
            'remove', 'delete', 'create', 'update', 'class', 'id', 'name', 'type'
        ],
        'code_patterns' => [
            '/^[A-Z_]+$/',           // CONSTANTS
            '/^[a-z]+[A-Z]/',        // camelCase
            '/^\$/',                 // variables
            '/^(function|class|return)/',
            '/^validation.values[^,]*$/', // validation.values.something
            '/^validation.custom[^,]*$/', // validation.custom.something
            '/^[a-zA-Z ]*$/' // only letters and spaces
        ]
    ];

    private const FILE_LIKE_EXTENSIONS = [
        'txt', 'pdf', 'xlsx', 'xls', 'csv',
        'doc', 'docx', 'ppt', 'pptx',
        'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp',
        'zip', 'rar', 'tar', 'gz', '7z',
        'mp3', 'mp4', 'mov', 'avi', 'mkv', 'webm',
        'log', 'md', 'json', 'xml', 'html', 'htm', 'css', 'js', 'ts',
        'yml', 'yaml', 'ini'
    ];

    public function isValidKey(string $key, string $context = ''): bool
    {
        // Basic sanity checks
        if ($key === '' || strlen($key) < 2 || strlen($key) > 100) return false;
        if (strpos($key, '$') !== false) return false;
        if (filter_var($key, FILTER_VALIDATE_URL) || filter_var($key, FILTER_VALIDATE_EMAIL)) return false;

        // Exclude numeric and float-like strings (e.g., "0.00", "123", "45.67")
        if (is_numeric($key) || preg_match('/^\d+(\.\d+)?$/', $key)) return false;

        // Exclude CSS selectors and path-like strings
        if (str_starts_with($key, '.') || str_starts_with($key, '#')) return false;
        if (strpos($key, '/') !== false || strpos($key, '\\') !== false) return false;

        // Exclude filenames based on common extensions
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::FILE_LIKE_EXTENSIONS, true)) return false;

        // Must match allowed key pattern
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9._-]*$/', $key)) return false;

        // Respect exclusion list file
        if ($this->isExcludedKey($key)) return false;

        // Context-based exclusions (if context provided)
        if ($context !== '' && !$this->isValidInContext($key, $context)) return false;

        // Rule-based exclusions
        if (!$this->passesExclusionRules($key)) return false;

        return true;
    }

    private function passesExclusionRules(string $key): bool
    {
        $rules = self::EXCLUSION_RULES;

        foreach ($rules['config_patterns'] as $pattern) {
            if (str_starts_with($key, $pattern)) return false;
        }

        if (in_array($key, $rules['validation_rules'], true)) return false;

        foreach ($rules['validation_patterns'] as $pattern) {
            if (str_starts_with($key, $pattern)) return false;
        }

        if (in_array($key, $rules['db_fields'], true)) return false;

        if (in_array(strtolower($key), $rules['common_words'], true)) return false;

        foreach ($rules['code_patterns'] as $pattern) {
            if (preg_match($pattern, $key)) return false;
        }

        return true;
    }

    private function isValidInContext(string $key, string $context): bool
    {
        foreach (self::EXCLUSION_RULES['contexts'] as $contextPattern) {
            if (stripos($context, $contextPattern) !== false) {
                return false;
            }
        }
        return true;
    }

    private function isExcludedKey(string $key): bool
    {
        $excludedKeys = $this->getExcludedKeys();
        return in_array($key, $excludedKeys, true);
    }

    private function getExcludedKeys(): array
    {
        $excludeFile = storage_path('app/translation_exclude_keys.json');

        if (file_exists($excludeFile)) {
            $content = file_get_contents($excludeFile);
            $parsed = json_decode($content, true);
            if (is_array($parsed)) return $parsed;
        }

        $defaultExcluded = [
            '_CollapsibleSideSection', '_CollapsibleInnerSection', '_CollapsibleSideTitle', '_CollapsibleSideItem',
            '_Button', '_Link', '_Flex', '_Html', '_Sax', '_Collapsible',
            'class', 'id', 'href', 'src', 'alt', 'title', 'name', 'value', 'type',
            'php', 'js', 'css', 'html', 'json', 'xml', 'api', 'admin',
            'hidden', 'active', 'disabled', 'loading', 'home', 'login', 'logout'
        ];

        if (!file_exists($excludeFile)) {
            @file_put_contents($excludeFile, json_encode($defaultExcluded, JSON_PRETTY_PRINT));
        }

        return $defaultExcluded;
    }
}
