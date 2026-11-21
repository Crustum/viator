<?php
declare(strict_types=1);

namespace Crustum\Viator\Routing;

use Crustum\Scrutator\Component\ControllerAction as ControllerActionComponent;
use Crustum\Scrutator\Component\Route as RouteComponent;
use Crustum\Scrutator\Support\Verb as ScrutatorVerb;
use Crustum\Viator\Lang\TypeScript;

/**
 * Single TypeScript function definition resolved from a CakePHP route.
 *
 * Built either from an explicitly named route (routes/ output) or from a
 * scanned controller action resolved against a route template (actions/
 * output). URL segments are produced by asking the route itself
 * (`Route::match()` with placeholder tokens), so dasherize/underscore
 * inflection and patterns always follow the route setup — never guessed.
 */
class RouteDefinition
{
    /**
     * Tokens are rawurlencode-safe and survive inflection.
     *
     * @var string
     */
    protected const string TOKEN_PREFIX = '__viator';

    /**
     * Placeholder names owned by the routing layer, not by the URL.
     *
     * @var array<int, string>
     */
    protected const array RESERVED = ['controller', 'action', 'plugin', 'prefix', '_ext', '_method'];

    /**
     * @param string|null $name Dotted route name for routes/ output, null for actions/
     * @param string $group Dotted group key used for the file layout
     * @param string $jsMethod TypeScript function name
     * @param string $originalMethod Original method/action name before sanitizing
     * @param string $controllerLabel Controller label for the docblock
     * @param string $file Controller file path for the docblock
     * @param int $line Action first line for the docblock
     * @param array<int, \Crustum\Viator\Routing\Parameter> $parameters URL parameters
     * @param array<int, \Crustum\Viator\Routing\Verb> $verbs HTTP verbs
     * @param string $uri URI template with `{param}` / `{param?}` placeholders
     */
    public function __construct(
        public readonly ?string $name,
        public readonly string $group,
        public readonly string $jsMethod,
        public readonly string $originalMethod,
        public readonly string $controllerLabel,
        public readonly string $file,
        public readonly int $line,
        public readonly array $parameters,
        public readonly array $verbs,
        public readonly string $uri,
    ) {
    }

    /**
     * Build from an explicitly named route component.
     *
     * Returns null when the route cannot be expressed as a typed function:
     * greedy `/*` remainder, or routing placeholders (`{controller}` etc.)
     * without concrete defaults (covered by the actions/ scan instead).
     * Defaulted placeholders render optional in the URI, so
     * `definition.url` matches the `{param?}` replace keys.
     *
     * @param \Crustum\Scrutator\Component\Route $route Collected route component
     * @param string $name Dotted explicit route name
     * @return static|null
     */
    public static function fromNamedRoute(RouteComponent $route, string $name): ?static
    {
        $base = $route->base();
        $template = '/' . ltrim($base->template, '/');
        if (str_contains($template, '*')) {
            return null;
        }

        $parameters = [];
        foreach (self::placeholders($template) as [$param, $markedOptional]) {
            if (in_array($param, self::RESERVED, true) && !array_key_exists($param, $base->defaults)) {
                return null;
            }

            $hasDefault = array_key_exists($param, $base->defaults)
                && !in_array($param, ['controller', 'action', 'plugin', 'prefix', '_method'], true);
            $optional = $markedOptional || $hasDefault;
            $default = $hasDefault ? self::scalarDefault($base->defaults[$param]) : null;
            $parameters[] = new Parameter($param, $optional, $default);
        }

        $verbs = array_map(
            static fn(ScrutatorVerb $verb): Verb => new Verb($verb->actual),
            $route->verbs()->toList(),
        );
        $controller = $base->defaults['controller'] ?? '';
        $original = self::afterLast($name, '.');

        $uri = $template === '/' ? '/' : rtrim($template, '/');
        foreach ($parameters as $parameter) {
            if ($parameter->optional) {
                $uri = str_replace('{' . $parameter->name . '}', '{' . $parameter->name . '?}', $uri);
            }
        }

        return new static(
            name: $name,
            group: $name,
            jsMethod: TypeScript::safeMethod($original, 'Method'),
            originalMethod: $original,
            controllerLabel: $controller !== '' ? $controller . 'Controller' : 'Closure',
            file: '[unknown]',
            line: 0,
            parameters: $parameters,
            verbs: $verbs,
            uri: $uri,
        );
    }

    /**
     * Build from a collected controller action resolved against a route template.
     *
     * Returns null when the template cannot produce the action (no
     * `{controller}` placeholder, `{action}` missing for a non-default action,
     * or the route itself refuses the URL via `match()`).
     *
     * @param \Crustum\Scrutator\Component\ControllerAction $action Collected controller action
     * @param \Crustum\Scrutator\Component\Route $route Collected fallback template
     * @param string $group Dotted group key for the actions/ file layout
     * @return static|null
     */
    public static function fromAction(ControllerActionComponent $action, RouteComponent $route, string $group): ?static
    {
        $base = $route->base();
        $template = '/' . ltrim($base->template, '/');
        if (!str_contains($template, '{controller}')) {
            return null;
        }

        if (!self::scopeMatches($action, $route)) {
            return null;
        }

        if (!str_contains($template, '{action}') && ($base->defaults['action'] ?? 'index') !== $action->action) {
            return null;
        }

        $tokens = [];
        $url = [
            'controller' => $action->controller,
            'action' => $action->action,
        ];
        if ($action->prefix !== null) {
            $url['prefix'] = $action->prefix;
        }

        if ($action->plugin !== null) {
            $url['plugin'] = $action->plugin;
        }

        foreach (self::placeholders($template) as [$param, $markedOptional]) {
            if (in_array($param, self::RESERVED, true)) {
                continue;
            }

            if (array_key_exists($param, $base->defaults)) {
                continue;
            }

            return null;
        }

        $index = 0;
        foreach (array_merge($action->params, $action->optionalParams) as $param) {
            $token = self::TOKEN_PREFIX . $index . '__';
            $tokens[$token] = $param;
            $url[] = $token;
            $index++;
        }

        $generated = $route->matchUrl($url, array_values($action->methods));
        if (!is_string($generated) || $generated === '') {
            return null;
        }

        $parameters = [];
        foreach ($action->params as $i => $param) {
            $parameters[] = new Parameter($param, false);
            $generated = str_replace(self::TOKEN_PREFIX . $i . '__', "{{$param}}", $generated);
        }

        foreach ($action->optionalParams as $j => $param) {
            $parameters[] = new Parameter($param, true);
            $generated = str_replace(self::TOKEN_PREFIX . ($j + count($action->params)) . '__', "{{$param}?}", $generated);
        }

        $uri = $generated === '/' ? '/' : rtrim($generated, '/');

        $detected = array_map(
            static fn(string $method): Verb => new Verb($method),
            $action->methods,
        );
        $constrained = isset($base->defaults['_method']) || isset($base->options['_method']);
        if (!$constrained) {
            $verbs = $detected;
        } else {
            $only = array_map(
                static fn(ScrutatorVerb $verb): string => $verb->actual,
                $route->verbs()->toList(),
            );
            $filtered = array_values(array_filter(
                $detected,
                static fn(Verb $verb): bool => in_array($verb->actual, $only, true),
            ));
            $verbs = $filtered !== [] ? $filtered : array_map(
                static fn(ScrutatorVerb $verb): Verb => new Verb($verb->actual),
                $route->verbs()->toList(),
            );
        }

        return new static(
            name: null,
            group: $group,
            jsMethod: TypeScript::safeMethod($action->action, 'Method'),
            originalMethod: $action->action,
            controllerLabel: $action->class,
            file: $action->filePath(),
            line: $action->line,
            parameters: $parameters,
            verbs: $verbs,
            uri: $uri,
        );
    }

    /**
     * Build from a collected controller action resolved against an explicit
     * route (concrete `controller`/`action` defaults, no `{controller}`
     * placeholder).
     *
     * Prefixed/sub-namespace controllers (`Feature/LiveProps`) are
     * normally wired with explicit `prefix` routes rather than
     * `{controller}` fallback templates, so `fromAction()` alone never sees
     * them. This resolves those actions against their explicit routes.
     *
     * Returns null when the route is a fallback template (use `fromAction()`
     * instead), is greedy (`*`), belongs to another scope, or targets a
     * different controller/action. Surviving tokens also mean null: the
     * route took fewer args than the action declares (e.g. query-only
     * params), which cannot be expressed as a URL function.
     *
     * @param \Crustum\Scrutator\Component\ControllerAction $action Collected controller action
     * @param \Crustum\Scrutator\Component\Route $route Explicit collected route
     * @param string $group Dotted group key for the actions/ file layout
     * @return static|null
     */
    public static function fromExplicitRoute(ControllerActionComponent $action, RouteComponent $route, string $group): ?static
    {
        $base = $route->base();
        $template = '/' . ltrim($base->template, '/');
        if (str_contains($template, '{controller}')) {
            return null;
        }

        if (str_contains($template, '*')) {
            return null;
        }

        if (!self::scopeMatches($action, $route)) {
            return null;
        }

        $routeController = $base->defaults['controller'] ?? null;
        if (!is_string($routeController) || strtolower($routeController) !== strtolower($action->controller)) {
            return null;
        }

        $routeAction = $base->defaults['action'] ?? null;
        if (!is_string($routeAction) || $routeAction !== $action->action) {
            return null;
        }

        $plainPlaceholders = [];
        $defaultedPlaceholders = [];
        $placeholderDefault = [];
        foreach (self::placeholders($template) as [$param, $markedOptional]) {
            if (in_array($param, self::RESERVED, true)) {
                continue;
            }

            if (array_key_exists($param, $base->defaults)) {
                $defaultedPlaceholders[] = $param;
                $placeholderDefault[$param] = self::scalarDefault($base->defaults[$param]);
            } else {
                $plainPlaceholders[] = $param;
            }
        }

        $actionParams = array_merge($action->params, $action->optionalParams);
        $requiredCount = count($action->params);

        if (count($plainPlaceholders) > count($actionParams)) {
            return null;
        }

        $placeholderForAction = [];
        foreach ($plainPlaceholders as $position => $placeholder) {
            $placeholderForAction[$position] = $placeholder;
        }

        $normalize = static fn(string $value): string => strtolower(str_replace('_', '', $value));
        $defaultedByName = [];
        $defaultedByNormalized = [];
        foreach ($defaultedPlaceholders as $placeholder) {
            $defaultedByName[$placeholder] = $placeholder;
            $defaultedByNormalized[$normalize($placeholder)] ??= $placeholder;
        }

        $matchedDefaulted = [];
        foreach ($actionParams as $i => $param) {
            if ($i < count($plainPlaceholders)) {
                continue;
            }

            if ($i < $requiredCount) {
                return null;
            }

            $match = $defaultedByName[$param]
                ?? $defaultedByNormalized[$normalize($param)]
                ?? null;
            if ($match === null || isset($matchedDefaulted[$match])) {
                return null;
            }

            $matchedDefaulted[$match] = $i;
            $placeholderForAction[$i] = $match;
        }

        $tokens = [];
        $url = [
            'controller' => $action->controller,
            'action' => $action->action,
        ];
        if ($action->prefix !== null) {
            $url['prefix'] = $action->prefix;
        }

        if ($action->plugin !== null) {
            $url['plugin'] = $action->plugin;
        }

        $index = 0;
        $tokenByActionIndex = [];
        foreach ($actionParams as $i => $param) {
            if (!array_key_exists($i, $placeholderForAction)) {
                return null;
            }

            $placeholder = $placeholderForAction[$i];
            $token = self::TOKEN_PREFIX . $index . '__';
            $tokens[$token] = $param;
            $url[$placeholder] = $token;
            $tokenByActionIndex[$i] = $token;
            $index++;
        }

        $defaultTokens = [];
        foreach ($defaultedPlaceholders as $placeholder) {
            if (array_key_exists($placeholder, $matchedDefaulted)) {
                continue;
            }

            $token = self::TOKEN_PREFIX . $index . '__';
            $tokens[$token] = $placeholder;
            $url[$placeholder] = $token;
            $defaultTokens[$token] = $placeholder;
            $index++;
        }

        $generated = $route->matchUrl($url, array_values($action->methods), $defaultedPlaceholders);
        if (!is_string($generated) || $generated === '') {
            return null;
        }

        $parameters = [];
        foreach ($action->params as $i => $param) {
            $parameters[] = new Parameter($param, false);
            $generated = str_replace($tokenByActionIndex[$i], "{{$param}}", $generated);
        }

        foreach ($action->optionalParams as $j => $param) {
            $i = $j + count($action->params);
            $placeholder = $placeholderForAction[$i];
            $default = $placeholderDefault[$placeholder] ?? null;
            $parameters[] = new Parameter($param, true, $default);
            $generated = str_replace($tokenByActionIndex[$i], "{{$param}?}", $generated);
        }

        foreach ($defaultTokens as $token => $param) {
            $parameters[] = new Parameter($param, true, self::scalarDefault($base->defaults[$param]));
            $generated = str_replace($token, "{{$param}?}", $generated);
        }

        if (str_contains($generated, self::TOKEN_PREFIX)) {
            return null;
        }

        $uri = $generated === '/' ? '/' : rtrim($generated, '/');

        $detected = array_map(
            static fn(string $method): Verb => new Verb($method),
            $action->methods,
        );
        $constrained = isset($base->defaults['_method']) || isset($base->options['_method']);
        if (!$constrained) {
            $verbs = $detected;
        } else {
            $only = array_map(
                static fn(ScrutatorVerb $verb): string => $verb->actual,
                $route->verbs()->toList(),
            );
            $filtered = array_values(array_filter(
                $detected,
                static fn(Verb $verb): bool => in_array($verb->actual, $only, true),
            ));
            $verbs = $filtered !== [] ? $filtered : array_map(
                static fn(ScrutatorVerb $verb): Verb => new Verb($verb->actual),
                $route->verbs()->toList(),
            );
        }

        return new static(
            name: null,
            group: $group,
            jsMethod: TypeScript::safeMethod($action->action, 'Method'),
            originalMethod: $action->action,
            controllerLabel: $action->class,
            file: $action->filePath(),
            line: $action->line,
            parameters: $parameters,
            verbs: $verbs,
            uri: $uri,
        );
    }

    /**
     * Check that a route template belongs to the action's scope.
     *
     * A prefixed URL can only be produced by a route with the same prefix,
     * and plugin routes only serve their own plugin's controllers. Without
     * this, `Route::match()` would happily append the scope as a query
     * string or `/*` segment and produce a bogus definition.
     *
     * @param \Crustum\Scrutator\Component\ControllerAction $action Collected controller action
     * @param \Crustum\Scrutator\Component\Route $route Route template
     * @return bool
     */
    protected static function scopeMatches(ControllerActionComponent $action, RouteComponent $route): bool
    {
        $defaults = $route->base()->defaults;
        $routePrefix = $defaults['prefix'] ?? null;
        $routePlugin = $defaults['plugin'] ?? null;

        if ($routePrefix !== null || $action->prefix !== null) {
            if (!is_string($routePrefix) || $action->prefix === null) {
                return false;
            }

            $normalize = static fn(string $value): string => strtolower(str_replace('\\', '/', $value));
            if ($normalize($routePrefix) !== $normalize($action->prefix)) {
                return false;
            }
        }

        if ($routePlugin !== null || $action->plugin !== null) {
            if (!is_string($routePlugin) || $action->plugin === null) {
                return false;
            }

            if (strtolower($routePlugin) !== strtolower($action->plugin)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Method name used inside the url() body (temp name for multi-method).
     *
     * @param string|null $tempMethod Override method name
     * @return string
     */
    public function jsMethodForUrl(?string $tempMethod = null): string
    {
        return $tempMethod ?? $this->jsMethod;
    }

    /**
     * Extract `{name}` / `{name?}` placeholders from a template.
     *
     * @param string $template URI template
     * @return array<int, array{0: string, 1: bool}>
     */
    protected static function placeholders(string $template): array
    {
        preg_match_all('/\{([A-Za-z0-9_]+)(\?)?\}/', $template, $matches, PREG_SET_ORDER);
        $out = [];
        foreach ($matches as $match) {
            $out[] = [$match[1], str_ends_with($match[0], '?}')];
        }

        return $out;
    }

    /**
     * Normalize a default value to a string for TS output.
     *
     * @param mixed $value Default value
     * @return string|null
     */
    protected static function scalarDefault(mixed $value): ?string
    {
        if (is_scalar($value) || $value === null) {
            $string = $value === true ? '1' : ($value === false ? '0' : (string)$value);

            return $string === '' && $value !== '' ? null : $string;
        }

        return null;
    }

    /**
     * Return the substring after the last occurrence of a separator.
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
}
