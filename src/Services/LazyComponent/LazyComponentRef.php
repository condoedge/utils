<?php

namespace Condoedge\Utils\Services\LazyComponent;

/**
 * A stored lazy closure: the compiled code's key, plus the variables it captured.
 *
 * The two are deliberately separate. The key addresses a file that holds code only
 * and is shared by every render of that call site; the captured variables belong to
 * *this* render and travel with the request, encrypted. Putting them in the same
 * place is what froze one person's id for every user.
 */
class LazyComponentRef
{
    public function __construct(
        public readonly string $key,
        public readonly array $use,
        private readonly string $path,
    ) {
    }

    /** Absolute path of the compiled file. Exposed for tests and tooling. */
    public function path(): string
    {
        return $this->path;
    }
}
