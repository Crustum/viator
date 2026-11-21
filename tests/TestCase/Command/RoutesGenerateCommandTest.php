<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use TestApp\Application;

/**
 * Routes generate command test.
 */
class RoutesGenerateCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    /**
     * Output directory for generated files.
     *
     * @var string
     */
    protected string $outDir = '';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configApplication(Application::class, [CONFIG]);
        $this->outDir = TMP . 'viator-routes' . DS;
        Configure::write('Viator.ignoreActions', ['fetchCollection']);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        Router::reload();
        Configure::delete('Viator.ignoreActions');
        $this->removeDir($this->outDir);

        parent::tearDown();
    }

    /**
     * The command generates actions/, routes/ and the runtime.
     *
     * @return void
     */
    public function testGenerateWritesExpectedFiles(): void
    {
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir));

        $this->assertExitSuccess();
        $this->assertFileExists($this->outDir . 'viator' . DS . 'index.ts');

        $actionFile = $this->outDir . 'actions' . DS . 'Demo.ts';
        $this->assertFileExists($actionFile);
        $contents = (string)file_get_contents($actionFile);
        $this->assertStringContainsString('export const view', $contents);
        $this->assertStringContainsString('"/demo/view/{id}"', $contents);
        $this->assertStringContainsString('export const edit', $contents);
        $this->assertStringContainsString('export const shared', $contents);
        $this->assertStringContainsString('export const add', $contents);
        $this->assertStringContainsString('export const deleteMethod', $contents);
        $this->assertStringContainsString('add.post', $contents);
        $this->assertStringContainsString('deleteMethod.delete', $contents);
        $this->assertStringContainsString('"delete"', $contents);
        // `index` matches the `/{controller}` and `/{controller}/{action}/*`
        // fallback pair, so the root URL keeps `index` and the explicit one
        // becomes `indexExplicit` instead of a URI-keyed dictionary.
        $this->assertStringContainsString('export const index = ', $contents);
        $this->assertStringContainsString('export const indexExplicit = ', $contents);
        $this->assertStringContainsString('"/demo"', $contents);
        $this->assertStringContainsString('"/demo/index"', $contents);
        $this->assertStringNotContainsString('const index = {', $contents);
        $this->assertStringContainsString('export default Demo', $contents);
        $this->assertStringContainsString("from '../viator'", $contents);
        $this->assertStringNotContainsString("\r", $contents);
        $this->assertStringNotContainsString('fetchCollection', $contents);
        $this->assertStringNotContainsString('beforeFilter', $contents);
        $this->assertFileDoesNotExist($this->outDir . 'actions' . DS . 'DemoApp.ts');

        $routeFile = $this->outDir . 'routes' . DS . 'users' . DS . 'index.ts';
        $this->assertFileExists($routeFile);
        $routeContents = (string)file_get_contents($routeFile);
        $this->assertStringContainsString('export const view', $routeContents);
        $this->assertStringContainsString('/users/{id}', $routeContents);
    }

    /**
     * Prefixed controllers and nested route names produce barrels.
     *
     * @return void
     */
    public function testGenerateWritesNestedBarrels(): void
    {
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir));

        $this->assertExitSuccess();

        $adminFile = $this->outDir . 'actions' . DS . 'Admin' . DS . 'Dashboard.ts';
        $this->assertFileExists($adminFile);
        $adminContents = (string)file_get_contents($adminFile);
        $this->assertStringContainsString('export const overview', $adminContents);
        $this->assertStringContainsString('/admin/dashboard/overview', $adminContents);

        $adminIndex = $this->outDir . 'actions' . DS . 'Admin' . DS . 'index.ts';
        $this->assertFileExists($adminIndex);
        $this->assertStringContainsString(
            "import Dashboard from './Dashboard'",
            (string)file_get_contents($adminIndex),
        );

        $nestedLeaf = $this->outDir . 'routes' . DS . 'nested' . DS . 'child' . DS . 'index.ts';
        $this->assertFileExists($nestedLeaf);
        $nestedContents = (string)file_get_contents($nestedLeaf);
        $this->assertStringContainsString('export const grandchild', $nestedContents);
        $this->assertStringContainsString('const child = {', $nestedContents);
        $this->assertStringContainsString('export default child', $nestedContents);

        $homeFile = $this->outDir . 'routes' . DS . 'index.ts';
        $this->assertFileExists($homeFile);
        $this->assertStringContainsString('"/"', (string)file_get_contents($homeFile));
    }

    /**
     * Form variants generate by default and opt out with `--no-with-form`.
     *
     * @return void
     */
    public function testGenerateWithFormVariants(): void
    {
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir));

        $this->assertExitSuccess();

        $contents = (string)file_get_contents($this->outDir . 'actions' . DS . 'Demo.ts');
        $this->assertStringContainsString('RouteFormDefinition', $contents);
        $this->assertStringContainsString('deleteMethodForm', $contents);
        $this->assertStringContainsString('deleteMethod.form', $contents);

        $this->exec('viator routes --path ' . escapeshellarg($this->outDir) . ' --no-with-form');

        $this->assertExitSuccess();

        $contents = (string)file_get_contents($this->outDir . 'actions' . DS . 'Demo.ts');
        $this->assertStringNotContainsString('deleteMethod.form', $contents);
    }

    /**
     * The `--except` flag drops matching action groups and named routes.
     *
     * @return void
     */
    public function testGenerateExceptFiltersGroupsAndNames(): void
    {
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir) . ' --except=Admin.*');

        $this->assertExitSuccess();
        $this->assertFileDoesNotExist($this->outDir . 'actions' . DS . 'Admin' . DS . 'Dashboard.ts');
        $this->assertFileExists($this->outDir . 'actions' . DS . 'Demo.ts');
    }

    /**
     * The `--only` flag keeps matching groups; `!` negates within the list.
     *
     * @return void
     */
    public function testGenerateOnlyWithNegation(): void
    {
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir) . ' --only=Demo,Admin.*,!Admin.Dashboard');

        $this->assertExitSuccess();
        $this->assertFileExists($this->outDir . 'actions' . DS . 'Demo.ts');
        $this->assertFileDoesNotExist($this->outDir . 'actions' . DS . 'Admin' . DS . 'Dashboard.ts');
        $this->assertFileDoesNotExist($this->outDir . 'routes');
    }

    /**
     * `Viator.only` / `Viator.except` config applies when flags are absent.
     *
     * @return void
     */
    public function testGenerateFilterConfigFallback(): void
    {
        Configure::write('Viator.except', ['Admin.*']);
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir));

        $this->assertExitSuccess();
        $this->assertFileDoesNotExist($this->outDir . 'actions' . DS . 'Admin' . DS . 'Dashboard.ts');
        $this->assertFileExists($this->outDir . 'actions' . DS . 'Demo.ts');

        Configure::delete('Viator.except');
    }

    /**
     * `Viator.urlDefaults` config is baked into the runtime.
     *
     * @return void
     */
    public function testGenerateBakesUrlDefaults(): void
    {
        Configure::write('Viator.urlDefaults', ['locale' => 'en']);
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir));

        $this->assertExitSuccess();
        $this->assertStringContainsString(
            'setUrlDefaults({"locale":"en"});',
            (string)file_get_contents($this->outDir . 'viator' . DS . 'index.ts'),
        );

        Configure::delete('Viator.urlDefaults');
    }

    /**
     * `--fresh` clears the analysis cache before generating.
     *
     * @return void
     */
    public function testGenerateFreshClearsCache(): void
    {
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir) . ' --fresh');

        $this->assertExitSuccess();
        $this->assertFileExists($this->outDir . 'actions' . DS . 'Demo.ts');
        $this->assertFileExists($this->outDir . 'viator' . DS . 'types.d.ts');
    }

    /**
     * Routes connected programmatically (the way attribute routing
     * materialises them) are picked up: the generator reads only the
     * final `Router::routes()` collection, never `routes.php` itself.
     *
     * @return void
     */
    public function testGeneratePicksUpProgrammaticallyConnectedRoutes(): void
    {
        $app = (new class (CONFIG) extends Application {
            /**
             * @inheritDoc
             */
            #[Override]
            public function routes(RouteBuilder $routes): void
            {
                parent::routes($routes);
                $routes->connect(
                    '/attr/{slug}',
                    ['controller' => 'Demo', 'action' => 'view'],
                    ['_name' => 'attr.show', '_method' => 'GET'],
                );
            }
        })::class;
        $this->configApplication($app, [CONFIG]);
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir));

        $this->assertExitSuccess();

        $file = $this->outDir . 'routes' . DS . 'attr' . DS . 'index.ts';
        $this->assertFileExists($file);
        $this->assertStringContainsString('export const show', (string)file_get_contents($file));
        $this->assertStringContainsString('/attr/{slug}', (string)file_get_contents($file));
    }

    /**
     * Workbench-ported actions generate the expected files and dicts.
     *
     * @return void
     */
    public function testGenerateWorkbenchFixtures(): void
    {
        $this->exec('viator routes --path ' . escapeshellarg($this->outDir));

        $this->assertExitSuccess();

        $posts = (string)file_get_contents($this->outDir . 'actions' . DS . 'Posts.ts');
        $this->assertStringContainsString('export const index = ', $posts);
        $this->assertStringContainsString('export const indexExplicit = ', $posts);
        $this->assertStringContainsString('"/posts"', $posts);
        $this->assertStringContainsString('export const create', $posts);
        $this->assertStringContainsString('export const destroy', $posts);

        $shared = (string)file_get_contents($this->outDir . 'actions' . DS . 'SharedUri.ts');
        $this->assertStringContainsString('"/shared-uri/{name}"', $shared);
        $this->assertStringContainsString('export default SharedUri', $shared);

        $two = (string)file_get_contents($this->outDir . 'actions' . DS . 'TwoRoutesSameAction.ts');
        $this->assertStringContainsString('"/two-routes-one-action-1"', $two);
        $this->assertStringContainsString('"/two-routes-one-action-2"', $two);

        $params = (string)file_get_contents($this->outDir . 'actions' . DS . 'ParameterName.ts');
        $this->assertStringContainsString('{camelCase}', $params);
        $this->assertStringContainsString('{StudlyCase}', $params);

        $optional = (string)file_get_contents($this->outDir . 'actions' . DS . 'Optional.ts');
        $this->assertStringContainsString('{parameter?}', $optional);
        $this->assertStringContainsString('{three?}', $optional);

        $this->assertFileExists($this->outDir . 'actions' . DS . 'Mixed' . DS . 'Items.ts');
        $this->assertFileExists($this->outDir . 'actions' . DS . 'Nested' . DS . 'Nested.ts');
        $this->assertFileExists($this->outDir . 'actions' . DS . 'Prism' . DS . 'Prism.ts');
        $this->assertFileExists(
            $this->outDir . 'actions' . DS . 'Prism' . DS . 'Prism' . DS . 'Prism.ts',
        );

        $disallowed = (string)file_get_contents($this->outDir . 'routes' . DS . 'disallowed' . DS . 'index.ts');
        $this->assertStringContainsString('export const method404', $disallowed);
        $this->assertStringContainsString('export const defaultMethod', $disallowed);
        $this->assertStringContainsString('export const publicMethod', $disallowed);
        $this->assertStringContainsString('export const staticMethod', $disallowed);
        $this->assertStringContainsString('export const deleteMethod', $disallowed);
        $this->assertStringContainsString('"2fa":', $disallowed);

        $types = (string)file_get_contents($this->outDir . 'viator' . DS . 'types.d.ts');
        $this->assertStringContainsString('export type ProductStatus = ', $types);
        $this->assertStringContainsString('export type StorePostForm = {', $types);
        $this->assertStringContainsString('export type Dashboard = SharedData & {', $types);
        $this->assertStringContainsString('export namespace Items {', $types);
        $this->assertStringContainsString('export namespace TestApp {', $types);
        $this->assertStringContainsString('export namespace Model {', $types);
        $this->assertStringContainsString('export namespace Entity {', $types);
        $this->assertStringContainsString('export type User = {', $types);
        $this->assertStringNotContainsString('namespace Models', $types);
        $this->assertStringContainsString('export type UnitEnum = 0 | 1 | 2;', $types);
        $this->assertStringContainsString('@see [\\TestApp\\Enum\\PostStatus]', $types);
        $this->assertStringContainsString('format: TestApp.Enum.QuotedEnum;', $types);

        $postStatus = (string)file_get_contents($this->outDir . 'TestApp' . DS . 'Enum' . DS . 'PostStatus.ts');
        $this->assertStringContainsString('export const Draft = "draft"', $postStatus);
        $this->assertStringContainsString('export const PostStatus = { Draft, Published, Archived } as const', $postStatus);
        $this->assertStringContainsString('export default PostStatus', $postStatus);
        $this->assertStringNotContainsString('Meta', $postStatus);

        $productStatus = (string)file_get_contents($this->outDir . 'TestApp' . DS . 'Enum' . DS . 'ProductStatus.ts');
        $this->assertStringContainsString('export const ProductStatus = { new: "new", used, for: "for-sale", Active } as const', $productStatus);

        $events = (string)file_get_contents($this->outDir . 'viator' . DS . 'broadcast-events.ts');
        $this->assertStringContainsString('TestApp\\\\Event\\\\OrderShipped', $events);
        $this->assertStringContainsString('user.notification', $events);

        $channels = (string)file_get_contents($this->outDir . 'viator' . DS . 'broadcast-channels.ts');
        $this->assertStringContainsString('export type BroadcastChannel = "chat.{roomId}.messages" | "orders.{orderId}" | "public-announcements" | "quirky.`tick`-$dollar.{roomId}" | "user.{userId}.notifications";', $channels);
        $this->assertStringContainsString('orders: (orderId: string | number) => `orders.${orderId}`,', $channels);
        $this->assertStringContainsString('user: (userId: string | number) => ({', $channels);
        $this->assertStringContainsString('"public-announcements": `public-announcements`,', $channels);
    }

    /**
     * Remove a directory recursively.
     *
     * @param string $dir Directory
     * @return void
     */
    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
