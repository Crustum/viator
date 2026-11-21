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
 * EnvironmentVariables converter test.
 */
final class EnvironmentVariablesTest extends TestCase
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
        $this->js = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'viator-env-' . uniqid();
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
     * Usages and dotenv keys merge into vite-env.d.ts.
     *
     * @return void
     */
    public function testConvert(): void
    {
        $variables = collection([
            new EnvironmentVariable('VITE_API_URL', 'https://example.test'),
            new EnvironmentVariable('VITE_EXTRA', 1),
            new EnvironmentVariable('VITE_APP_NAME', 'demo'),
            new EnvironmentVariable('NOT_VITE', 'x'),
        ]);

        $result = (new EnvironmentVariables($this->js))->convert($variables);

        $this->assertNotNull($result);
        $this->assertSame('vite-env.d.ts', $result->name);
        $this->assertStringContainsString('/// <reference types="vite/client" />', $result->content);
        $this->assertStringContainsString('readonly VITE_APP_NAME: string;', $result->content);
        $this->assertStringContainsString('readonly VITE_API_URL: string;', $result->content);
        $this->assertStringContainsString('readonly VITE_EXTRA: string;', $result->content);
        $this->assertStringNotContainsString('NOT_VITE', $result->content);
    }

    /**
     * Existing ImportMetaEnv declarations suppress generation.
     *
     * @return void
     */
    public function testSkipsWhenAppDeclaresEnv(): void
    {
        file_put_contents(
            $this->js . DIRECTORY_SEPARATOR . 'app.ts',
            "const name = import.meta.env.VITE_APP_NAME;\n",
        );
        file_put_contents(
            $this->js . DIRECTORY_SEPARATOR . 'global.d.ts',
            "declare module 'vite/client' {\n    interface ImportMetaEnv {\n        readonly VITE_APP_NAME: string;\n    }\n}\n",
        );

        $this->assertNull((new EnvironmentVariables($this->js))->convert(collection([
            new EnvironmentVariable('VITE_APP_NAME', 'demo'),
        ])));
    }

    /**
     * Nothing found yields nothing.
     *
     * @return void
     */
    public function testEmptyYieldsNull(): void
    {
        $this->assertNull((new EnvironmentVariables($this->js))->convert());
        $this->assertNull((new EnvironmentVariables($this->js))->convert(collection([])));
    }
}
