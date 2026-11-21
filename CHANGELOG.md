# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0]

Initial release of `crustum/viator` (`Crustum\Viator`).

Generates typed TypeScript route functions, entity/form/enum types, Inertia and broadcast types, and Vite env declarations from CakePHP sources.

### Added

- `bin/cake viator routes` command generating `actions/`, `routes/`, `viator/` (`index.ts` runtime, `types.d.ts`, broadcast files), `vite-env.d.ts`, and per-enum files following the PHP namespace
- Action functions resolved against Cake route templates via `Route::match()`, grouped by dotted `Plugin.Prefix.Controller` with `index.ts` barrels; root `index` / `indexExplicit` split pair and URI-keyed dictionary exports with verb-prefixed keys for remaining collisions
- Route helpers with `.url()`, `query` / `mergeQuery` serialization, verb-specific variants, and opt-out `.form` variants with `_method` spoofing; `applyUrlDefaults()` / `validateParameters()` runtime with `Viator.urlDefaults` baked in as `setUrlDefaults()`
- Named-route files organized by dotted route name with one `index.ts` per group plus barrels; `--only` / `--except` dotted patterns (`*` wildcards, `!` negation) and `Viator.ignoreActions` skipping
- `types.d.ts` assembly: entity interfaces, enum unions, Cake `*Form` validator types, Inertia shared data plus page types, and per-action resource / JSON:API `Response` unions
- Per-enum TypeScript files (named constants, const object with `as const`, default export); opt-in `Viator.enumMethods` resolving no-argument methods into `NameMeta` constants keyed by case value
- Inertia page types collected from scanned controller actions and shared-data components, nested on `/`, `::`, `.`, `\` separators
- Broadcast channel builders (`BroadcastChannels` const with `{param}` arrow functions) when `crustum/broadcasting` is loaded, and broadcast event types with verbatim wire names plus `echo-broadcast-events.d.ts` when `@laravel/echo-vue` / `@laravel/echo-react` is installed
- `vite-env.d.ts` for `VITE_*` keys from `Viator.envFiles` plus collected `import.meta.env` usages, skipped when the app declares `ImportMetaEnv` itself
- `ViatorIgnore` attribute plus `@viator-ignore` payload-key markers with `unless` / `when` conditions answered by `Configure::read()` or `[class, method]` callables
- Plugin bootstrap, config (`config/viator.php`), and standalone `Manifest\ViatorManifest` provider so the plugin runs without `crustum/plugin-manifest` installed
- Analysis cache (`Viator.cache.enabled` / `directory`, `--fresh` flag), plugin scanning via `--plugin` / `Viator.scanPlugins` with `entityDirs` / `enumDirs` / `formDirs` / `eventDirs` overrides, and Twig TypeScript templates (`templates/ts/*`)
- Vite plugin (`@crustum/vite-plugin-viator`) watching `config/routes.php` and controller sources to regenerate on change
- Public docs: `docs/index.md`, `docs/Versions.md`
