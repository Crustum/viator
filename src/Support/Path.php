<?php
declare(strict_types=1);

namespace Crustum\Viator\Support;

/**
 * Base/app path registry for docblock links and file resolution.
 *
 * Built on CakePHP constants (`ROOT` for the host application, `APP` for
 * its `src/` directory).
 */
final class Path
{
    /**
     * @var array<int, string>
     */
    protected static array $basePaths = [];

    /**
     * @var array<int, string>
     */
    protected static array $appPaths = [];

    /**
     * @param string ...$paths Base paths, e.g. the host application root
     * @return void
     */
    public static function setBasePaths(string ...$paths): void
    {
        self::$basePaths = array_values($paths);
    }

    /**
     * @param string ...$paths Application source paths, e.g. `APP`
     * @return void
     */
    public static function setAppPaths(string ...$paths): void
    {
        self::$appPaths = array_values($paths);
    }

    /**
     * Default paths (host application root and `src/`).
     *
     * @return void
     */
    public static function useDefaults(): void
    {
        if (defined('ROOT')) {
            self::$basePaths = [ROOT];
        }

        if (defined('APP')) {
            self::$appPaths = [APP];
        }
    }

    /**
     * First base path holding the relative path, if any.
     *
     * @param string $path Relative path, e.g. `routes/channels.php`
     * @return string|null Absolute path
     */
    public static function firstFromBasePath(string $path): ?string
    {
        foreach (self::$basePaths as $basePath) {
            $candidate = rtrim($basePath, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * First app path holding the relative path, if any.
     *
     * @param string $path Relative path, e.g. `Event/VoteCast.php`
     * @return string|null Absolute path
     */
    public static function firstFromAppPath(string $path): ?string
    {
        foreach (self::$appPaths as $appPath) {
            $candidate = rtrim($appPath, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
