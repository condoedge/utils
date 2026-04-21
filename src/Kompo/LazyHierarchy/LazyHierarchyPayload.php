<?php

namespace Condoedge\Utils\Kompo\LazyHierarchy;

class LazyHierarchyPayload
{
    public const ROOT_KEY = 'root';

    protected array $nodes = [];
    protected array $children = [];
    protected array $paging = [];
    protected array $expandedIds = [];

    public function __construct(
        protected array $meta = [],
        protected string $rootKey = self::ROOT_KEY,
        bool $includeRoot = true,
    ) {
        if ($includeRoot) {
            $this->children[$this->rootKey] = [];
        }
    }

    public static function make(array $meta = [], string $rootKey = self::ROOT_KEY, bool $includeRoot = true): static
    {
        return new static($meta, $rootKey, $includeRoot);
    }

    public function addNode(array $node, ?string $nodeId = null): static
    {
        $nodeId = $nodeId ?: ($node['id'] ?? null);

        if (!$nodeId) {
            return $this;
        }

        $this->nodes[$nodeId] = $node;

        return $this;
    }

    public function addNodes(iterable $nodes): static
    {
        foreach ($nodes as $node) {
            if (is_array($node)) {
                $this->addNode($node);
            }
        }

        return $this;
    }

    public function setChildren(string|int|null $parentKey, iterable $childIds, bool $append = false): static
    {
        $parentKey = $this->normalizeParentKey($parentKey);
        $childIds = $this->unique($childIds);

        $this->children[$parentKey] = $append
            ? $this->unique(array_merge($this->children[$parentKey] ?? [], $childIds))
            : $childIds;

        return $this;
    }

    public function appendChild(string|int|null $parentKey, string $childId): static
    {
        $parentKey = $this->normalizeParentKey($parentKey);

        $this->children[$parentKey] = $this->unique(array_merge($this->children[$parentKey] ?? [], [$childId]));

        return $this;
    }

    public function prependChild(string|int|null $parentKey, string $childId): static
    {
        $parentKey = $this->normalizeParentKey($parentKey);

        $this->children[$parentKey] = $this->unique(array_merge([$childId], $this->children[$parentKey] ?? []));

        return $this;
    }

    public function setPaging(
        string|int|null $parentKey,
        ?int $nextCursor,
        int $total,
        int $limit,
        bool $append = false,
        array $extra = [],
    ): static {
        $this->paging[$this->normalizeParentKey($parentKey)] = array_replace([
            'nextCursor' => $nextCursor,
            'total' => $total,
            'limit' => $limit,
            'append' => $append,
        ], $extra);

        return $this;
    }

    public function expand(string $nodeId): static
    {
        $this->expandedIds[] = $nodeId;
        $this->expandedIds = $this->unique($this->expandedIds);

        return $this;
    }

    public function expandMany(iterable $nodeIds): static
    {
        foreach ($nodeIds as $nodeId) {
            if ($nodeId) {
                $this->expand((string) $nodeId);
            }
        }

        return $this;
    }

    public function toArray(): array
    {
        return array_replace($this->meta, [
            'nodes' => $this->nodes,
            'children' => $this->children,
            'paging' => $this->paging,
            'expandedIds' => $this->expandedIds,
        ]);
    }

    protected function normalizeParentKey(string|int|null $parentKey): string
    {
        return $parentKey === null || $parentKey === '' ? $this->rootKey : (string) $parentKey;
    }

    protected function unique(iterable $values): array
    {
        return array_values(array_unique(is_array($values) ? $values : iterator_to_array($values)));
    }
}
