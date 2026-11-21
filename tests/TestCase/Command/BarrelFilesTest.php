<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Command;

use Crustum\Viator\Routing\RouteDefinition;
use Crustum\Viator\Routing\Verb;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Barrel `index.ts` generation test.
 */
class BarrelFilesTest extends TestCase
{
    /**
     * Base output directory for generated files.
     *
     * @var string
     */
    protected string $outDir = '';

    /**
     * @var \Crustum\Viator\Test\TestCase\Command\BarrelRoutesGenerateCommand
     */
    protected BarrelRoutesGenerateCommand $command;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->outDir = str_replace('\\', '/', TMP . 'viator-barrels');
        $this->command = new BarrelRoutesGenerateCommand();
        $this->command->setBase($this->outDir);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->removeDir(TMP . 'viator-barrels');
        unset($this->command);

        parent::tearDown();
    }

    /**
     * Nested plugin groups get a barrel per branch directory.
     *
     * @return void
     */
    public function testNestedActionsGetBarrelPerBranch(): void
    {
        $groups = [
        'CakeDC.Admin.Admin' => [$this->definition('CakeDC.Admin.Admin', 'dashboard', '/admin/dashboard')],
        'CakeDC.Admin.Reports' => [$this->definition('CakeDC.Admin.Reports', 'overview', '/admin/reports')],
        'Monitor.Home' => [$this->definition('Monitor.Home', 'status', '/monitor')],
        ];

        foreach ($groups as $group => $definitions) {
            $this->command->makeControllerFile($definitions, $group);
        }

        $this->command->makeBarrels($groups, 'actions');
        $this->command->flush();

        $adminIndex = $this->outDir . '/actions/CakeDC/Admin/index.ts';
        $this->assertFileExists($adminIndex);
        $adminContents = (string)file_get_contents($adminIndex);
        $this->assertStringContainsString("import Admin from './Admin'", $adminContents);
        $this->assertStringContainsString("import Reports from './Reports'", $adminContents);
        // Child alias `Admin` collides with the directory export name.
        $this->assertStringContainsString('export default AdminNamespace', $adminContents);

        $pluginIndex = $this->outDir . '/actions/CakeDC/index.ts';
        $this->assertFileExists($pluginIndex);
        $pluginContents = (string)file_get_contents($pluginIndex);
        $this->assertStringContainsString("import Admin from './Admin'", $pluginContents);
        $this->assertStringContainsString('export default CakeDC', $pluginContents);

        $monitorIndex = $this->outDir . '/actions/Monitor/index.ts';
        $this->assertFileExists($monitorIndex);
        $monitorContents = (string)file_get_contents($monitorIndex);
        $this->assertStringContainsString("import Home from './Home'", $monitorContents);
        $this->assertStringContainsString('export default Monitor', $monitorContents);

        // No root barrel: flat groups never create one.
        $this->assertFileDoesNotExist($this->outDir . '/actions/index.ts');
    }

    /**
     * Nested dotted route names merge leaf methods with the branch barrel.
     *
     * @return void
     */
    public function testNestedRoutesMergeLeafWithBarrel(): void
    {
        $groups = [
        'admin.users.view' => [$this->namedDefinition('admin.users.view', 'view', '/admin/users/view')],
        'admin.users.edit' => [$this->namedDefinition('admin.users.edit', 'edit', '/admin/users/edit')],
        ];

        foreach ($groups as $group => $definitions) {
            $this->command->makeNamedFile($definitions, $group);
        }

        $this->command->makeBarrels($groups, 'routes');
        $this->command->flush();

        $leaf = $this->outDir . '/routes/admin/users/index.ts';
        $this->assertFileExists($leaf);
        $leafContents = (string)file_get_contents($leaf);
        $this->assertStringContainsString('export const view', $leafContents);
        $this->assertStringContainsString('export const edit', $leafContents);
        $this->assertStringContainsString('const users = {', $leafContents);
        $this->assertStringContainsString('export default users', $leafContents);

        $branch = $this->outDir . '/routes/admin/index.ts';
        $this->assertFileExists($branch);
        $branchContents = (string)file_get_contents($branch);
        $this->assertStringContainsString("import users from './users'", $branchContents);
        $this->assertStringContainsString('export default admin', $branchContents);
    }

    /**
     * A branch named `index` is imported by its full path (no self-import).
     *
     * @return void
     */
    public function testIndexBranchUsesFullImportPath(): void
    {
        $groups = [
        'a.index.b' => [$this->namedDefinition('a.index.b', 'b', '/a/b')],
        ];

        foreach ($groups as $group => $definitions) {
            $this->command->makeNamedFile($definitions, $group);
        }

        $this->command->makeBarrels($groups, 'routes');
        $this->command->flush();

        $branch = $this->outDir . '/routes/a/index.ts';
        $this->assertFileExists($branch);
        $branchContents = (string)file_get_contents($branch);
        $this->assertStringContainsString("import index from './index/index'", $branchContents);

        // The nested barrel merges with the leaf file of `a.index.b`.
        $nested = $this->outDir . '/routes/a/index/index.ts';
        $this->assertFileExists($nested);
        $nestedContents = (string)file_get_contents($nested);
        $this->assertStringContainsString('export const b', $nestedContents);
        $this->assertStringContainsString('export default index', $nestedContents);
    }

    /**
     * Genuine same-method collisions still produce a URI-keyed dictionary.
     *
     * @return void
     */
    public function testUnrelatedCollisionKeepsDictionary(): void
    {
        $groups = [
            'Demo' => [
                $this->definition('Demo', 'shared', '/demo/shared-a'),
                $this->definition('Demo', 'shared', '/demo/shared-b'),
            ],
        ];

        foreach ($groups as $group => $definitions) {
            $this->command->makeControllerFile($definitions, $group);
        }

        $this->command->makeBarrels($groups, 'actions');
        $paths = $this->command->flush();

        $this->assertContains($this->outDir . '/actions/Demo.ts', $paths);
        $contents = (string)file_get_contents($this->outDir . '/actions/Demo.ts');
        $this->assertStringContainsString('const shared = {', $contents);
        $this->assertStringContainsString('"/demo/shared-a"', $contents);
        $this->assertStringContainsString('"/demo/shared-b"', $contents);
    }

    /**
     * Build an actions/ definition.
     *
     * @param string $group Dotted group key
     * @param string $method Method name
     * @param string $uri URI template
     * @return \Crustum\Viator\Routing\RouteDefinition
     */
    protected function definition(string $group, string $method, string $uri): RouteDefinition
    {
        return new RouteDefinition(
            name: null,
            group: $group,
            jsMethod: $method,
            originalMethod: $method,
            controllerLabel: 'TestController',
            file: '/tmp/TestController.php',
            line: 1,
            parameters: [],
            verbs: [new Verb('GET')],
            uri: $uri,
        );
    }

    /**
     * Build a routes/ definition.
     *
     * @param string $name Dotted route name
     * @param string $method Method name
     * @param string $uri URI template
     * @return \Crustum\Viator\Routing\RouteDefinition
     */
    protected function namedDefinition(string $name, string $method, string $uri): RouteDefinition
    {
        return new RouteDefinition(
            name: $name,
            group: $name,
            jsMethod: $method,
            originalMethod: $method,
            controllerLabel: 'TestController',
            file: '/tmp/TestController.php',
            line: 1,
            parameters: [],
            verbs: [new Verb('GET')],
            uri: $uri,
        );
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
