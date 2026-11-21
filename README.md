# CakePHP Viator Plugin

The **Viator** plugin bridges your CakePHP backend and TypeScript frontend. It scans CakePHP routes, controller actions, entities, forms, enums, Inertia responses, broadcast definitions, and `VITE_*` environment variables, then generates fully-typed TypeScript from them.

Viator generates type-safe route functions with parameter validation, named-route helpers, form and entity types, enum constants and unions, Inertia page and shared-data types, broadcast channel builders and event payloads, and typed `import.meta.env` declarations. Generation runs through the `viator routes` command over a single Scrutator/Investigator walk, with per-section skip flags and an analysis cache for repeat runs.

## Requirements

* PHP 8.4+

Optional installer support is listed under Composer `suggest`.

See [Versions.md](docs/Versions.md) for the supported CakePHP versions.

## Documentation

For documentation, as well as tutorials, see the [docs](docs/index.md) directory of this repository.

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.
