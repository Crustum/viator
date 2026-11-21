<?php
declare(strict_types=1);

namespace Crustum\Viator\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Generated-file operations.
 *
 * Writing skips unchanged files so watchers stay quiet; pruning deletes
 * generated files missing from the current run, bottom-up.
 */
final class Files
{
    /**
     * Write a file only when the content changed.
     *
     * @param string $path File path
     * @param string $content Content
     * @return void
     */
    public static function writeContentIfChanged(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        if (!is_file($path) || file_get_contents($path) !== $content) {
            file_put_contents($path, $content);
        }
    }

    /**
     * Delete stale files under one output root.
     *
     * @param string $base Base directory
     * @param array<int, string> $roots Top-level segments holding generated files
     * @param array<int, string> $written Written paths
     * @return void
     */
    public static function pruneTrees(string $base, array $roots, array $written): void
    {
        foreach ($roots as $root) {
            $dir = $base . '/' . $root;
            /** @var array<int, string> $kept */
            $kept = collection($written)
                ->filter(static fn(string $path): bool => str_starts_with($path, $dir . '/'))
                ->toList();

            self::pruneStaleFiles($dir, $kept);
        }
    }

    /**
     * Delete generated files that were not written in this run.
     *
     * @param string $base Base directory
     * @param array<int, string> $writtenPaths Written paths
     * @return void
     */
    public static function pruneStaleFiles(string $base, array $writtenPaths): void
    {
        if (!is_dir($base)) {
            return;
        }

        $kept = [];
        foreach ($writtenPaths as $path) {
            $real = realpath($path);
            $kept[self::normalizeSeparators($real !== false ? $real : $path)] = true;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            $pathname = self::normalizeSeparators($file->getPathname());
            if ($file->isFile() && !isset($kept[$pathname])) {
                unlink($file->getPathname());
            }
        }

        self::pruneEmptyDirectories($base);
    }

    /**
     * Normalize directory separators for path comparison.
     *
     * `realpath()` returns backslashes on Windows while generated paths use
     * forward slashes — compare normalized.
     *
     * @param string $path Path
     * @return string
     */
    public static function normalizeSeparators(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Delete empty directories bottom-up.
     *
     * @param string $dir Directory
     * @return void
     */
    protected static function pruneEmptyDirectories(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . DS . '*', GLOB_ONLYDIR) ?: [] as $sub) {
            self::pruneEmptyDirectories($sub);
        }

        $isEmpty = true;
        foreach (glob($dir . DS . '*') ?: [] as $entry) {
            $isEmpty = false;
            break;
        }

        if ($isEmpty) {
            rmdir($dir);
        }
    }
}
