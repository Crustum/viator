<?php
declare(strict_types=1);

namespace Crustum\Viator\Converter;

use Cake\Collection\CollectionInterface;
use Crustum\Viator\Result\Result;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * `VITE_*` environment variables to `vite-env.d.ts`.
 *
 * Names come from Scrutator `EnvironmentVariable` components (dotenv
 * files plus `import.meta.env.VITE_*` usages collected on the walk).
 * All values type as `string` (environment values are strings).
 *
 * Returns null when nothing is found or when the application already
 * declares `ImportMetaEnv` itself (merging a second declaration would
 * conflict with the app's own index signature).
 */
final class EnvironmentVariables
{
    /**
     * @param string $jsDir Host JavaScript root (scanned for existing declarations)
     */
    public function __construct(
        protected string $jsDir = '',
    ) {
    }

    /**
     * Build the `vite-env.d.ts` result, if any.
     *
     * @param \Cake\Collection\CollectionInterface<array-key, \Crustum\Scrutator\Component\EnvironmentVariable>|null $variables Scrutator components, if the command collected them
     * @return \Crustum\Viator\Result\Result|null
     */
    public function convert(?CollectionInterface $variables = null): ?Result
    {
        $names = [];

        if ($variables instanceof CollectionInterface) {
            foreach ($variables as $variable) {
                if (self::isViteKey($variable->key)) {
                    $names[$variable->key] = true;
                }
            }
        }

        if ($names === [] || $this->appDeclaresEnv()) {
            return null;
        }

        $names = array_keys($names);
        sort($names);

        $lines = ['/// <reference types="vite/client" />', '', 'interface ImportMetaEnv {'];
        foreach ($names as $name) {
            $lines[] = "    readonly {$name}: string;";
        }

        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'interface ImportMeta {';
        $lines[] = '    readonly env: ImportMetaEnv;';
        $lines[] = '}';

        return new Result('vite-env.d.ts', implode("\n", $lines) . "\n");
    }

    /**
     * Whether a dotenv key belongs in `vite-env.d.ts`.
     *
     * @param string $key Dotenv key
     * @return bool
     */
    protected static function isViteKey(string $key): bool
    {
        if (!str_starts_with($key, 'VITE_')) {
            return false;
        }

        $suffix = substr($key, strlen('VITE_'));

        return $suffix !== '' && strspn($suffix, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_') === strlen($suffix);
    }

    /**
     * Check whether the application declares `ImportMetaEnv` itself.
     *
     * @return bool
     */
    protected function appDeclaresEnv(): bool
    {
        if ($this->jsDir === '' || !is_dir($this->jsDir)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->jsDir, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'ts') {
                continue;
            }

            if ($file->getFilename() === 'vite-env.d.ts') {
                continue;
            }

            $code = file_get_contents($file->getPathname());
            if (is_string($code) && str_contains($code, 'ImportMetaEnv')) {
                return true;
            }
        }

        return false;
    }
}
