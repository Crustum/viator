<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Cake\Core\Configure;
use Cake\Utility\Inflector;
use Crustum\Scrutator\Component\ControllerAction as ControllerActionComponent;
use Crustum\Scrutator\Component\Route as RouteComponent;
use Crustum\Viator\Lang\TypeScript;
use Crustum\Viator\Result\Result;
use Crustum\Viator\Routing\RouteDefinition;
use Crustum\Viator\Routing\Verb;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * Walked routes and actions to TypeScript route functions.
 *
 * The upstream `Routes` converter, with Cake emission: action functions
 * resolve scanned actions against fallback templates, named routes render
 * one index file per dotted group, and every branch directory gets a
 * barrel. Output renders through the Twig templates (which own the emit),
 * so this converter prepares file contents and returns them as results.
 */
final class Routes
{
    /**
     * Accumulated file contents by base-relative path.
     *
     * @var array<string, array<int, string>>
     */
    protected array $content = [];

    /**
     * Accumulated imports by file path.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    protected array $imports = [];

    /**
     * Accumulated default imports by file path.
     *
     * Barrel files re-export child barrels/leafs via default imports
     * (`import Admin from './Admin'`), which do not fit the named-import
     * accumulator above.
     *
     * @var array<string, array<string, string>>
     */
    protected array $defaultImports = [];

    /**
     * @var \Twig\Environment|null
     */
    protected ?Environment $twig = null;

    /**
     * @param array{actionsDir?: string, routesDir?: string, runtimeDir?: string, withForm?: bool, generateActions?: bool, generateRoutes?: bool, only?: mixed, except?: mixed, ignoredActions?: array<array-key, mixed>} $options Emit options (raw option/Configure values)
     */
    public function __construct(
        protected array $options = [],
    ) {
    }

    /**
     * Convert walked actions and routes to file results.
     *
     * @param list<\Crustum\Scrutator\Component\Route> $routes Walked route components
     * @param list<\Crustum\Scrutator\Component\ControllerAction> $actions Walked action components
     * @return list<\Crustum\Viator\Result\Result> Base-relative file results
     */
    public function convert(array $routes, array $actions): array
    {
        $this->content = [];
        $this->imports = [];
        $this->defaultImports = [];

        $actionsDir = $this->options['actionsDir'] ?? 'actions';
        $routesDir = $this->options['routesDir'] ?? 'routes';

        if ($this->options['generateActions'] ?? true) {
            $grouped = [];
            foreach ($this->resolveActions($routes, $actions) as $definition) {
                $grouped[$this->normalizeGroup($definition->group)][] = $definition;
            }

            foreach ($grouped as $group => $definitions) {
                $this->writeControllerFile($definitions, $group);
            }

            $this->writeBarrels($grouped, $actionsDir);
        }

        if ($this->options['generateRoutes'] ?? true) {
            $grouped = [];
            foreach ($this->resolveNamed($routes) as $definition) {
                $grouped[$this->normalizeGroup($definition->group)][] = $definition;
            }

            foreach ($grouped as $group => $definitions) {
                $this->writeNamedFile($definitions, $group);
            }

            $this->writeBarrels($grouped, $routesDir);
        }

        return $this->flush();
    }

    /**
     * Write barrel `index.ts` files for grouped definitions.
     *
     * @param array<string, array<int, \Crustum\Viator\Routing\RouteDefinition>> $grouped Flat groups
     * @param string $dir Output subdirectory (`actions` or `routes`)
     * @return void
     */
    public function writeBarrels(array $grouped, string $dir): void
    {
        foreach ($this->undot($grouped) as $key => $subtree) {
            $this->writeBarrelFiles($subtree, [(string)$key], $dir);
        }
    }

    /**
     * Resolve walked controller actions against route templates.
     *
     * @param list<\Crustum\Scrutator\Component\Route> $routes Walked route components
     * @param list<\Crustum\Scrutator\Component\ControllerAction> $actions Walked action components
     * @return array<int, \Crustum\Viator\Routing\RouteDefinition>
     */
    protected function resolveActions(array $routes, array $actions): array
    {
        $templates = array_filter(
            $routes,
            static fn(RouteComponent $route): bool => str_contains($route->base()->template, '{controller}'),
        );
        $explicit = array_filter(
            $routes,
            static fn(RouteComponent $route): bool => !str_contains($route->base()->template, '{controller}')
                && !str_contains($route->base()->template, '*'),
        );

        $definitions = [];
        $seen = [];
        foreach ($actions as $action) {
            if (!$this->isActionAllowed($action)) {
                continue;
            }

            $group = $this->actionGroup($action);
            foreach ($templates as $route) {
                $definition = RouteDefinition::fromAction($action, $route, $group);
                if ($definition instanceof RouteDefinition && $this->isAllowed($definition->group)) {
                    $key = $definition->group . '|' . $definition->jsMethod . '|' . $definition->uri . '|' . implode(',', array_map(static fn($v) => $v->actual, $definition->verbs));
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $definitions[] = $definition;
                    }
                }
            }

            foreach ($explicit as $route) {
                $definition = RouteDefinition::fromExplicitRoute($action, $route, $group);
                if ($definition instanceof RouteDefinition && $this->isAllowed($definition->group)) {
                    $key = $definition->group . '|' . $definition->jsMethod . '|' . $definition->uri . '|' . implode(',', array_map(static fn($v) => $v->actual, $definition->verbs));
                    if (!isset($seen[$key])) {
                        $seen[$key] = true;
                        $definitions[] = $definition;
                    }
                }
            }
        }

        return $definitions;
    }

    /**
     * Resolve explicitly named walked routes (greedy `/*` routes are skipped).
     *
     * @param list<\Crustum\Scrutator\Component\Route> $routes Walked route components
     * @return array<int, \Crustum\Viator\Routing\RouteDefinition>
     */
    protected function resolveNamed(array $routes): array
    {
        $definitions = [];
        foreach ($routes as $route) {
            $name = $route->base()->options['_name'] ?? null;
            if (!is_string($name) || $name === '') {
                continue;
            }

            $name = (string)preg_replace(
                '/\.+/',
                '.',
                str_replace(['/', ':'], '.', $name),
            );
            if (!$this->isAllowed($name)) {
                continue;
            }

            $definition = RouteDefinition::fromNamedRoute($route, $name);
            if ($definition instanceof RouteDefinition) {
                $definitions[] = $definition;
            }
        }

        return $definitions;
    }

    /**
     * Check a collected action against the ignored-actions option.
     *
     * @param \Crustum\Scrutator\Component\ControllerAction $action Collected controller action
     * @return bool
     */
    protected function isActionAllowed(ControllerActionComponent $action): bool
    {
        foreach ($this->options['ignoredActions'] ?? [] as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            if ($entry === $action->action || $entry === $this->actionReference($action)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cake-style handler reference: `prefix:Controller:action`.
     *
     * @param \Crustum\Scrutator\Component\ControllerAction $action Collected controller action
     * @return string e.g. `Admin:Users:show`
     */
    protected function actionReference(ControllerActionComponent $action): string
    {
        $reference = '';
        if ($action->prefix !== null && $action->prefix !== '') {
            $reference .= $action->prefix . ':';
        }

        if ($action->plugin !== null && $action->plugin !== '') {
            $reference .= $action->plugin . '.';
        }

        return $reference . $action->controller . ':' . $action->action;
    }

    /**
     * Dotted group key for the actions/ file layout.
     *
     * @param \Crustum\Scrutator\Component\ControllerAction $action Collected controller action
     * @return string e.g. `Admin.Users`
     */
    protected function actionGroup(ControllerActionComponent $action): string
    {
        $parts = [];
        if ($action->plugin !== null && $action->plugin !== '') {
            $parts[] = $action->plugin;
        }

        if ($action->prefix !== null && $action->prefix !== '') {
            $parts[] = $action->prefix;
        }

        $parts[] = $action->controller;

        return implode('.', $parts);
    }

    /**
     * Check a dotted name against the only/except option lists.
     *
     * Included when the only list is empty or matches, and the except
     * list does not match.
     *
     * @param string $key Dotted route name or action group
     * @return bool
     */
    protected function isAllowed(string $key): bool
    {
        $only = $this->patterns('only');
        if ($only !== [] && !$this->matches($key, $only)) {
            return false;
        }

        return !$this->matches($key, $this->patterns('except'));
    }

    /**
     * Comma-separated wildcard patterns from options or Configure.
     *
     * Accepts a string (`admin.*,!admin.internal`) or a string array.
     * `*` wildcards via `fnmatch()`; a leading `!` negates the pattern.
     * Last matching pattern wins within one list. Falls back to the
     * `Viator.only` / `Viator.except` Configure keys the CLI flags mirror.
     *
     * @param string $key Option key (`only`, `except`)
     * @return array<int, string>
     */
    protected function patterns(string $key): array
    {
        $value = $this->options[$key] ?? null;
        if (is_string($value) && $value !== '') {
            return array_map(trim(...), explode(',', $value));
        }

        $configured = Configure::read('Viator.' . $key, []);
        if (is_string($configured)) {
            return array_map(trim(...), explode(',', $configured));
        }

        if (!is_array($configured)) {
            return [];
        }

        $patterns = [];
        foreach ($configured as $pattern) {
            if (is_string($pattern) && trim($pattern) !== '') {
                $patterns[] = trim($pattern);
            }
        }

        return $patterns;
    }

    /**
     * Wildcard match with `*` and `!` negation (last match wins).
     *
     * @param string $key Dotted name or action group
     * @param array<int, string> $patterns Patterns
     * @return bool
     */
    protected function matches(string $key, array $patterns): bool
    {
        $result = false;
        foreach ($patterns as $pattern) {
            if ($pattern === '') {
                continue;
            }

            $negate = str_starts_with($pattern, '!');
            $check = $negate ? substr($pattern, 1) : $pattern;
            if ($check !== '' && fnmatch($check, $key)) {
                $result = !$negate;
            }
        }

        return $result;
    }

    /**
     * Write one actions/ file for a controller group.
     *
     * @param array<int, \Crustum\Viator\Routing\RouteDefinition> $routes Group definitions
     * @param string $group Dotted group key
     * @param bool|null $withForm Whether to generate form variants, defaults to the `withForm` option
     * @return void
     */
    public function writeControllerFile(array $routes, string $group, ?bool $withForm = null): void
    {
        $namespace = $this->normalizeGroup($group);
        $path = $this->joinPath($this->options['actionsDir'] ?? 'actions', ...explode('.', $namespace)) . '.ts';
        $withForm ??= (bool)($this->options['withForm'] ?? true);

        $this->appendCommonImports($routes, $path, $namespace, $withForm);

        $byMethod = [];
        foreach ($routes as $route) {
            $byMethod[$route->jsMethod][] = $route;
        }

        $taken = [];
        foreach ($routes as $route) {
            $taken[] = $route->jsMethod;
            $taken[] = $route->originalMethod;
        }

        $splits = [];
        foreach ($byMethod as $methodRoutes) {
            if (count($methodRoutes) === 1) {
                $this->writeControllerMethodExport($methodRoutes[0], $path, $withForm);
                continue;
            }

            $split = $this->splitRootIndex($methodRoutes, $taken);
            if ($split === null) {
                $this->writeMultiRouteControllerMethodExport($methodRoutes, $path, $withForm);
                continue;
            }

            $splits[] = $split;
            $taken[] = $split['extra'];
            $this->writeSplitRootIndexExport($split, $path, $withForm);
        }

        $entries = [];
        $seen = [];
        foreach ($routes as $route) {
            if (!isset($seen[$route->jsMethod])) {
                $seen[$route->jsMethod] = true;
                $entries[] = ['key' => $route->jsMethod, 'alias' => $route->jsMethod];
            }

            if ($route->jsMethod !== $route->originalMethod) {
                $key = TypeScript::quoteIfNeeded($route->originalMethod);
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $entries[] = ['key' => $key, 'alias' => $route->jsMethod];
                }
            }
        }

        foreach ($splits as $split) {
            $key = $split['extra'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $entries[] = ['key' => $key, 'alias' => $key];
            }
        }

        $default = self::afterLast($namespace, '.');
        $this->appendContent($path, $this->renderNamespace($default, $entries));
    }

    /**
     * Write one routes/ index file for a named group.
     *
     * @param array<int, \Crustum\Viator\Routing\RouteDefinition> $routes Group definitions
     * @param string $namespace Dotted route name
     * @param bool|null $withForm Whether to generate form variants, defaults to the `withForm` option
     * @return void
     */
    public function writeNamedFile(array $routes, string $namespace, ?bool $withForm = null): void
    {
        $namespace = $this->normalizeGroup($namespace);
        $parts = explode('.', $namespace);
        array_pop($parts);
        $parts[] = 'index';

        $path = $this->joinPath($this->options['routesDir'] ?? 'routes', ...$parts) . '.ts';
        $withForm ??= (bool)($this->options['withForm'] ?? true);

        $this->appendCommonImports($routes, $path, $namespace, $withForm);

        foreach ($routes as $route) {
            $this->writeNamedMethodExport($route, $path, $withForm);
        }
    }

    /**
     * Register shared runtime imports for a generated file.
     *
     * @param array<int, \Crustum\Viator\Routing\RouteDefinition> $routes Group definitions
     * @param string $path Output file path
     * @param string $namespace Dotted group key
     * @param bool $withForm Whether form variants are generated
     * @return void
     */
    protected function appendCommonImports(array $routes, string $path, string $namespace, bool $withForm): void
    {
        $imports = ['queryParams', 'type RouteQueryOptions', 'type RouteDefinition'];
        $runtimeDir = $this->options['runtimeDir'] ?? 'viator';

        if ($withForm) {
            $imports[] = 'type RouteFormDefinition';
        }

        $hasParams = false;
        $hasOptional = false;
        foreach ($routes as $route) {
            if ($route->parameters !== []) {
                $hasParams = true;
            }

            foreach ($route->parameters as $parameter) {
                if ($parameter->optional) {
                    $hasOptional = true;
                }
            }
        }

        if ($hasParams) {
            $imports[] = 'applyUrlDefaults';
        }

        if ($hasOptional) {
            $imports[] = 'validateParameters';
        }

        $depth = substr_count($namespace, '.');
        $pathKey = '..' . str_repeat('/..', $depth) . '/' . $runtimeDir;

        $this->imports[$path] ??= [];
        $this->imports[$path][$pathKey] ??= [];
        $this->imports[$path][$pathKey] = array_values(array_unique(array_merge(
            $this->imports[$path][$pathKey],
            $imports,
        )));
    }

    /**
     * Expand dotted group keys into a nested tree.
     *
     * `['CakeDC.Admin.Admin' => [...]]` becomes
     * `['CakeDC' => ['Admin' => ['Admin' => [...]]]]`. A node may mix leaf
     * definitions (integer keys) with branch children (string keys) when
     * one group key is a prefix of another.
     *
     * @param array<string, array<int, mixed>> $grouped Flat groups
     * @return array<string|int, mixed> Nested tree
     */
    protected function undot(array $grouped): array
    {
        $tree = [];
        foreach ($grouped as $key => $value) {
            $node = &$tree;
            foreach (explode('.', (string)$key) as $segment) {
                if (!isset($node[$segment]) || !is_array($node[$segment])) {
                    $node[$segment] = [];
                }

                $node = &$node[$segment];
            }

            foreach ($value as $item) {
                $node[] = $item;
            }

            unset($node);
        }

        return $tree;
    }

    /**
     * Write barrel `index.ts` files for one tree level, recursively.
     *
     * Every branch directory gets an `index.ts` that default-imports its
     * children and re-exports them as one object, so
     * `@/actions/CakeDC/Admin` and `@/routes/admin` resolve without deep
     * imports. List nodes are leaves (their own files already exist) and
     * are skipped; integer keys (leaf definitions sharing a node with
     * branches) are skipped — their leaf file owns them.
     *
     * @param array<string|int, mixed> $children Tree level
     * @param array<int, string> $segments Path segments from the output dir
     * @param string $dir Output subdirectory (`actions` or `routes`)
     * @return void
     */
    protected function writeBarrelFiles(array $children, array $segments, string $dir): void
    {
        if ($children === [] || array_is_list($children)) {
            return;
        }

        $indexPath = $this->joinPath($dir, ...[...$segments, 'index']) . '.ts';

        $branchKeys = [];
        foreach ($children as $key => $value) {
            if (is_string($key) && is_array($value) && !array_is_list($value)) {
                $branchKeys[$key] = true;
            }
        }

        $childKeys = [];
        foreach ($children as $key => $_) {
            if (!is_string($key)) {
                continue;
            }

            $safeMethod = TypeScript::safeMethod($key, 'Method');
            $safe = $safeMethod;
            if (isset($branchKeys[$key])) {
                foreach ($this->content[$indexPath] ?? [] as $content) {
                    if (str_contains($content, 'const ' . $safeMethod . ' =')) {
                        $safe .= ucfirst(substr(hash('xxh128', $safe), 0, 6));
                    }
                }
            }

            $childKeys[$key] = [
                'safeMethod' => $safeMethod,
                'safe' => $safe,
                'safeAssign' => 'Object.assign(' . $safeMethod . ', ' . $safe . ')',
                'normalized' => TypeScript::quoteKey(
                    str_contains($key, '-') ? Inflector::variable($key) : $key,
                ),
            ];
        }

        $importPath = static fn(string $key): string => $key === 'index' ? './index/index' : './' . $key;

        $hasLeafContent = ($this->content[$indexPath] ?? []) !== [];
        $importable = [];
        foreach ($childKeys as $key => $alias) {
            if ($hasLeafContent) {
                if (isset($branchKeys[$key])) {
                    $importable[$key] = $alias;
                }
            } elseif ($key !== 'index' || isset($branchKeys[$key])) {
                $importable[$key] = $alias;
            }
        }

        foreach ($importable as $key => $alias) {
            $this->defaultImports[$indexPath][$importPath($key)] = $alias['safe'];
        }

        $entries = [];
        foreach ($childKeys as $alias) {
            $entries[] = ['key' => $alias['normalized'], 'value' => $alias['safeAssign']];
        }

        $varExport = TypeScript::safeMethod((string)end($segments), 'Method');
        $existingVars = [];
        foreach ($childKeys as $alias) {
            $existingVars[] = $alias['safeMethod'];
            $existingVars[] = $alias['safe'];
        }

        $existingVars = array_values(array_unique($existingVars));
        if (in_array($varExport, $existingVars, true)) {
            $baseExport = $varExport . 'Namespace';
            $varExport = TypeScript::safeMethod($baseExport, 'Method');
            $suffix = 2;
            while (in_array($varExport, $existingVars, true)) {
                $varExport = TypeScript::safeMethod($baseExport . $suffix, 'Method');
                $suffix++;
            }
        }

        $this->appendContent($indexPath, $this->renderBarrel($varExport, $entries));

        foreach ($children as $key => $grandChildren) {
            if (!is_string($key) || !is_array($grandChildren)) {
                continue;
            }

            $this->writeBarrelFiles($grandChildren, [...$segments, $key], $dir);
        }
    }

    /**
     * Render the default-export object of a barrel file.
     *
     * @param string $varExport Variable name
     * @param array<int, array{key: string, value: string}> $entries Object entries
     * @return string
     */
    protected function renderBarrel(string $varExport, array $entries): string
    {
        return $this->twig()->render('barrel.twig', [
            'varExport' => $varExport,
            'entries' => $entries,
        ]);
    }

    /**
     * Write a single-method export.
     *
     * @param \Crustum\Viator\Routing\RouteDefinition $route Definition
     * @param string $path Output file path
     * @param bool $withForm Whether to generate form variants
     * @return void
     */
    protected function writeControllerMethodExport(RouteDefinition $route, string $path, bool $withForm): void
    {
        $this->appendContent($path, $this->renderMethod($route, true, $withForm));
    }

    /**
     * Write a named-route method export (always exported).
     *
     * @param \Crustum\Viator\Routing\RouteDefinition $route Definition
     * @param string $path Output file path
     * @param bool $withForm Whether to generate form variants
     * @return void
     */
    protected function writeNamedMethodExport(RouteDefinition $route, string $path, bool $withForm): void
    {
        $this->appendContent($path, $this->renderMethod($route, true, $withForm));
    }

    /**
     * Write a dictionary export for several routes sharing one method.
     *
     * @param array<int, \Crustum\Viator\Routing\RouteDefinition> $routes Same-method definitions
     * @param string $path Output file path
     * @param bool $withForm Whether to generate form variants
     * @return void
     */
    protected function writeMultiRouteControllerMethodExport(array $routes, string $path, bool $withForm): void
    {
        $first = $routes[0];
        $method = $first->jsMethod;

        $this->appendContent($path, $this->renderMultiMethod($routes, $method, $withForm));
    }

    /**
     * Split a root/index fallback pair into two callables.
     *
     * Every param-less `index` action matches both `/{controller}` and
     * `/{controller}/{action}/*`, which would otherwise force all of them
     * into URI-keyed dictionaries. When exactly two parameter-less routes
     * share a jsMethod, use identical verbs, and one URI is the other plus
     * `/index`, the root URL keeps the plain method name and the explicit
     * one gets an `Explicit` suffix instead of a dictionary.
     *
     * All other same-method collisions keep the dictionary export.
     *
     * @param array<int, \Crustum\Viator\Routing\RouteDefinition> $routes Same-method definitions
     * @param array<int, string> $taken Method names already used in the file
     * @return array{root: \Crustum\Viator\Routing\RouteDefinition, suffixed: \Crustum\Viator\Routing\RouteDefinition, extra: string}|null
     */
    protected function splitRootIndex(array $routes, array $taken): ?array
    {
        if (count($routes) !== 2) {
            return null;
        }

        [$first, $second] = array_values($routes);
        if ($first->parameters !== [] || $second->parameters !== []) {
            return null;
        }

        $verbs = static fn(RouteDefinition $route): array => array_map(
            static fn(Verb $verb): string => $verb->actual,
            $route->verbs,
        );
        $firstVerbs = $verbs($first);
        sort($firstVerbs);
        $secondVerbs = $verbs($second);
        sort($secondVerbs);
        if ($firstVerbs !== $secondVerbs) {
            return null;
        }

        $firstUri = rtrim($first->uri, '/');
        $secondUri = rtrim($second->uri, '/');
        if ($firstUri . '/index' === $secondUri) {
            [$root, $suffixed] = [$first, $second];
        } elseif ($secondUri . '/index' === $firstUri) {
            [$root, $suffixed] = [$second, $first];
        } else {
            return null;
        }

        $extra = $root->jsMethod . 'Explicit';
        if (in_array($extra, $taken, true)) {
            return null;
        }

        return ['root' => $root, 'suffixed' => $suffixed, 'extra' => $extra];
    }

    /**
     * Write the two callables of a split root/index pair.
     *
     * @param array{root: \Crustum\Viator\Routing\RouteDefinition, suffixed: \Crustum\Viator\Routing\RouteDefinition, extra: string} $split Split pair
     * @param string $path Output file path
     * @param bool $withForm Whether to generate form variants
     * @return void
     */
    protected function writeSplitRootIndexExport(array $split, string $path, bool $withForm): void
    {
        $this->appendContent($path, $this->renderMethod($split['root'], true, $withForm));
        $this->appendContent($path, $this->renderMethod($split['suffixed'], true, $withForm, $split['extra']));
    }

    /**
     * URI prefixed with verbs for duplicate disambiguation.
     *
     * @param \Crustum\Viator\Routing\RouteDefinition $route Definition
     * @return string
     */
    protected function verbPrefixedUri(RouteDefinition $route): string
    {
        $verbs = [];
        foreach ($route->verbs as $verb) {
            $verbs[] = $verb->actual;
        }

        if (in_array('get', $verbs, true)) {
            $verbs = array_values(array_filter($verbs, static fn(string $verb): bool => $verb !== 'head'));
        }

        return implode('|', $verbs) . ' ' . $route->uri;
    }

    /**
     * Twig environment for the TypeScript templates.
     *
     * Autoescape is disabled: templates emit TypeScript, not HTML.
     * Every `{{ }}` interpolation is therefore raw.
     *
     * @return \Twig\Environment
     */
    protected function twig(): Environment
    {
        if (!isset($this->twig)) {
            $loader = new FilesystemLoader($this->templatePath());
            $this->twig = new Environment($loader, ['cache' => false, 'autoescape' => false]);
        }

        return $this->twig;
    }

    /**
     * Directory with the TypeScript templates.
     *
     * @return string
     */
    protected function templatePath(): string
    {
        return dirname(__DIR__, 2) . DS . 'templates' . DS . 'ts';
    }

    /**
     * Render one method export via `method.twig`.
     *
     * @param \Crustum\Viator\Routing\RouteDefinition $route Definition
     * @param bool $shouldExport Whether to export the const
     * @param bool $withForm Whether to generate form variants
     * @param string|null $tempMethod Override method name (multi-method)
     * @return string
     */
    protected function renderMethod(
        RouteDefinition $route,
        bool $shouldExport,
        bool $withForm,
        ?string $tempMethod = null,
    ): string {
        $method = $tempMethod ?? $route->jsMethod;

        return $this->twig()->render('method.twig', $this->methodContext($route, $method, $shouldExport, $withForm));
    }

    /**
     * Build the template context of one method.
     *
     * @param \Crustum\Viator\Routing\RouteDefinition $route Definition
     * @param string $method Const name used in the output
     * @param bool $shouldExport Whether to export the const
     * @param bool $withForm Whether to generate form variants
     * @param array<string, string>|null $names Safe local variable names
     * @return array<string, mixed>
     */
    protected function methodContext(
        RouteDefinition $route,
        string $method,
        bool $shouldExport,
        bool $withForm,
        ?array $names = null,
    ): array {
        $names ??= $this->safeParamNames($method);

        $parameters = [];
        $allOptional = true;
        $hasOptional = false;
        foreach ($route->parameters as $parameter) {
            if ($parameter->optional) {
                $hasOptional = true;
            } else {
                $allOptional = false;
            }

            $parameters[] = [
                'name' => $parameter->name,
                'types' => $parameter->types,
                'optional' => $parameter->optional,
                'safeName' => $parameter->safeName(),
                'default' => $parameter->default,
                'defaultJson' => json_encode($parameter->default),
                'placeholder' => $parameter->placeholder,
                'placeholderJson' => json_encode($parameter->placeholder, JSON_UNESCAPED_SLASHES),
                'nameJson' => json_encode($parameter->name),
            ];
        }

        $verbs = [];
        foreach ($route->verbs as $verb) {
            $verbs[] = [
                'actual' => $verb->actual,
                'actualJson' => json_encode($verb->actual),
                'safe' => $verb->formSafe,
                'safeJson' => json_encode($verb->formSafe),
                'upperJson' => json_encode(strtoupper($verb->actual)),
            ];
        }

        $first = $verbs[0] ?? null;

        return [
            'method' => $method,
            'docMethod' => $route->originalMethod,
            'shouldExport' => $shouldExport,
            'withForm' => $withForm,
            'controller' => $route->controllerLabel,
            'path' => $this->relativeDocPath($route->file),
            'line' => $route->line,
            'parameters' => $parameters,
            'allOptional' => $allOptional,
            'hasOptional' => $hasOptional,
            'verbs' => $verbs,
            'firstVerbJson' => json_encode($first['actual'] ?? 'get'),
            'verbsJson' => json_encode(array_column($verbs, 'actual')),
            'uri' => $route->uri,
            'uriLiteral' => json_encode($route->uri, JSON_UNESCAPED_SLASHES),
            'args' => $names['args'],
            'options' => $names['options'],
            'parsedArgs' => $names['parsedArgs'],
            'formMethod' => $method . 'Form',
            'firstSafe' => $first['safe'] ?? 'get',
            'firstActual' => $first['actual'] ?? 'get',
            'firstSafeJson' => json_encode($first['safe'] ?? 'get'),
            'firstActualUpperJson' => json_encode(strtoupper($first['actual'] ?? 'get')),
        ];
    }

    /**
     * Render a dictionary export for several routes sharing one method.
     *
     * @param array<int, \Crustum\Viator\Routing\RouteDefinition> $routes Same-method definitions
     * @param string $method Shared method name
     * @param bool $withForm Whether to generate form variants
     * @return string
     */
    protected function renderMultiMethod(array $routes, string $method, bool $withForm): string
    {
        $first = $routes[0];
        $names = $this->safeParamNames($method);

        $uris = [];
        foreach ($routes as $route) {
            $uris[] = $route->uri;
        }

        $duplicates = array_filter(array_count_values($uris), static fn(int $count): bool => $count > 1);

        $entries = [];
        foreach ($routes as $route) {
            $verbKey = implode(',', array_map(
                static fn($verb): string => $verb->actual,
                $route->verbs,
            ));
            $tempMethod = $method . substr(hash('xxh128', $route->uri . '|' . $verbKey), 0, 8);
            $key = isset($duplicates[$route->uri]) ? $this->verbPrefixedUri($route) : $route->uri;
            $entry = $this->methodContext($route, $tempMethod, false, $withForm, $names);
            $entry['tempMethod'] = $tempMethod;
            $entry['keyJson'] = json_encode($key, JSON_UNESCAPED_SLASHES);
            $entries[] = $entry;
        }

        return $this->twig()->render('multi-method.twig', [
            'method' => $method,
            'controller' => $first->controllerLabel,
            'originalMethod' => $first->originalMethod,
            'shouldExport' => true,
            'withForm' => $withForm,
            'args' => $names['args'],
            'options' => $names['options'],
            'parsedArgs' => $names['parsedArgs'],
            'routes' => $entries,
        ]);
    }

    /**
     * Render the default-export namespace object of a group file.
     *
     * @param string $varExport Variable name
     * @param array<int, array{key: string, alias: string}> $entries Object entries
     * @return string
     */
    protected function renderNamespace(string $varExport, array $entries): string
    {
        return $this->twig()->render('namespace.twig', [
            'varExport' => $varExport,
            'entries' => $entries,
        ]);
    }

    /**
     * Collision-safe local variable names.
     *
     * @param string $method Method name
     * @return array<string, string>
     */
    protected function safeParamNames(string $method): array
    {
        $reserved = [
            'args' => 'routeArgs',
            'options' => 'routeOptions',
            'parsedArgs' => 'routeParsedArgs',
        ];

        $out = [];
        foreach (array_keys($reserved) as $key) {
            $out[$key] = $method === $key ? $reserved[$key] : $key;
        }

        return $out;
    }

    /**
     * Controller path for docblocks, relative to the app root when possible.
     *
     * Absolute machine-specific paths would dirty every generated file in git.
     *
     * @param string $file Absolute file path
     * @return string
     */
    protected function relativeDocPath(string $file): string
    {
        $normalized = str_replace('\\', '/', $file);
        $root = rtrim(str_replace('\\', '/', (string)ROOT), '/') . '/';
        if (str_starts_with($normalized, $root)) {
            return substr($normalized, strlen($root));
        }

        return $normalized;
    }

    /**
     * Join path segments with a forward slash.
     *
     * Generated file paths and TypeScript imports always use `/`, even on
     * Windows — `DS` produced backslash filenames the TS toolchain cannot use.
     *
     * @param string ...$parts Path parts
     * @return string
     */
    protected function joinPath(string ...$parts): string
    {
        return implode('/', $parts);
    }

    /**
     * Normalize a dotted group key (plugin/prefix separators become dots).
     *
     * @param string $namespace Dotted group key
     * @return string
     */
    protected function normalizeGroup(string $namespace): string
    {
        return str_replace(['/', '\\'], '.', $namespace);
    }

    /**
     * Substring after the last occurrence of a separator.
     *
     * @param string $value Haystack
     * @param string $separator Separator
     * @return string
     */
    protected static function afterLast(string $value, string $separator): string
    {
        $position = strrpos($value, $separator);

        return $position === false ? $value : substr($value, $position + strlen($separator));
    }

    /**
     * Append generated content for a file.
     *
     * @param string $path File path
     * @param string $content Content chunk
     * @return void
     */
    protected function appendContent(string $path, string $content): void
    {
        $this->content[$path] ??= [];
        if (!in_array($content, $this->content[$path], true)) {
            $this->content[$path][] = $content;
        }
    }

    /**
     * Flush accumulated content to file results.
     *
     * Chunks come out of the Twig templates fully formatted (indentation
     * and blank lines are literal, Bake-style), so they are only joined —
     * no regex post-processing.
     *
     * @return list<\Crustum\Viator\Result\Result> Base-relative file results
     */
    public function flush(): array
    {
        $results = [];
        foreach ($this->content as $path => $chunks) {
            $trimmed = array_map(trim(...), $chunks);
            $body = implode("\n\n", $trimmed);
            $body = str_replace(["\r\n", "\r"], "\n", $body);
            $body .= "\n";

            if (isset($this->defaultImports[$path]) || isset($this->imports[$path])) {
                $importLines = [];
                foreach ($this->defaultImports[$path] ?? [] as $importPath => $alias) {
                    $importLines[] = "import {$alias} from '{$importPath}'";
                }

                foreach ($this->imports[$path] ?? [] as $importPath => $names) {
                    $importLines[] = 'import { ' . implode(', ', array_unique($names)) . " } from '{$importPath}'";
                }

                $body = implode("\n", $importLines) . "\n" . $body;
            }

            $results[] = new Result($path, $body);
        }

        $this->content = [];
        $this->imports = [];
        $this->defaultImports = [];

        return $results;
    }
}
