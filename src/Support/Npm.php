<?php
declare(strict_types=1);

namespace Crustum\Viator\Support;

/**
 * Installed npm package lookup.
 *
 * Reads the host application `package.json` (defaults to the current
 * working directory so tests can point it anywhere).
 */
final class Npm
{
    /**
     * Check whether a package is a dependency of the host application.
     *
     * @param string $packageName Package name, e.g. `@laravel/echo-vue`
     * @param string|null $baseDir Directory holding `package.json`
     * @return bool
     */
    public static function isInstalled(string $packageName, ?string $baseDir = null): bool
    {
        $manifest = self::manifest($baseDir);
        if (!is_array($manifest)) {
            return false;
        }

        foreach (['dependencies', 'devDependencies'] as $section) {
            if (isset($manifest[$section][$packageName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * First installed package from a preference-ordered list.
     *
     * @param array<int, string> $packageNames Package names in preference order
     * @param string|null $baseDir Directory holding `package.json`
     * @return string|null
     */
    public static function findFirstInstalledPackage(array $packageNames, ?string $baseDir = null): ?string
    {
        foreach ($packageNames as $packageName) {
            if (self::isInstalled($packageName, $baseDir)) {
                return $packageName;
            }
        }

        return null;
    }

    /**
     * Decode the host application `package.json`.
     *
     * @param string|null $baseDir Directory holding `package.json`
     * @return array<string, mixed>|null
     */
    protected static function manifest(?string $baseDir): ?array
    {
        $path = rtrim($baseDir ?? (string)getcwd(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'package.json';
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string)file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
