<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Routing;

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\Route\Route;
use Crustum\Scrutator\Component\ControllerAction as ControllerActionComponent;
use Crustum\Scrutator\Component\Route as RouteComponent;
use Crustum\Viator\Routing\RouteDefinition;
use PHPUnit\Framework\TestCase;
use TestApp\Controller\DemoController;
use TestApp\Controller\Prism\Prism\PrismController as NestedPrismController;
use TestApp\Controller\SharedUriController;

/**
 * Route definition test.
 *
 * Definitions build from Scrutator components: collected controller
 * actions pair with collected route templates (fallback) or named routes.
 */
class RouteDefinitionTest extends TestCase
{
    /**
     * Named routes resolve params, verbs and method names.
     *
     * @return void
     */
    public function testFromNamedRoute(): void
    {
        $route = new RouteComponent(
            new Route(
                '/users/{id}',
                ['controller' => 'Users', 'action' => 'view', '_method' => 'GET'],
                ['_name' => 'users:view'],
            ),
            [],
            null,
            null,
        );

        $definition = RouteDefinition::fromNamedRoute($route, 'users.view');

        $this->assertNotNull($definition);
        $this->assertSame('/users/{id}', $definition->uri);
        $this->assertSame('view', $definition->jsMethod);
        $this->assertSame(['id'], array_map(
            static fn($parameter): string => $parameter->name,
            $definition->parameters,
        ));
        $this->assertFalse($definition->parameters[0]->optional);
        $this->assertSame('get', $definition->verbs[0]->actual);
    }

    /**
     * Defaults mark parameters as optional with a default.
     *
     * @return void
     */
    public function testFromNamedRouteMarksDefaultedParamsOptional(): void
    {
        $route = new RouteComponent(
            new Route(
                '/posts/{page}',
                ['controller' => 'Posts', 'action' => 'index', 'page' => '1'],
                ['_name' => 'posts:index'],
            ),
            [],
            null,
            null,
        );

        $definition = RouteDefinition::fromNamedRoute($route, 'posts.index');

        $this->assertNotNull($definition);
        $this->assertTrue($definition->parameters[0]->optional);
        $this->assertSame('1', $definition->parameters[0]->default);
    }

    /**
     * Greedy routes cannot be expressed as typed functions.
     *
     * @return void
     */
    public function testFromNamedRouteSkipsGreedyTemplates(): void
    {
        $route = new RouteComponent(
            new Route(
                '/{controller}/{action}/*',
                [],
                ['_name' => 'fallback'],
            ),
            [],
            null,
            null,
        );

        $this->assertNull(RouteDefinition::fromNamedRoute($route, 'fallback'));
    }

    /**
     * Actions resolve against fallback templates with real inflection.
     *
     * @return void
     */
    public function testFromActionResolvesViaRouteMatch(): void
    {
        $route = new RouteComponent(new DashedRoute('/{controller}/{action}/*'), [], null, null);
        $action = (new ControllerActionComponent(
            DemoController::class,
            'view',
            'Demo',
            null,
            null,
            ['id'],
        ))->setFilePath(__FILE__);

        $definition = RouteDefinition::fromAction($action, $route, 'Demo');

        $this->assertNotNull($definition);
        $this->assertSame('/demo/view/{id}', $definition->uri);
        $this->assertSame('view', $definition->jsMethod);
        $this->assertSame('Demo', $definition->group);
    }

    /**
     * Static templates cannot produce actions.
     *
     * @return void
     */
    public function testFromActionSkipsStaticTemplates(): void
    {
        $route = new RouteComponent(
            new Route('/static', ['controller' => 'Pages', 'action' => 'display']),
            [],
            null,
            null,
        );
        $action = (new ControllerActionComponent(
            'TestApp\\Controller\\PagesController',
            'display',
            'Pages',
        ))->setFilePath(__FILE__);

        $this->assertNull(RouteDefinition::fromAction($action, $route, 'Pages'));
    }

    /**
     * Explicit prefixed routes resolve without fallback templates.
     *
     * @return void
     */
    public function testFromExplicitRouteResolvesPrefixedAction(): void
    {
        $route = new RouteComponent(
            new Route(
                '/features/forms/use-form',
                ['prefix' => 'Feature', 'controller' => 'Forms', 'action' => 'useForm', '_method' => 'GET'],
            ),
            [],
            null,
            null,
        );
        $action = (new ControllerActionComponent(
            'TestApp\\Controller\\Feature\\FormsController',
            'useForm',
            'Forms',
            null,
            'Feature',
        ))->setFilePath(__FILE__);

        $definition = RouteDefinition::fromExplicitRoute($action, $route, 'Feature.Forms');

        $this->assertNotNull($definition);
        $this->assertSame('/features/forms/use-form', $definition->uri);
        $this->assertSame('Feature.Forms', $definition->group);
        $this->assertSame('useForm', $definition->jsMethod);
    }

    /**
     * Explicit routes reject other scopes and actions.
     *
     * @return void
     */
    public function testFromExplicitRouteRejectsMismatch(): void
    {
        $route = new RouteComponent(
            new Route(
                '/features/forms/use-form',
                ['prefix' => 'Feature', 'controller' => 'Forms', 'action' => 'useForm', '_method' => 'GET'],
            ),
            [],
            null,
            null,
        );
        $other = (new ControllerActionComponent(
            'TestApp\\Controller\\Feature\\FormsController',
            'other',
            'Forms',
            null,
            'Feature',
        ))->setFilePath(__FILE__);

        $this->assertNull(RouteDefinition::fromExplicitRoute($other, $route, 'Feature.Forms'));

        $fallback = new RouteComponent(new DashedRoute('/{controller}/{action}/*'), [], null, null);
        $action = (new ControllerActionComponent(
            'TestApp\\Controller\\Feature\\FormsController',
            'useForm',
            'Forms',
            null,
            'Feature',
        ))->setFilePath(__FILE__);

        $this->assertNull(RouteDefinition::fromExplicitRoute($action, $fallback, 'Feature.Forms'));
    }

    /**
     * Placeholder patterns do not block explicit route resolution.
     *
     * @return void
     */
    public function testFromExplicitRouteIgnoresPlaceholderPatterns(): void
    {
        $route = new RouteComponent(
            new Route(
                '/features/data-loading/once-props/{page}',
                ['prefix' => 'Feature', 'controller' => 'DataLoading', 'action' => 'onceProps'],
                ['page' => '[12]', 'pass' => ['page']],
            ),
            [],
            null,
            null,
        );
        $action = (new ControllerActionComponent(
            'TestApp\\Controller\\Feature\\DataLoadingController',
            'onceProps',
            'DataLoading',
            null,
            'Feature',
            [],
            ['page'],
        ))->setFilePath(__FILE__);

        $definition = RouteDefinition::fromExplicitRoute($action, $route, 'Feature.DataLoading');

        $this->assertNotNull($definition);
        $this->assertSame('/features/data-loading/once-props/{page?}', $definition->uri);
    }

    /**
     * Defaulted placeholders satisfy optional action params (demo onceProps).
     *
     * Regression: `routes.php` sets `['page' => 1]` as the route default for
     * `/features/data-loading/once-props/{page}`, which used to exclude the
     * placeholder from matching and dropped `onceProps` from actions/ output.
     *
     * @return void
     */
    public function testFromExplicitRouteResolvesDefaultedPlaceholder(): void
    {
        $route = new RouteComponent(
            new Route(
                '/features/data-loading/once-props/{page}',
                ['prefix' => 'Feature', 'controller' => 'DataLoading', 'action' => 'onceProps', 'page' => 1],
                ['page' => '[12]', 'pass' => ['page']],
            ),
            [],
            null,
            null,
        );
        $action = (new ControllerActionComponent(
            'TestApp\\Controller\\Feature\\DataLoadingController',
            'onceProps',
            'DataLoading',
            null,
            'Feature',
            [],
            ['page'],
        ))->setFilePath(__FILE__);

        $definition = RouteDefinition::fromExplicitRoute($action, $route, 'Feature.DataLoading');

        $this->assertNotNull($definition);
        $this->assertSame('/features/data-loading/once-props/{page?}', $definition->uri);
        $this->assertCount(1, $definition->parameters);
        $this->assertSame('page', $definition->parameters[0]->name);
        $this->assertTrue($definition->parameters[0]->optional);
        $this->assertSame('1', $definition->parameters[0]->default);
    }

    /**
     * Explicit routes with placeholders resolve by name (no `pass` needed).
     *
     * Regression: tokens were passed numerically, so `Route::match()`
     * refused every placeholder route built via `RouteBuilder::connect()`.
     *
     * @return void
     */
    public function testFromExplicitRouteResolvesNamedPlaceholders(): void
    {
        $route = new RouteComponent(
            new Route(
                '/shared-uri/{name}',
                ['controller' => 'SharedUri', 'action' => 'handle'],
            ),
            [],
            null,
            null,
        );
        $action = (new ControllerActionComponent(
            SharedUriController::class,
            'handle',
            'SharedUri',
            null,
            null,
            ['name'],
        ))->setFilePath(__FILE__);

        $definition = RouteDefinition::fromExplicitRoute($action, $route, 'SharedUri');

        $this->assertNotNull($definition);
        $this->assertSame('/shared-uri/{name}', $definition->uri);
        $this->assertSame('SharedUri', $definition->group);
    }

    /**
     * Explicit routes map placeholders positionally when names differ.
     *
     * Cake routes often pass `{id}` to a `$contactId` action param (see the
     * demo `/contacts/{id}/notes` route). Names are only used to match
     * defaulted placeholders to optional params.
     *
     * @return void
     */
    public function testFromExplicitRouteMapsPlaceholdersPositionally(): void
    {
        $route = new RouteComponent(
            new Route(
                '/contacts/{id}/notes',
                ['controller' => 'Notes', 'action' => 'store'],
                ['pass' => ['id']],
            ),
            [],
            null,
            null,
        );
        $action = (new ControllerActionComponent(
            'TestApp\\Controller\\NotesController',
            'store',
            'Notes',
            null,
            null,
            ['contactId'],
        ))->setFilePath(__FILE__);

        $definition = RouteDefinition::fromExplicitRoute($action, $route, 'Notes');

        $this->assertNotNull($definition);
        $this->assertSame('/contacts/{contactId}/notes', $definition->uri);
        $this->assertCount(1, $definition->parameters);
        $this->assertSame('contactId', $definition->parameters[0]->name);
        $this->assertFalse($definition->parameters[0]->optional);
    }

    /**
     * Explicit routes reject arity mismatches.
     *
     * @return void
     */
    public function testFromExplicitRouteRejectsArityMismatch(): void
    {        $route = new RouteComponent(
            new Route(
                '/shared-uri/{name}',
                ['controller' => 'SharedUri', 'action' => 'handle'],
            ),
            [],
            null,
            null,
        );
        $action = (new ControllerActionComponent(
            SharedUriController::class,
            'handle',
            'SharedUri',
        ))->setFilePath(__FILE__);

        $this->assertNull(RouteDefinition::fromExplicitRoute($action, $route, 'SharedUri'));
    }

    /**
     * Nested prefixes resolve with slash-separated scopes.
     *
     * @return void
     */
    public function testFromExplicitRouteResolvesNestedPrefix(): void
    {
        $route = new RouteComponent(
            new Route(
                '/prism/prism/nested',
                ['prefix' => 'Prism/Prism', 'controller' => 'Prism', 'action' => 'nested'],
            ),
            [],
            null,
            null,
        );
        $action = (new ControllerActionComponent(
            NestedPrismController::class,
            'nested',
            'Prism',
            null,
            'Prism/Prism',
        ))->setFilePath(__FILE__);

        $definition = RouteDefinition::fromExplicitRoute($action, $route, 'Prism/Prism.Prism');

        $this->assertNotNull($definition);
        $this->assertSame('/prism/prism/nested', $definition->uri);
    }
}
