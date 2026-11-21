<?php
declare(strict_types=1);

namespace Crustum\Viator\Command;

use Cake\Collection\CollectionInterface;
use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Cake\Core\Container;
use Cake\Core\Exception\MissingPluginException;
use Cake\Core\Plugin;
use Crustum\Broadcasting\Broadcaster\BroadcasterInterface;
use Crustum\Investigator\Analyzer\AnalyzedCache;
use Crustum\Investigator\Support\Markers;
use Crustum\Scrutator\Collector\EnvironmentVariable as EnvironmentVariableCollector;
use Crustum\Scrutator\Collector\InertiaComponents;
use Crustum\Scrutator\Collector\Response as ResponseCollector;
use Crustum\Scrutator\Component\ControllerAction as ControllerActionComponent;
use Crustum\Scrutator\Component\Enum as EnumComponent;
use Crustum\Scrutator\Component\InertiaResponse as InertiaResponseComponent;
use Crustum\Scrutator\Component\InertiaSharedData as InertiaSharedDataComponent;
use Crustum\Scrutator\Component\JsonApiResponse as JsonApiResponseComponent;
use Crustum\Scrutator\Component\ResourceResponse as ResourceResponseComponent;
use Crustum\Scrutator\Component\Route as RouteComponent;
use Crustum\Scrutator\Scrutator;
use Crustum\Scrutator\ScrutatorPlugin;
use Crustum\Scrutator\Support\Inventory;
use Crustum\Viator\Assembler\BroadcastAssembler;
use Crustum\Viator\Assembler\EnumAssembler;
use Crustum\Viator\Assembler\EnvAssembler;
use Crustum\Viator\Assembler\RuntimeAssembler;
use Crustum\Viator\Assembler\TypesAssembler;
use Crustum\Viator\Attribute\ViatorIgnore;
use Crustum\Viator\Converter\Models;
use Crustum\Viator\Converter\Routes;
use Crustum\Viator\Result\Result;
use Crustum\Viator\Support\Files;
use Override;
use Throwable;

/**
 * Generate TypeScript route functions from CakePHP routes.
 *
 * Named routes become `routes/` functions, collected controller actions
 * become `actions/` functions, and a framework-agnostic runtime is copied
 * to `viator/`. URLs are produced by asking each route itself
 * (`Route::match()`), so dasherize/underscore inflection always follows
 * the route setup.
 *
 * Usage:
 * ```
 * bin/cake viator routes
 * bin/cake viator routes --path resources/js
 * ```
 */
class RoutesGenerateCommand extends Command
{
    /**
     * Output subdirectory for controller actions.
     *
     * @var string
     */
    protected string $actionsDir = 'actions';

    /**
     * Output subdirectory for named routes.
     *
     * @var string
     */
    protected string $routesDir = 'routes';

    /**
     * Output subdirectory for the runtime.
     *
     * @var string
     */
    protected string $runtimeDir = 'viator';

    /**
     * Current base output path (set during execute).
     *
     * @var string
     */
    protected string $currentBase = '';

    /**
     * Route components from the walk (named output + fallback templates).
     *
     * @var list<\Crustum\Scrutator\Component\Route>
     */
    protected array $walkRoutes = [];

    /**
     * Controller action components from the walk.
     *
     * @var list<\Crustum\Scrutator\Component\ControllerAction>
     */
    protected array $controllerActions = [];

    /**
     * Ignored action names from `Viator.ignoreActions` config.
     *
     * Entries are plain method names (`fetchCollection`) or Cake-style
     * handler references (`Admin:Users:export`).
     *
     * @var array<int, string>
     */
    protected array $ignoredActions = [];

    /**
     * Enum assembly from the walk.
     *
     * @var \Crustum\Viator\Assembler\EnumAssembler
     */
    protected EnumAssembler $enumAssembler;

    /**
     * Types file assembly from the walk.
     *
     * @var \Crustum\Viator\Assembler\TypesAssembler
     */
    protected TypesAssembler $typesAssembler;

    /**
     * Broadcast events from the walk, null when skipped.
     *
     * @var \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\BroadcastEvent>|null
     */
    protected ?CollectionInterface $eventComponents = null;

    /**
     * Broadcast channels from the walk, null when skipped or unresolvable.
     *
     * @var \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\BroadcastChannel>|null
     */
    protected ?CollectionInterface $channelComponents = null;

    /**
     * Environment variables from the walk, null when skipped.
     *
     * @var \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\EnvironmentVariable>|null
     */
    protected ?CollectionInterface $envComponents = null;

    /**
     * Page components from scanned actions.
     *
     * Route templates rarely carry concrete controllers (Cake fallback
     * routes resolve `{controller}` at runtime), so pages come from the
     * same scanned actions the actions/ output uses, analyzed through
     * the Scrutator Response collector.
     *
     * @var list<\Crustum\Scrutator\Component\InertiaResponse>
     */
    protected array $inertiaPages = [];

    /**
     * Response payloads from scanned actions, keyed by controller action.
     *
     * @var list<array{controller: string, action: string, response: \Crustum\Scrutator\Component\ResourceResponse|\Crustum\Scrutator\Component\JsonApiResponse}>
     */
    protected array $routeResponses = [];

    /**
     * @inheritDoc
     */
    #[Override]
    public static function defaultName(): string
    {
        return 'viator routes';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Generate TypeScript route functions from CakePHP routes';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->setDescription('Generate TypeScript route functions from CakePHP routes')
            ->addOption('path', [
                'help' => 'Base output path for generated files',
            ])
            ->addOption('skip-actions', [
                'boolean' => true,
                'help' => 'Skip generating controller action functions',
            ])
            ->addOption('skip-routes', [
                'boolean' => true,
                'help' => 'Skip generating named route functions',
            ])
            ->addOption('skip-types', [
                'boolean' => true,
                'help' => 'Skip generating App/Inertia TypeScript namespaces',
            ])
            ->addOption('skip-enums', [
                'boolean' => true,
                'help' => 'Skip generating enum types (*.Enum.* sections)',
            ])
            ->addOption('skip-forms', [
                'boolean' => true,
                'help' => 'Skip generating form types (*.Form.* sections)',
            ])
            ->addOption('skip-broadcasts', [
                'boolean' => true,
                'help' => 'Skip generating broadcast event/channel types',
            ])
            ->addOption('skip-env', [
                'boolean' => true,
                'help' => 'Skip generating vite-env.d.ts',
            ])
            ->addOption('with-form', [
                'boolean' => true,
                'help' => 'Generate form variants for non-GET verbs (on by default)',
            ])
            ->addOption('no-with-form', [
                'boolean' => true,
                'help' => 'Skip generating form variants for non-GET verbs (the parser cannot combine default with boolean, so opt-out carries the default)',
            ])
            ->addOption('plugin', [
                'help' => 'Comma-separated plugin short names whose controllers to scan',
            ])
            ->addOption('only', [
                'help' => 'Comma-separated dotted route/group patterns to include (`*` wildcards, `!` negation)',
            ])
            ->addOption('except', [
                'help' => 'Comma-separated dotted route/group patterns to exclude (`*` wildcards, `!` negation)',
            ])
            ->addOption('fresh', [
                'boolean' => true,
                'help' => 'Clear the analysis cache before generating',
            ]);
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $path = $this->args->getOption('path');
        if (is_string($path) && $path !== '') {
            $this->currentBase = rtrim(str_replace('\\', '/', $path), '/');
        } else {
            $configured = Configure::read('Viator.output');
            $base = is_string($configured) && $configured !== '' ? $configured : ROOT . '/resources/js';
            $this->currentBase = rtrim(str_replace('\\', '/', $base), '/');
        }

        $this->configureCaches();

        $withForm = (bool)$this->args->getOption('with-form') || !$this->args->getOption('no-with-form');

        $ignored = Configure::read('Viator.ignoreActions', []);
        $this->ignoredActions = [];
        if (is_array($ignored)) {
            foreach ($ignored as $entry) {
                if (is_string($entry)) {
                    $this->ignoredActions[] = $entry;
                }
            }
        }

        $this->enumAssembler = new EnumAssembler((bool)Configure::read('Viator.enumMethods', false));
        $this->typesAssembler = new TypesAssembler(
            (bool)$this->args->getOption('skip-enums'),
            (bool)$this->args->getOption('skip-forms'),
            $this->runtimeDir,
        );

        $this->collectScrutatorData();

        $results = [];
        if (!$this->args->getOption('skip-actions') || !$this->args->getOption('skip-routes')) {
            $routes = new Routes([
                'actionsDir' => $this->actionsDir,
                'routesDir' => $this->routesDir,
                'runtimeDir' => $this->runtimeDir,
                'withForm' => $withForm,
                'generateActions' => !$this->args->getOption('skip-actions'),
                'generateRoutes' => !$this->args->getOption('skip-routes'),
                'only' => $this->args->getOption('only'),
                'except' => $this->args->getOption('except'),
                'ignoredActions' => $this->ignoredActions,
            ]);
            $results = array_merge($results, $routes->convert($this->walkRoutes, $this->controllerActions));

            if (!$this->args->getOption('skip-actions')) {
                $this->io->out('[Viator] Generated actions in ' . $this->currentBase . '/' . $this->actionsDir);
            }

            if (!$this->args->getOption('skip-routes')) {
                $this->io->out('[Viator] Generated routes in ' . $this->currentBase . '/' . $this->routesDir);
            }
        }

        if (!$this->args->getOption('skip-types')) {
            $results[] = $this->typesAssembler->assemble();

            $this->io->out('[Viator] Generated types in ' . $this->currentBase . '/' . $this->runtimeDir . '/types.d.ts');
        }

        if (!$this->args->getOption('skip-enums')) {
            $enumResults = $this->enumAssembler->results();
            $results = array_merge($results, $enumResults);

            foreach ($enumResults as $result) {
                $this->io->out('[Viator] Generated enum in ' . $this->currentBase . '/' . $result->name);
            }
        }

        if (!$this->args->getOption('skip-env')) {
            $env = (new EnvAssembler())->assemble($this->currentBase, $this->envComponents);
            if ($env instanceof Result) {
                $results[] = $env;
                $this->io->out('[Viator] Generated env in ' . $this->currentBase . '/vite-env.d.ts');
            }
        }

        if (!$this->args->getOption('skip-broadcasts')) {
            $results = array_merge($results, (new BroadcastAssembler())->assemble($this->eventComponents, $this->channelComponents, $this->runtimeDir));
        }

        $results[] = (new RuntimeAssembler())->assemble($this->runtimeDir);

        $written = $this->writeFiles($results);
        Files::pruneTrees($this->currentBase, $this->enumAssembler->roots(), $written);
        foreach ($written as $file) {
            if (
                str_contains($file, '/' . $this->runtimeDir . '/broadcast-')
                || str_ends_with($file, '/' . $this->runtimeDir . '/echo-broadcast-events.d.ts')
            ) {
                $this->io->out('[Viator] Generated broadcast in ' . $file);
            }
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Configure the analysis cache for the run.
     *
     * Analysis results persist on disk between runs; `--fresh` or a
     * disabled cache clears them first so the walk always reflects the
     * current sources.
     *
     * @return void
     */
    protected function configureCaches(): void
    {
        AnalyzedCache::freezeFileTimes();

        $cacheDir = Configure::read('Viator.cache.directory');
        if (!is_string($cacheDir) || $cacheDir === '') {
            $cacheDir = CACHE . 'viator' . DS;
        }

        AnalyzedCache::setCacheDirectory($cacheDir);

        $enabled = (bool)Configure::read('Viator.cache.enabled', true);
        if ($this->args->getOption('fresh') || !$enabled) {
            AnalyzedCache::clear();
            Inventory::flush();
        }

        if ($enabled) {
            AnalyzedCache::enable();
        } else {
            AnalyzedCache::disable();
        }
    }

    /**
     * Write result files, creating directories and skipping unchanged files.
     *
     * Prunes stale outputs the same way the per-section writers used to:
     * generated files missing from this run are deleted from the
     * actions/ and routes/ trees, and orphaned broadcast files are
     * removed from the runtime dir.
     *
     * @param array<int, \Crustum\Viator\Result\Result> $results Base-relative file results
     * @return array<int, string> Written absolute paths
     */
    protected function writeFiles(array $results): array
    {
        $written = [];
        foreach ($results as $result) {
            $path = $this->currentBase . '/' . $result->name;
            Files::writeContentIfChanged($path, $result->content);
            $written[] = $path;
        }

        /** @var array<int, string> $actionPaths */
        $actionPaths = collection($written)
            ->filter(fn(string $path): bool => str_starts_with($path, $this->currentBase . '/' . $this->actionsDir . '/'))
            ->toList();
        /** @var array<int, string> $routePaths */
        $routePaths = collection($written)
            ->filter(fn(string $path): bool => str_starts_with($path, $this->currentBase . '/' . $this->routesDir . '/'))
            ->toList();
        Files::pruneStaleFiles($this->currentBase . '/' . $this->actionsDir, array_values($actionPaths));
        Files::pruneStaleFiles($this->currentBase . '/' . $this->routesDir, array_values($routePaths));

        foreach (glob($this->currentBase . '/' . $this->runtimeDir . '/{broadcast-*.ts,echo-broadcast-events.d.ts}', GLOB_BRACE) ?: [] as $stale) {
            if (!in_array($stale, $written, true)) {
                unlink($stale);
            }
        }

        return $written;
    }

    /**
     * Run one Scrutator walk, collecting types, routes, and broadcast data.
     *
     * Converters render from the collected components in the write methods
     * below; the routes/actions emitters keep the Cake naming, grouping,
     * and barrel layout untouched.
     *
     * @return void
     */
    protected function collectScrutatorData(): void
    {
        Markers::registerAttributes(ViatorIgnore::class);
        Markers::registerTags('viator-ignore');

        $skipTypes = (bool)$this->args->getOption('skip-types');
        $skipBroadcasts = (bool)$this->args->getOption('skip-broadcasts');
        $skipActions = (bool)$this->args->getOption('skip-actions');
        $skipRoutes = (bool)$this->args->getOption('skip-routes');
        $needRoutes = !$skipActions || !$skipRoutes || !$skipTypes;
        $needBroadcasts = !$skipBroadcasts;
        $needEnv = !$this->args->getOption('skip-env');
        if (!$needRoutes && !$needBroadcasts && !$needEnv) {
            return;
        }

        $container = new Container();
        (new ScrutatorPlugin())->services($container);
        $scrutator = $container->get(Scrutator::class);
        $scrutator->setBasePaths(ROOT)->setAppPaths(...$this->walkAppPaths());

        if (!$skipTypes) {
            if (!$this->args->getOption('skip-enums')) {
                $scrutator->onEnums(function (CollectionInterface $enums): void {
                    $this->enumAssembler->register($enums);
                });
                $scrutator->onEnum(function (EnumComponent $enum): void {
                    $this->enumAssembler->add($enum);
                });
            }

            $scrutator->onEntities(function (CollectionInterface $entities): void {
                $this->typesAssembler->setModels((new Models())->blocks($entities));
            });
            $scrutator->onInertiaSharedData(function (InertiaSharedDataComponent $shared): void {
                $this->typesAssembler->addSharedData($shared);
            });

            if (!$this->args->getOption('skip-forms')) {
                $scrutator->onFormValidators(function (CollectionInterface $validators): void {
                    $this->typesAssembler->setValidators($validators);
                });
            }
        }

        if ($needRoutes) {
            $scrutator->onRoutes(function (CollectionInterface $routes): void {
                foreach ($routes as $route) {
                    if ($route instanceof RouteComponent) {
                        $this->walkRoutes[] = $route;
                    }
                }
            });
            $scrutator->onControllerActions(function (CollectionInterface $actions): void {
                foreach ($actions as $action) {
                    if ($action instanceof ControllerActionComponent) {
                        $this->controllerActions[] = $action;
                    }
                }
            });
        }

        if (!$skipBroadcasts) {
            $scrutator->onBroadcastEvents(function (CollectionInterface $events): void {
                $this->eventComponents = $events;
            });

            try {
                $container->get(BroadcasterInterface::class);
                $scrutator->onBroadcastChannels(function (CollectionInterface $channels): void {
                    $this->channelComponents = $channels;
                });
            } catch (Throwable) {
                $this->channelComponents = null;
            }
        }

        if ($needEnv) {
            $envFiles = Configure::read('Viator.envFiles', [ROOT . DS . 'config' . DS . '.env']);
            $container->get(EnvironmentVariableCollector::class)
                ->setEnvFiles(...(is_array($envFiles) ? array_values(array_filter($envFiles, is_string(...))) : []))
                ->setJsRoots($this->currentBase);
            $scrutator->onEnvironmentVariables(function (CollectionInterface $variables): void {
                $this->envComponents = $variables;
            });
        }

        $scrutator->walk();

        if (!$skipTypes) {
            $this->collectActionResponses($container);
            $this->typesAssembler->setEnums($this->enumAssembler->unions(), $this->enumAssembler->docs());
            $this->typesAssembler->setPages($this->inertiaPages);
            $this->typesAssembler->setResponses($this->routeResponses);
        }
    }

    /**
     * Collect page and response payloads from walked controller actions.
     *
     * Runs on every collected action even when actions/ output is skipped:
     * the types need pages regardless of the emitted files. Route
     * templates rarely carry concrete controllers, so actions pair with
     * templates downstream instead of the other way round.
     *
     * @param \Cake\Core\Container $container Container resolving collectors
     * @return void
     */
    protected function collectActionResponses(Container $container): void
    {
        $responses = $container->get(ResponseCollector::class);
        foreach ($this->controllerActions as $action) {
            if (!$this->isActionAllowed($action)) {
                continue;
            }

            $parsed = $responses->parseResponse(['controller' => $action->class, 'action' => $action->action]);
            foreach ($parsed as $response) {
                if (is_string($response)) {
                    $response = InertiaComponents::getComponent($response);
                }

                if ($response instanceof InertiaResponseComponent) {
                    $this->inertiaPages[] = $response;
                    continue;
                }

                if ($response instanceof ResourceResponseComponent || $response instanceof JsonApiResponseComponent) {
                    $this->routeResponses[] = [
                        'controller' => str_replace('\\', '.', ltrim($action->class, '\\')),
                        'action' => $action->action,
                        'response' => $response,
                    ];
                }
            }
        }
    }

    /**
     * Check an action against the ignored-actions config.
     *
     * Entries are plain method names (`fetchCollection`, skipped in every
     * controller) or Cake-style handler references
     * (`Admin:Users:export`).
     *
     * @param \Crustum\Scrutator\Component\ControllerAction $action Collected controller action
     * @return bool
     */
    protected function isActionAllowed(ControllerActionComponent $action): bool
    {
        foreach ($this->ignoredActions as $ignored) {
            if ($ignored === $action->action || $ignored === $this->actionReference($action)) {
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
     * Directories the walk scans: the app plus every configured source dir.
     *
     * @return array<int, string>
     */
    protected function walkAppPaths(): array
    {
        $paths = [
            APP,
            ...$this->entityDirs(),
            ...$this->enumDirs(),
            ...$this->formDirs(),
            ...$this->eventDirs(),
        ];

        foreach ($this->scanPluginNames() as $plugin) {
            try {
                $paths[] = Plugin::path($plugin) . 'src';
            } catch (MissingPluginException) {
                continue;
            }
        }

        return array_values(array_unique(array_filter(
            $paths,
            static fn(string $path): bool => $path !== '' && is_dir($path),
        )));
    }

    /**
     * Entity source directories.
     *
     * @return array<int, string>
     */
    protected function entityDirs(): array
    {
        $entityDirs = Configure::read('Viator.entityDirs');
        if (!is_array($entityDirs)) {
            $entityDirs = [APP . 'Model' . DS . 'Entity'];
            foreach ($this->scanPluginNames() as $plugin) {
                try {
                    $path = Plugin::path($plugin) . 'src' . DS . 'Model' . DS . 'Entity';
                } catch (MissingPluginException) {
                    continue;
                }

                if (is_dir($path)) {
                    $entityDirs[] = $path;
                }
            }
        }

        return array_values(array_filter($entityDirs, is_string(...)));
    }

    /**
     * Enum source directories.
     *
     * @return array<int, string>
     */
    protected function enumDirs(): array
    {
        $enumDirs = Configure::read('Viator.enumDirs', [APP]);

        return is_array($enumDirs) ? array_values(array_filter($enumDirs, is_string(...))) : [APP];
    }

    /**
     * Form source directories.
     *
     * @return array<int, string>
     */
    protected function formDirs(): array
    {
        $formDirs = Configure::read('Viator.formDirs', [APP . 'Form']);

        return is_array($formDirs) ? array_values(array_filter($formDirs, is_string(...))) : [APP . 'Form'];
    }

    /**
     * Broadcast event source directories.
     *
     * @return array<int, string>
     */
    protected function eventDirs(): array
    {
        $eventDirs = Configure::read('Viator.eventDirs', [APP . 'Event']);

        return is_array($eventDirs) ? array_values(array_filter($eventDirs, is_string(...))) : [APP . 'Event'];
    }

    /**
     * Plugin short names to scan, from `--plugin` or `Viator.scanPlugins`.
     *
     * Covers walk paths plus entity and controller directories.
     *
     * @return array<int, string>
     */
    protected function scanPluginNames(): array
    {
        $plugins = [];
        $option = $this->args->getOption('plugin');
        if (is_string($option) && $option !== '') {
            foreach (explode(',', $option) as $plugin) {
                $plugin = trim($plugin);
                if ($plugin !== '') {
                    $plugins[] = $plugin;
                }
            }
        }

        if ($plugins === []) {
            $configured = Configure::read('Viator.scanPlugins', []);
            if (is_array($configured)) {
                foreach ($configured as $plugin) {
                    if (is_string($plugin)) {
                        $plugins[] = $plugin;
                    }
                }
            }
        }

        return $plugins;
    }
}
