<?php
declare(strict_types=1);

namespace Crustum\Viator\Test\TestCase\Converter;

use Crustum\Scrutator\Component\EnvironmentVariable;
use Crustum\Viator\Converter\EnvironmentVariables;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * EnvironmentVariables Scrutator-consumer test.
 */
final class EnvironmentVariablesScrutatorTest extends TestCase
{
    /**
     * @var string Temp JS root
     */
    protected string $js = '';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->js = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'viator-env-scr-' . uniqid();
        mkdir($this->js, 0777, true);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->js, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->js);
        parent::tearDown();
    }

    /**
     * Scrutator components feed names, values stay string.
     *
     * @return void
     */
    public function testComponentsFeedNames(): void
    {
        file_put_contents($this->js . DIRECTORY_SEPARATOR . 'app.ts', "console.log('hi');\n");

        $variables = collection([
            new EnvironmentVariable('VITE_APP_NAME', 'demo'),
            new EnvironmentVariable('VITE_EXTRA', 1),
            new EnvironmentVariable('NOT_VITE', 'x'),
        ]);

        $result = (new EnvironmentVariables($this->js))->convert($variables);

        $this->assertNotNull($result);
        $this->assertSame('vite-env.d.ts', $result->name);
        $this->assertStringContainsString('readonly VITE_APP_NAME: string;', $result->content);
        $this->assertStringContainsString('readonly VITE_EXTRA: string;', $result->content);
        $this->assertStringNotContainsString('NOT_VITE', $result->content);
    }
}
