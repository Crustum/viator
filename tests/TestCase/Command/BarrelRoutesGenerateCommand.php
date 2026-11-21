<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Command;

use Crustum\Viator\Converter\Routes;

/**
 * Exposes the routes converter emit pipeline for barrel tests.
 */
class BarrelRoutesGenerateCommand
{
    /**
     * @var \Crustum\Viator\Converter\Routes
     */
    protected Routes $routes;

    /**
     * @var string
     */
    protected string $base = '';

    public function __construct()
    {
        $this->routes = new Routes();
    }

    /**
     * @param string $base Base output path
     * @return void
     */
    public function setBase(string $base): void
    {
        $this->base = $base;
    }

    /**
     * @param array<int, \Crustum\Viator\Routing\RouteDefinition> $routes Group definitions
     * @param string $namespace Dotted group key
     * @return void
     */
    public function makeControllerFile(array $routes, string $namespace): void
    {
        $this->routes->writeControllerFile($routes, $namespace, false);
    }

    /**
     * @param array<int, \Crustum\Viator\Routing\RouteDefinition> $routes Group definitions
     * @param string $namespace Dotted route name
     * @return void
     */
    public function makeNamedFile(array $routes, string $namespace): void
    {
        $this->routes->writeNamedFile($routes, $namespace, false);
    }

    /**
     * @param array<string, array<int, mixed>> $grouped Flat groups
     * @param string $dir Output subdirectory
     * @return void
     */
    public function makeBarrels(array $grouped, string $dir): void
    {
        $this->routes->writeBarrels($grouped, $dir);
    }

    /**
     * @return array<int, string> Written paths
     */
    public function flush(): array
    {
        $written = [];
        foreach ($this->routes->flush() as $result) {
            $path = $this->base . '/' . $result->name;
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($path, $result->content);
            $written[] = $path;
        }

        return $written;
    }
}
