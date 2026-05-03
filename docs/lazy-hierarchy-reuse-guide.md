# LazyHierarchy Reuse Guide

## Goal

`LazyHierarchy` should be reused the same way `Query` is reused: one generic Vue implementation, then thin PHP definitions per use case.

The important constraint is to keep `VlLazyHierarchy.vue` infrastructure-only. It should know how to:

- bootstrap a tree
- lazily load children
- paginate siblings
- apply search and modes
- render a node from a generic kompo `render` payload

It should not know anything about teams, roles, folders, categories, permissions, committees, or any other domain.

## What The Generic Layer Already Gives Us

Today the reusable contract is already there:

- `src/Kompo/Elements/LazyHierarchy.php`
- `src/Kompo/LazyHierarchy/LazyHierarchyPayload.php`
- `../js-kompo-utils/components/VlLazyHierarchy.vue`
- `../js-kompo-utils/components/VlLazyTree.vue`

That contract is URL-driven:

1. PHP configures a `LazyHierarchy` element with:
   - `bootstrapUrl`
   - `nodesUrl`
   - base request params
   - search config
   - mode config
   - paging config
   - labels/classes
2. The server returns a payload shaped like:

```php
[
    'nodes' => [
        '42' => [
            'id' => '42',
            'hasChildren' => true,
            'isCurrent' => false,
            'render' => /* kompo element tree */,
        ],
    ],
    'children' => [
        'root' => ['42'],
        '42' => ['58', '61'],
    ],
    'paging' => [
        'root' => [
            'nextCursor' => 20,
            'total' => 120,
            'limit' => 20,
            'append' => false,
        ],
    ],
    'expandedIds' => ['42'],
]
```

That is generic enough. The problem is not the Vue component. The problem is how a concrete hierarchy is assembled around it.

## Recommended Architecture

For a new hierarchy, split the implementation into four layers.

### 1. Element Definition

This is the PHP entry point used by pages/forms/components.

Its job is only to configure the generic `LazyHierarchy` element:

- endpoints
- request params
- display mode
- search
- modes
- labels
- CSS classes
- lazy/deferred loading

It should not query the database and it should not build node rows inline.

### 2. Controller / Endpoint Layer

This layer exposes two endpoints:

- bootstrap
- nodes

It should be very thin. Ideally it delegates immediately to an application service.

### 3. Hierarchy Query Service

This is the real orchestration layer.

Its responsibilities are:

- resolve filters from the request
- fetch roots or children from the domain repository
- decide which branches should start expanded
- attach paging metadata
- build a `LazyHierarchyPayload`

It should not know visual details beyond the fact that each node needs a `render` payload.

### 4. Node Render Factory

This layer converts a domain row into the generic node array expected by `LazyHierarchy`.

Typical output:

```php
[
    'id' => (string) $item->id,
    'hasChildren' => $item->children_count > 0,
    'isCurrent' => $context->isCurrent($item),
    'render' => _Flex(
        _Html($item->name)->class('min-w-0 flex-1 truncate'),
        _Pill($item->status),
        _Button('Open')->selfPost('...')
    )->class('items-center gap-2 w-full'),
]
```

This is the layer where the concrete product behavior lives:

- pills
- badges
- inline actions
- custom buttons
- links
- row highlighting
- domain-specific labels

That keeps the Vue generic while preserving the flexibility you asked for earlier: the internal row can still contain custom kompo behavior.

## The Best Reuse Shape Right Now

With the current implementation, the cleanest shape is not "one new `.vue` per hierarchy".

The cleanest shape is:

- one generic Vue: `VlLazyHierarchy.vue`
- one optional thin PHP element class per reusable family
- one query service
- one node render factory

For ergonomics, the concrete entry point should feel like a preset or definition, not like a frontend specialization.

## Three Possible Ways To Reuse It

### Option A. Create A New Vue For Each Hierarchy

Example:

- `VlFolderHierarchy.vue`
- `VlCategoryTree.vue`

This is the worst option.

Why:

- duplicates lazy-loading logic
- duplicates pagination logic
- duplicates search/mode wiring
- drifts visually and behaviorally
- turns a generic component back into a concrete one

This should be avoided.

### Option B. Create A Thin PHP Element Class Per Use Case

Example:

```php
class FolderHierarchy extends LazyHierarchy
{
    public function initialize($workspaceId = null)
    {
        parent::initialize();

        $this->hierarchySource('folders.tree.bootstrap', 'folders.tree.nodes')
            ->hierarchyParam('workspace_id', $workspaceId)
            ->hierarchyPaging(30, 120)
            ->config([
                'search' => [
                    'enabled' => true,
                    'placeholder' => __('Search a folder'),
                ],
            ]);
    }
}
```

This is acceptable when the hierarchy is reused in many places and deserves a named entry point.

Use it when you want:

- discoverability in PHP autocompletion
- shared defaults
- a stable public API such as `->forWorkspace($id)` or `->inline()`

### Option C. Build A Preset / Definition Helper That Returns `LazyHierarchy`

Example:

```php
final class FolderHierarchyDefinition
{
    public static function make(int $workspaceId): LazyHierarchy
    {
        return _LazyHierarchy()
            ->hierarchySource('folders.tree.bootstrap', 'folders.tree.nodes')
            ->hierarchyParam('workspace_id', $workspaceId)
            ->hierarchyPaging(30, 120)
            ->config([
                'search' => [
                    'enabled' => true,
                    'placeholder' => __('Search a folder'),
                ],
            ]);
    }
}
```

This is the best option with the current generic implementation.

Why:

- keeps one Vue
- keeps one generic element
- gives a `Query`-like PHP entry point
- keeps the use-case API explicit
- avoids a tree of tiny frontend subclasses

If I had to standardize the pattern today, I would recommend Option C first, and Option B only when the preset becomes large enough to deserve a dedicated class with instance methods.

## What To Standardize Across Projects

If multiple projects will build trees on top of `LazyHierarchy`, standardize these concepts:

### A. Definition / Preset

The public PHP API that pages consume.

Examples:

- `FolderHierarchyDefinition::make($workspaceId)`
- `CategoryPickerHierarchy::forCatalog($catalogId)`
- `LocationTreeDefinition::inline()->forRegion($regionId)`

### B. Query Service

The application service that returns `LazyHierarchyPayload`.

Examples:

- `FolderHierarchyQuery`
- `CategoryTreeQuery`
- `TeamRoleSwitcherQuery`

### C. Node Factory

The class that produces each node's `render`.

Examples:

- `FolderHierarchyNodeFactory`
- `CategoryTreeNodeFactory`
- `UserAccessNodeFactory`

This split is readable because each layer has one reason to change.

## Example: Implementing A Folder Picker

This is the kind of alternative implementation that should be easy with the current generic layer.

### Step 1. Public Definition

```php
final class FolderPickerHierarchy
{
    public static function make(int $workspaceId): LazyHierarchy
    {
        return _LazyHierarchy()
            ->hierarchySource('folders.hierarchy.bootstrap', 'folders.hierarchy.nodes')
            ->hierarchyParam('workspace_id', $workspaceId)
            ->hierarchyPaging(25, 75)
            ->hierarchyIndent(0.75, 1, 6)
            ->loadDeferred()
            ->config([
                'displayMode' => 'dropdown',
                'triggerLabel' => __('Choose folder'),
                'search' => [
                    'enabled' => true,
                    'placeholder' => __('Search a folder'),
                    'param' => 'search',
                ],
                'labels' => [
                    'loading' => __('Loading folders...'),
                    'empty' => __('No folders found'),
                ],
            ]);
    }
}
```

### Step 2. Controller

```php
final class FolderHierarchyController
{
    public function bootstrap(FolderHierarchyQuery $query)
    {
        return response()->json($query->bootstrap(request()));
    }

    public function nodes(FolderHierarchyQuery $query)
    {
        return response()->json($query->children(request()));
    }
}
```

### Step 3. Query Service

```php
final class FolderHierarchyQuery
{
    public function __construct(
        private FolderRepository $folders,
        private FolderHierarchyNodeFactory $nodeFactory,
    ) {
    }

    public function bootstrap($request): array
    {
        $payload = LazyHierarchyPayload::make();

        $roots = $this->folders->roots(
            workspaceId: (int) $request->workspace_id,
            search: (string) $request->search,
            limit: (int) $request->limit,
        );

        $payload->addNodes($roots->map(fn ($folder) => $this->nodeFactory->make($folder)));
        $payload->setChildren(null, $roots->pluck('id')->map(fn ($id) => (string) $id));

        return $payload->toArray();
    }

    public function children($request): array
    {
        $parentId = (string) $request->parent_id;
        $payload = LazyHierarchyPayload::make();

        $children = $this->folders->children(
            parentId: (int) $parentId,
            limit: (int) $request->limit,
            cursor: $request->cursor,
        );

        $payload->addNodes($children->map(fn ($folder) => $this->nodeFactory->make($folder)));
        $payload->setChildren($parentId, $children->pluck('id')->map(fn ($id) => (string) $id));

        return $payload->toArray();
    }
}
```

### Step 4. Node Factory

```php
final class FolderHierarchyNodeFactory
{
    public function make(Folder $folder): array
    {
        return [
            'id' => (string) $folder->id,
            'hasChildren' => $folder->children_count > 0,
            'render' => _Flex(
                _Html($folder->name)->class('min-w-0 flex-1 truncate'),
                _Html($folder->documents_count)->class('text-sm text-gray-500'),
            )->class('items-center gap-2 w-full'),
        ];
    }
}
```

That is the full implementation path. No extra Vue is required.

## How This Stays Flexible Enough

One concern with generic hierarchies is losing the ability to customize internal behavior.

With the current implementation, that is already avoidable because `render` is a full kompo element tree.

That means the node factory can still produce:

- `_Button()->selfPost(...)`
- `_Link(...)`
- `_Dropdown(...)`
- `_Tooltip(...)`
- `_Pill(...)`
- project-specific wrappers

So the concrete product behavior belongs in PHP composition, not in a new Vue file.

## Performance Rules

To keep generic hierarchies fast, the implementation rules matter more than the component itself.

### 1. Bootstrap Only What The User Needs

Good bootstrap examples:

- roots only
- roots + current path
- roots + first two visible levels

Bad bootstrap example:

- load the entire tree because the endpoint is available

### 2. Paginate Siblings, Not The Whole Tree

The current payload model is already designed for sibling pagination by parent key.

That is the correct shape for large trees.

### 3. Keep Search Server-Driven

Search should change the payload, not filter already-rendered nodes in the browser.

For hierarchical search, return:

- matching nodes
- enough ancestor path to make them visible
- optional `expandedIds` to open the path

### 4. Keep Node IDs Stable

Every node id must be:

- stable across requests
- string-cast consistently
- unique within the tree

### 5. Precompute Domain Flags In The Query/Repository Layer

The node factory should not need to ask the database whether a node:

- has children
- is selectable
- is current
- has warnings

Those flags should already be available in the row DTO or model projection.

## If We Want A More "Query-Like" Developer Experience

The next clean abstraction is not another Vue. It is a PHP helper layer on top of `LazyHierarchy`.

For example:

```php
final class LazyHierarchyDefinition
{
    public function __construct(
        protected string $bootstrapRoute,
        protected ?string $nodesRoute = null,
        protected array $params = [],
        protected array $config = [],
    ) {
    }

    public function toElement(): LazyHierarchy
    {
        return _LazyHierarchy()
            ->hierarchySource($this->bootstrapRoute, $this->nodesRoute ?: $this->bootstrapRoute)
            ->hierarchyParams($this->params)
            ->config($this->config);
    }
}
```

Then a concrete implementation becomes:

```php
final class FolderPickerHierarchyDefinition extends LazyHierarchyDefinition
{
    public function __construct(int $workspaceId)
    {
        parent::__construct(
            bootstrapRoute: 'folders.hierarchy.bootstrap',
            nodesRoute: 'folders.hierarchy.nodes',
            params: ['workspace_id' => $workspaceId],
            config: [
                'displayMode' => 'dropdown',
                'search' => ['enabled' => true],
            ],
        );
    }
}
```

This gives the team a reusable definition pattern without introducing another frontend specialization.

## Recommendation

If you want to implement another hierarchy today with the current generic implementation, I would do it this way:

1. Keep using the existing `VlLazyHierarchy.vue`.
2. Do not create another concrete Vue component.
3. Add a PHP definition/preset for the new tree.
4. Back it with a dedicated query service.
5. Keep row composition in a node factory.

That gives:

- readable architecture
- good reuse
- strong performance control
- high product flexibility
- very little frontend duplication

## Practical Rule Of Thumb

When building a new hierarchy, ask:

- "Is this a new tree behavior?"  
  If yes, change generic Vue.
- "Is this only a new domain row, endpoint, or set of actions?"  
  If yes, keep Vue untouched and implement it in PHP.

That boundary keeps `LazyHierarchy` generic and keeps concrete hierarchies cheap to build.
