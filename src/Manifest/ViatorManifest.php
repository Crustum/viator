<?php
declare(strict_types=1);

namespace Crustum\Viator\Manifest;

use Crustum\PluginManifest\Manifest\ManifestOps;
use Crustum\Viator\ViatorPlugin;

/**
 * Standalone manifest provider for Crustum/Viator.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class ViatorManifest
{
    /**
     * Plugin install assets via crustum/plugin-manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(ViatorPlugin::class);

        return array_merge(
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'viator.php',
                CONFIG . 'viator.php',
                false,
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "if (file_exists(CONFIG . 'viator.php')) {\n    Configure::load('viator', 'default');\n}",
                '// Viator Plugin Configuration',
            ),
            ManifestOps::manifestStarRepo($plugin, 'Crustum/Viator'),
        );
    }
}
