<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Core\Plugin;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Crustum\Broadcasting\Broadcasting;
use Override;
use TestApp\Ignore\IgnoreWholeController;

/**
 * Test application for Crustum/Viator plugin tests.
 *
 * Mirrors `workspace/wayfinder/workbench/routes/web.php` as closely as
 * CakePHP allows (see docs/DesignNotes.md for intentionally absent
 * Laravel-only areas: invokables, model binding keys, QUERY verb,
 * middleware URL defaults, domains).
 */
class Application extends BaseApplication
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(): void
    {
        parent::bootstrap();

        if (!Plugin::isLoaded('Crustum/Viator')) {
            $this->addPlugin('Crustum/Viator', [
                'bootstrap' => true,
                'routes' => true,
            ]);
        }

        // The built broadcaster (with its registered channels) survives
        // repeated boots; reconfiguring would drop it while
        // `Broadcasting::routes()` stays guarded by its once-only flag.
        if (!Broadcasting::getRegistry()->has('default')) {
            Broadcasting::setConfig('default', ['className' => 'Crustum/Broadcasting.Log']);
        }

        Broadcasting::routes();
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
        $routes->connect(
            '/users/{id}',
            ['controller' => 'Users', 'action' => 'view'],
            ['_name' => 'users:view', '_method' => 'GET'],
        );
        $routes->connect(
            '/',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'home'],
        );
        $routes->connect(
            '/archive/{year}',
            ['controller' => 'Demo', 'action' => 'archive', 'year' => '2024'],
            ['_name' => 'archive', '_method' => 'GET'],
        );
        $routes->connect(
            '/nested/child',
            ['controller' => 'Demo', 'action' => 'nested'],
            ['_name' => 'nested.child'],
        );
        $routes->connect(
            '/nested/child/grandchild',
            ['controller' => 'Demo', 'action' => 'nested'],
            ['_name' => 'nested.child.grandchild'],
        );

        // Workbench: closure-style named routes (routes-only, no actions).
        $routes->connect(
            '/dashboard',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'dashboard'],
        );
        $routes->connect(
            '/closure',
            ['controller' => 'Pages', 'action' => 'home'],
        );
        $routes->connect(
            '/export/{report}/{export}',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'export'],
        );
        $routes->connect(
            '/invalid-js-name',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'invalid#js@name'],
        );
        $routes->connect(
            '/package-route',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'my-package::store'],
        );
        $routes->connect(
            '/dashed-route',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'my-dashed-route'],
        );
        $routes->connect(
            '/projects/application/customer-sector',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'projects.application.customer-sector'],
        );
        $routes->connect(
            '/dashed-parent/admin/items',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'dashed-parent.admin.items'],
        );
        $routes->connect(
            '/api/v1/tasks',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'api.v1.tasks'],
        );
        $routes->connect(
            '/api/v1/tasks/{task}/task-status',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'api.v1.task-status.index'],
        );
        $routes->connect(
            '/photos',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'photos.index'],
        );
        $routes->connect(
            '/photos/window',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'photos.index.window'],
        );
        $routes->connect(
            '/albums/recent',
            ['controller' => 'Pages', 'action' => 'home'],
            ['_name' => 'albums.index.recent'],
        );

        // Workbench PostController REST group.
        $routes->connect(
            '/posts',
            ['controller' => 'Posts', 'action' => 'index'],
            ['_name' => 'posts.index', '_method' => 'GET'],
        );
        $routes->connect(
            '/posts/create',
            ['controller' => 'Posts', 'action' => 'create'],
            ['_name' => 'posts.create', '_method' => 'GET'],
        );
        $routes->connect(
            '/posts',
            ['controller' => 'Posts', 'action' => 'store'],
            ['_name' => 'posts.store', '_method' => 'POST'],
        );
        $routes->connect(
            '/posts/{post}',
            ['controller' => 'Posts', 'action' => 'show'],
            ['_name' => 'posts.show', '_method' => 'GET'],
        );
        $routes->connect(
            '/posts/{post}/edit',
            ['controller' => 'Posts', 'action' => 'edit'],
            ['_name' => 'posts.edit', '_method' => 'GET'],
        );
        $routes->connect(
            '/posts/{post}',
            ['controller' => 'Posts', 'action' => 'update'],
            ['_name' => 'posts.update', '_method' => 'PATCH'],
        );
        $routes->connect(
            '/posts/{post}',
            ['controller' => 'Posts', 'action' => 'destroy'],
            ['_name' => 'posts.destroy', '_method' => 'DELETE'],
        );

        // Workbench OptionalController.
        $routes->connect(
            '/optional/{parameter}',
            ['controller' => 'Optional', 'action' => 'optional'],
            ['_name' => 'optional', '_method' => 'POST'],
        );
        $routes->connect(
            '/many-optional/{one}/{two}/{three}',
            ['controller' => 'Optional', 'action' => 'manyOptional'],
            ['_method' => 'POST'],
        );

        // Shared URI under several verbs (invokable equivalent, unnamed).
        $routes->connect(
            '/shared-uri/{name}',
            ['controller' => 'SharedUri', 'action' => 'handle'],
            ['_method' => 'GET'],
        );
        $routes->connect(
            '/shared-uri/{name}',
            ['controller' => 'SharedUri', 'action' => 'handle'],
            ['_method' => 'POST'],
        );
        $routes->connect(
            '/shared-uri/{name}',
            ['controller' => 'SharedUri', 'action' => 'handle'],
            ['_method' => ['PUT', 'PATCH']],
        );

        // One action on two URLs (unnamed).
        $routes->connect(
            '/two-routes-one-action-1',
            ['controller' => 'TwoRoutesSameAction', 'action' => 'same'],
        );
        $routes->connect(
            '/two-routes-one-action-2',
            ['controller' => 'TwoRoutesSameAction', 'action' => 'same'],
        );

        // Parameter casing (unnamed explicit routes).
        $routes->connect(
            '/parameter-names/{camelCase}/camel',
            ['controller' => 'ParameterName', 'action' => 'camel'],
        );
        $routes->connect(
            '/parameter-names/{StudlyCase}/studly',
            ['controller' => 'ParameterName', 'action' => 'studly'],
        );
        $routes->connect(
            '/parameter-names/{snake_case}/snake',
            ['controller' => 'ParameterName', 'action' => 'snake'],
        );
        $routes->connect(
            '/parameter-names/{SCREAMING_SNAKE_CASE}/screaming-snake',
            ['controller' => 'ParameterName', 'action' => 'screamingSnake'],
        );

        // Reserved-word named routes (valid PHP targets, reserved names).
        $routes->connect(
            '/disallowed/delete',
            ['controller' => 'Disallowed', 'action' => 'delete'],
            ['_name' => 'disallowed.delete'],
        );
        $routes->connect(
            '/disallowed/404',
            ['controller' => 'Disallowed', 'action' => 'notFound'],
            ['_name' => 'disallowed.404'],
        );
        $routes->connect(
            '/disallowed/2fa',
            ['controller' => 'Disallowed', 'action' => 'twoFa'],
            ['_name' => 'disallowed.2fa'],
        );
        $routes->connect(
            '/disallowed/default',
            ['controller' => 'Disallowed', 'action' => 'login'],
            ['_name' => 'disallowed.default'],
        );
        $routes->connect(
            '/disallowed/public',
            ['controller' => 'Disallowed', 'action' => 'asset'],
            ['_name' => 'disallowed.public'],
        );
        $routes->connect(
            '/disallowed/static',
            ['controller' => 'Disallowed', 'action' => 'file'],
            ['_name' => 'disallowed.static'],
        );

        // URL defaults via route defaults (middleware equivalent).
        $routes->connect(
            '/with-defaults/{locale}',
            ['controller' => 'UrlDefaults', 'action' => 'onlyDefaults', 'locale' => 'en'],
            ['_method' => 'POST'],
        );
        $routes->connect(
            '/with-defaults/{locale}/also/{timezone}',
            ['controller' => 'UrlDefaults', 'action' => 'mixedDefaults', 'locale' => 'en'],
            ['_method' => 'POST'],
        );
        $routes->connect(
            '/with-quoted-defaults/{quoted}',
            ['controller' => 'UrlDefaults', 'action' => 'quotedDefaults', 'quoted' => 'say "hi" now'],
            ['_method' => 'POST'],
        );

        // Keys / model-binding equivalents (plain string params).
        $routes->connect('/keys/{key}', ['controller' => 'Key', 'action' => 'show']);
        $routes->connect('/keys/{key}/edit', ['controller' => 'Key', 'action' => 'edit']);
        $routes->connect('/users/{user}', ['controller' => 'ModelBinding', 'action' => 'show']);
        $routes->connect(
            '/users/by-token/{user}',
            ['controller' => 'ModelBinding', 'action' => 'nullableBinding'],
        );

        // Search (GET stands in for the QUERY verb).
        $routes->connect(
            '/search',
            ['controller' => 'Search', 'action' => 'index'],
            ['_name' => 'search.index', '_method' => 'GET'],
        );
        $routes->connect(
            '/search/{type}',
            ['controller' => 'Search', 'action' => 'show'],
            ['_name' => 'search.show', '_method' => 'GET'],
        );

        // Misc single-action routes.
        $routes->connect(
            '/navigation-items/{item}/options',
            ['controller' => 'NavigationItem', 'action' => 'options'],
        );
        $routes->connect(
            '/anonymous-middleware',
            ['controller' => 'AnonymousMiddleware', 'action' => 'show'],
        );
        $routes->connect(
            '/date-time',
            ['controller' => 'DateTime', 'action' => 'show'],
            ['_name' => 'date-time.show'],
        );
        $routes->connect(
            '/api/status',
            ['controller' => 'Api', 'action' => 'status'],
            ['_name' => 'api.status'],
        );
        $routes->connect(
            '/api/users',
            ['controller' => 'Api', 'action' => 'users'],
            ['_name' => 'api.users'],
        );

        // Inertia pages.
        $routes->connect(
            '/inertia/dashboard',
            ['controller' => 'Inertia', 'action' => 'dashboard'],
            ['_name' => 'inertia.dashboard'],
        );
        $routes->connect(
            '/inertia/settings',
            ['controller' => 'Inertia', 'action' => 'settings'],
            ['_name' => 'inertia.settings'],
        );
        $routes->connect(
            '/inertia/profile',
            ['controller' => 'Inertia', 'action' => 'profile'],
            ['_name' => 'inertia.profile'],
        );
        $routes->connect(
            '/inertia/unsafe',
            ['controller' => 'Inertia', 'action' => 'unsafe'],
            ['_name' => 'inertia.unsafe'],
        );
        $routes->connect(
            '/inertia/conditional',
            ['controller' => 'Inertia', 'action' => 'conditional'],
            ['_name' => 'inertia.conditional'],
        );
        $routes->connect(
            '/inertia/users-list',
            ['controller' => 'Inertia', 'action' => 'usersList'],
            ['_name' => 'inertia.users-list'],
        );
        $routes->connect(
            '/inertia/modular/login',
            ['controller' => 'ModularInertia', 'action' => 'login'],
            ['_name' => 'inertia.modular.login'],
        );
        $routes->connect(
            '/inertia/modular/register',
            ['controller' => 'ModularInertia', 'action' => 'register'],
            ['_name' => 'inertia.modular.register'],
        );
        $routes->connect(
            '/inertia/duplicate',
            ['controller' => 'DuplicateInertia', 'action' => 'duplicate'],
            ['_name' => 'inertia.duplicate'],
        );
        $routes->connect(
            '/inertia/duplicate-with-data',
            ['controller' => 'DuplicateInertia', 'action' => 'duplicateWithData'],
            ['_name' => 'inertia.duplicate.with-data'],
        );

        // Entity-backed product pages.
        $routes->connect(
            '/catalog-products',
            ['controller' => 'CatalogProduct', 'action' => 'index'],
            ['_name' => 'catalog-products.index'],
        );
        $routes->connect(
            '/catalog-products/{product}',
            ['controller' => 'CatalogProduct', 'action' => 'show'],
            ['_name' => 'catalog-products.show'],
        );

        // Resource-style routes.
        $routes->connect(
            '/products/{product}',
            ['controller' => 'ResourceTest', 'action' => 'show'],
            ['_name' => 'products.show'],
        );
        $routes->connect(
            '/products',
            ['controller' => 'ResourceTest', 'action' => 'index'],
            ['_name' => 'products.index'],
        );
        $routes->connect(
            '/products/wrapped',
            ['controller' => 'ResourceTest', 'action' => 'wrapped'],
            ['_name' => 'products.wrapped'],
        );
        $routes->connect(
            '/products/unwrapped',
            ['controller' => 'ResourceTest', 'action' => 'unwrapped'],
            ['_name' => 'products.unwrapped'],
        );
        $routes->connect(
            '/categories/json-api/{category}',
            ['controller' => 'ResourceTest', 'action' => 'jsonApi'],
            ['_name' => 'categories.jsonApi'],
        );
        $routes->connect(
            '/categories/json-api',
            ['controller' => 'ResourceTest', 'action' => 'jsonApiCollection'],
            ['_name' => 'categories.jsonApi.collection'],
        );
        $routes->connect(
            '/users-resource/{user}',
            ['controller' => 'ResourceTest', 'action' => 'user'],
            ['_name' => 'users-resource.show'],
        );
        $routes->connect(
            '/users-resource',
            ['controller' => 'ResourceTest', 'action' => 'users'],
            ['_name' => 'users-resource.index'],
        );
        $routes->connect(
            '/users-json-api/{user}',
            ['controller' => 'ResourceTest', 'action' => 'userJsonApi'],
            ['_name' => 'users-json-api.show'],
        );

        // Secrets (reveal is ViatorIgnore-d, route stays for routes/ parity).
        $routes->connect(
            '/secrets',
            ['controller' => 'Secrets', 'action' => 'index'],
            ['_name' => 'secrets.index'],
        );
        $routes->connect(
            '/secrets/reveal',
            ['controller' => 'Secrets', 'action' => 'reveal'],
            ['_name' => 'secrets.reveal'],
        );
        $routes->connect(
            '/secrets/resource',
            ['controller' => 'Secrets', 'action' => 'resource'],
            ['_name' => 'secrets.resource'],
        );

        // Fully ignored controller: the named route is dropped with it.
        $routes->connect(
            '/ignore-whole',
            ['controller' => IgnoreWholeController::class, 'action' => 'index'],
            ['_name' => 'ignore.whole'],
        );

        // Prefixed / namespaced groups.
        $routes->prefix('Nested', function (RouteBuilder $routes): void {
            $routes->connect(
                '/controller',
                ['controller' => 'Nested', 'action' => 'nested'],
            );
            $routes->connect(
                '/controller/child',
                ['controller' => 'Nested', 'action' => 'child'],
            );
            $routes->connect(
                '/controller/child/grandchild',
                ['controller' => 'Nested', 'action' => 'grandchild'],
            );
        });
        $routes->prefix('Mixed', function (RouteBuilder $routes): void {
            $routes->connect(
                '/items',
                ['controller' => 'Items', 'action' => 'index'],
                ['_name' => 'mixed.items'],
            );
            $routes->connect(
                '/items/{item}',
                ['controller' => 'Items', 'action' => 'edit'],
                ['_name' => 'mixed.items.edit'],
            );
            $routes->connect(
                '/items/{item}',
                ['controller' => 'Items', 'action' => 'update'],
                ['_name' => 'mixed.items.update', '_method' => 'PATCH'],
            );
        });
        $routes->prefix('Prism', function (RouteBuilder $routes): void {
            $routes->connect(
                '/prism',
                ['controller' => 'Prism', 'action' => 'index'],
                ['_name' => 'prism.index'],
            );
            $routes->prefix('Prism', function (RouteBuilder $routes): void {
                $routes->connect(
                    '/nested',
                    ['controller' => 'Prism', 'action' => 'nested'],
                    ['_name' => 'prism.prism.nested'],
                );
            });
        });

        $routes->prefix('Admin', function (RouteBuilder $routes): void {
            $routes->fallbacks(DashedRoute::class);
        });
        $routes->fallbacks(DashedRoute::class);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }
}
