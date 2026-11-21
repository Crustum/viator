<?php
declare(strict_types=1);

/**
 * Viator Plugin Configuration
 *
 * Host applications copy settings into `config/viator.php` and load via
 * `Configure::load('viator')`. Keys are read as `Configure::read('Viator.*')`.
 */
return [
    'Viator' => [
        // Base output path for `bin/cake viator routes` (defaults to ROOT/resources/js).
        'output' => null,
        // Plugins whose controllers are scanned in addition to the host app.
        // Same as repeating `--plugin`. Vendor plugins are NOT scanned by default.
        'scanPlugins' => [],
        // Action names to skip during the controller scan (per-app trait
        // helpers like `fetchCollection`, never real actions). Plain names
        // apply to every controller, `Controller::method` pairs are scoped.
        // Example: `['fetchCollection', 'getPaginate', 'Admin:Users:export']`.
        'ignoreActions' => [],
        // Entity directories scanned for `Model.Entity` types. Defaults to
        // the host `src/Model/Entity` plus each `--plugin` / `scanPlugins`
        // plugin's `src/Model/Entity`. Set explicitly to override.
        // Example: `[APP . 'Model' . DS . 'Entity']`.
        'entityDirs' => null,
        // Analysis cache. Disk caching speeds up repeat runs; `--fresh`
        // clears the cache before generating.
        'cache' => [
            'enabled' => true,
            'directory' => null,
        ],
    ],
];
