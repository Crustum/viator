# Vite plugin for Viator

Vite plugin for [Crustum Viator](https://github.com/crustum/viator) (CakePHP routes to TypeScript).

```ts
import { viator } from "@crustum/vite-plugin-viator";

export default defineConfig({
    plugins: [
        viator(),
        // ...
    ],
});
```

All options have sensible defaults, but should you need to customize anything:

```ts
import { viator } from "@crustum/vite-plugin-viator";

export default defineConfig({
    plugins: [
        viator({
            path: "resources/js",
            command: "php bin/cake.php viator routes",
            routes: false,
            actions: true,
            formVariants: true,
            plugins: ["CakeDC/Admin", "Monitor"],
            patterns: ["config/routes.php", "src/Controller/**/*.php"],
        }),
        // ...
    ],
});
```

## Options

| Option | Default | Description |
|---|---|---|
| `patterns` | `config/routes.php`, `src/Controller/**/*.php`, `plugins/*/src/Controller/**/*.php` | Watched files that trigger regeneration |
| `actions` | `true` | Generate `actions/` (set `false` to pass `--skip-actions`) |
| `routes` | `true` | Generate `routes/` (set `false` to pass `--skip-routes`) |
| `formVariants` | `false` | Generate `.form` variants (`--with-form`) |
| `path` | generator default | Base output path (`--path=`) |
| `command` | `php bin/cake.php viator routes` | Generator invocation (e.g. with `herd`/`valet` prefix) |
| `plugins` | `[]` | CakePHP plugins to scan (`--plugin=A,B`) |

There is no `skipDatabase` option: Viator never
touches the database.
