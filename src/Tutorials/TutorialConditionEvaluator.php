<?php

namespace Condoedge\Utils\Tutorials;

use Closure;

class TutorialConditionEvaluator
{
    protected static array $registered = [];

    public static function register(string $name, Closure $callback): void
    {
        static::$registered[$name] = $callback;
    }

    public function evaluate(string $expression): bool
    {
        $tokens = $this->tokenize($expression);
        $pos = 0;
        return $this->parseOr($tokens, $pos);
    }

    protected function parseOr(array $tokens, int &$pos): bool
    {
        $result = $this->parseAnd($tokens, $pos);
        while ($pos < count($tokens) && $tokens[$pos] === '||') {
            $pos++;
            $result = $result || $this->parseAnd($tokens, $pos);
        }
        return $result;
    }

    protected function parseAnd(array $tokens, int &$pos): bool
    {
        $result = $this->parseNot($tokens, $pos);
        while ($pos < count($tokens) && $tokens[$pos] === '&&') {
            $pos++;
            $result = $result && $this->parseNot($tokens, $pos);
        }
        return $result;
    }

    protected function parseNot(array $tokens, int &$pos): bool
    {
        if ($pos < count($tokens) && $tokens[$pos] === '!') {
            $pos++;
            return !$this->parsePrimary($tokens, $pos);
        }
        return $this->parsePrimary($tokens, $pos);
    }

    protected function parsePrimary(array $tokens, int &$pos): bool
    {
        if ($pos < count($tokens) && $tokens[$pos] === '(') {
            $pos++;
            $result = $this->parseOr($tokens, $pos);
            $pos++; // skip ')'
            return $result;
        }
        $atom = $tokens[$pos] ?? '';
        $pos++;
        return $this->evaluateAtom($atom);
    }

    protected function tokenize(string $expression): array
    {
        $tokens = [];
        $i = 0;
        $len = strlen($expression = trim($expression));

        while ($i < $len) {
            if ($expression[$i] === ' ') { $i++; continue; }
            if (in_array($expression[$i], ['(', ')', '!'])) {
                $tokens[] = $expression[$i]; $i++; continue;
            }
            if (substr($expression, $i, 2) === '&&') { $tokens[] = '&&'; $i += 2; continue; }
            if (substr($expression, $i, 2) === '||') { $tokens[] = '||'; $i += 2; continue; }

            $start = $i;
            while ($i < $len && $expression[$i] !== ' ' && $expression[$i] !== '('
                && $expression[$i] !== ')' && substr($expression, $i, 2) !== '&&'
                && substr($expression, $i, 2) !== '||') {
                $i++;
            }
            $tokens[] = substr($expression, $start, $i - $start);
        }
        return $tokens;
    }

    protected function evaluateAtom(string $atom): bool
    {
        // Built-in types (colon syntax)
        if (str_contains($atom, ':')) {
            [$type, $value] = explode(':', $atom, 2);
            return match ($type) {
                'permission' => $this->checkPermission($value),
                'setting'    => (bool) (auth()->user()?->getSettingValue($value)),
                'role'       => auth()->user()?->hasRole($value) ?? false,
                'selector'   => true,  // Client-side — always pass server-side
                default      => false,
            };
        }

        // Registered custom conditions
        if (isset(static::$registered[$atom])) {
            return (bool) (static::$registered[$atom])();
        }

        // Unknown = false (fail-closed)
        return false;
    }

    protected function checkPermission(string $value): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        if (!str_contains($value, ',')) {
            return $user->hasPermission($value);
        }

        [$key, $typeStr] = explode(',', $value, 2);
        $typeStr = strtoupper(trim($typeStr));

        // Map string to PermissionTypeEnum
        $typeMap = [
            'READ' => 1,
            'WRITE' => 3,
            'ALL' => 7,
        ];

        if (isset($typeMap[$typeStr]) && enum_exists(\Kompo\Auth\Models\Teams\PermissionTypeEnum::class)) {
            $enum = \Kompo\Auth\Models\Teams\PermissionTypeEnum::from($typeMap[$typeStr]);
            return $user->hasPermission(trim($key), $enum);
        }

        return $user->hasPermission(trim($key));
    }
}
