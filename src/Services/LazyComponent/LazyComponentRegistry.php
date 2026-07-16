<?php

namespace Condoedge\Utils\Services\LazyComponent;

use Closure;
use Laravel\SerializableClosure\Support\ReflectionClosure;

class LazyComponentRegistry
{
    /**
     * Compile a closure's code to a shared file and lift its captured variables out.
     *
     * The key identifies the closure itself (via reflection), not the call path: a
     * fixed backtrace depth is wrong for at least one wrapper (`_LazyComponent` sits
     * one frame shallower than `_LazyTab`/`_LazyCollapsible`/`_LazyResponsive`), and
     * it made the key depend on the caller of the enclosing method.
     *
     * filemtime() invalidates on deploy and on local edits, uniformly — so there is
     * no isProduction() branch and a compiled file is never stale.
     */
    public function store(Closure $closure): LazyComponentRef
    {
        $ref = new \ReflectionFunction($closure);

        $file = $ref->getFileName();
        $line = $ref->getStartLine();

        $key = sha1($file . ':' . $line . ':' . filemtime($file));

        $compiledPath = $this->compiledPath($key);
        if (!file_exists($compiledPath)) {
            $this->compile($closure, $compiledPath);
        }

        return new LazyComponentRef($key, $ref->getStaticVariables(), $compiledPath);
    }

    /**
     * Rebuild a stored closure, injecting the captured variables for *this* request.
     *
     * Returns null for unknown or malformed keys: the key arrives from the request
     * and ends up in a require(), so it is refused unless it is a bare hash. It
     * carries no state and is not a secret — the state travels separately, encrypted.
     */
    public function retrieve(string $key, array $use = []): ?Closure
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $key)) {
            return null;
        }

        $compiledPath = $this->compiledPath($key);
        if (!file_exists($compiledPath)) {
            return null;
        }

        $factory = $this->requireFactory($compiledPath);
        if (!$factory instanceof Closure) {
            return null;
        }

        $closure = $factory($use);

        return $closure instanceof Closure ? $closure : null;
    }

    /**
     * Write the closure's code to a plain PHP file, so loading it is a require() that
     * opcache serves — cheaper than unserialize()+eval.
     *
     * The file holds a *factory*: it takes the captured variables and returns the real
     * closure with them bound. That is what keeps `use ($id)` working while keeping the
     * data out of the file — extract() puts the variables back in scope, so both
     * `use (...)` clauses and arrow-function auto-capture resolve against this render's
     * values instead of whichever render compiled the file first.
     */
    protected function compile(Closure $closure, string $path): void
    {
        $code = (new ReflectionClosure($closure))->getCode();

        $factory = <<<PHP
        <?php

        // Compiled by Condoedge\\Utils LazyComponentRegistry. Code only — never data.
        return function (array \$__lazyUse) {
            extract(\$__lazyUse, EXTR_SKIP);

            return {$code};
        };

        PHP;

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Unique temp file + atomic rename: concurrent requests compiling the same key
        // must never let a reader require() a half-written file.
        $tmp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
        file_put_contents($tmp, $factory);
        rename($tmp, $path);
    }

    /** require() in a scope of its own, so the factory never inherits $this from here. */
    protected function requireFactory(string $path)
    {
        return (static fn () => require $path)();
    }

    protected function compiledPath(string $key): string
    {
        return storage_path("framework/kompo-lazy/{$key}.php");
    }
}
