# CakePHP Viator Plugin

- [Introduction](#introduction)
- [Installation](#installation)
    - [Installing the Plugin](#installing-the-plugin)
- [Generating TypeScript](#generating-typescript)
    - [Command Options](#command-options)
    - [Automating Generation](#automating-generation)
- [Routes and Controller Actions](#routes-and-controller-actions)
    - [Basic Usage](#basic-usage)
    - [URL Generation](#url-generation)
    - [Query Parameters](#query-parameters)
    - [HTTP Method Variants](#http-method-variants)
    - [Form-Safe Routes](#form-safe-routes)
    - [Multiple Routes to the Same Action](#multiple-routes-to-the-same-action)
- [Named Routes](#named-routes)
- [Form Types](#form-types)
- [Entities](#entities)
- [PHP Enums](#php-enums)
    - [Enum Methods](#enum-methods)
- [Inertia.js Integration](#inertia-integration)
    - [Page Props](#page-props)
    - [Shared Data](#shared-data)
- [Broadcast Channels](#broadcast-channels)
- [Broadcast Events](#broadcast-events)
    - [Echo Integration](#echo-integration)
- [Environment Variables](#environment-variables)
- [Leaving Things Out](#leaving-things-out)
    - [Conditional Ignore](#conditional-ignore)
    - [Payload Keys](#payload-keys)
- [Configuration](#configuration)
    - [Configuration Options](#configuration-options)

<a name="introduction"></a>
## Introduction

Viator bridges your CakePHP backend and TypeScript frontend. It scans CakePHP routes, controller actions, entities, forms, enums, Inertia responses, broadcast definitions, and `VITE_*` environment variables, then generates fully-typed TypeScript from them. Stop manually maintaining route URLs, form types, and API contracts — Viator keeps your frontend and backend in sync.

Viator generates type-safe route functions with parameter validation for your controller actions, helpers for your named routes, types derived from your form validation rules, interfaces matching your entity properties and relations, types and constants for your PHP enums, page and shared-data types for your Inertia application, per-action response unions for your resource payloads, channel name builders and event payload types for your broadcasts, and typed declarations for your Vite environment variables.

Viator collects your application in a single Scrutator/Investigator walk and renders the collected components as fully-typed TypeScript. Viator never touches the database.

<a name="installation"></a>
## Installation

<a name="installing-the-plugin"></a>
### Installing the Plugin

Install via Composer (`crustum/investigator`, `crustum/scrutator`, `nikic/php-parser`, `phpstan/phpdoc-parser`, and `twig/twig` are hard dependencies):

```bash
composer require crustum/viator
```

Load the plugin:

```bash
bin/cake plugin load Crustum/Viator
```

> [!NOTE]
> Register the plugin in `config/plugins.php` (or `Application::bootstrap()`).

> [!TIP]
> **After the plugin is loaded**, install configuration with PluginManifest:

```bash
bin/cake manifest install --plugin Crustum/Viator
```

That publishes `config/viator.php` and appends loading of that file to your application's bootstrap. Settings are read as `Configure::read('Viator.*')`. There are no migrations to run.

Alternatively, you can load the plugin in your `Application.php`:

```php
// In src/Application.php
public function bootstrap(): void
{
    parent::bootstrap();

    $this->addPlugin('Crustum/Viator');
}
```

If you prefer not to use PluginManifest, copy `vendor/crustum/viator/config/viator.php` to `config/viator.php` and load it from bootstrap:

```php
if (file_exists(CONFIG . 'viator.php')) {
    Configure::load('viator', 'default');
}
```

A typical published configuration looks like:

```php
return [
    'Viator' => [
        'output' => null,
        'scanPlugins' => [],
        'ignoreActions' => [],
        'entityDirs' => null,
        'cache' => [
            'enabled' => true,
            'directory' => null,
        ],
    ],
];
```

<a name="generating-typescript"></a>
## Generating TypeScript

To generate TypeScript files from your CakePHP application, run the `viator routes` command:

```bash
bin/cake viator routes
```

By default, Viator writes files to `ROOT/resources/js`. Customize this with the `--path` option:

```bash
bin/cake viator routes --path=resources/ts/api
```

Generated layout under the base path:

```text
actions/<Group>.ts        # one file per Plugin.Prefix.Controller group
actions/**/index.ts       # barrels, one per branch level
routes/<group>/index.ts   # one index file per dotted route-name group
routes/**/index.ts        # barrels, one per branch level
viator/index.ts           # framework-agnostic runtime
viator/types.d.ts         # App / Inertia / enum / form / response namespaces
viator/broadcast-events.ts
viator/broadcast-channels.ts
viator/echo-broadcast-events.d.ts   # only when Echo Vue/React is installed
vite-env.d.ts             # at the base path, when VITE_* keys are found
<App/Enum/...>.ts         # one file per enum, following the PHP namespace
```

Action files resolve scanned controller actions against your Cake route templates, so URLs are produced by asking each route itself (`Route::match()` with placeholder tokens). Dasherize/underscore inflection and route patterns always follow the route setup — never guessed.

<a name="command-options"></a>
### Command Options

| Option | Description |
|--------|-------------|
| `--path` | Base output path for generated files |
| `--skip-actions` | Skip generating controller action functions |
| `--skip-routes` | Skip generating named route functions |
| `--skip-types` | Skip generating `App` / `Inertia` TypeScript namespaces |
| `--skip-enums` | Skip generating enum types (`*.Enum.*` sections plus per-enum files) |
| `--skip-forms` | Skip generating form types (`*.Form.*` sections) |
| `--skip-broadcasts` | Skip generating broadcast event/channel types |
| `--skip-env` | Skip generating `vite-env.d.ts` |
| `--with-form` | Generate form variants for non-GET verbs (on by default) |
| `--no-with-form` | Skip generating form variants for non-GET verbs |
| `--plugin` | Comma-separated plugin short names whose controllers to scan |
| `--only` | Comma-separated dotted route/group patterns to include (`*` wildcards, `!` negation) |
| `--except` | Comma-separated dotted route/group patterns to exclude (`*` wildcards, `!` negation) |
| `--fresh` | Clear the analysis cache before generating |

`--only` / `--except` match dotted names (`Posts`, `Admin.Users`, `posts.index`). `*` wildcards use `fnmatch()`; a leading `!` negates the pattern, and the last matching pattern wins. They fall back to the `Viator.only` / `Viator.except` Configure keys the flags mirror.

Analysis results persist on disk between runs; `--fresh` (or a disabled `Viator.cache`) clears them first so the walk reflects the current sources.

<a name="automating-generation"></a>
### Automating Generation

For development, regenerate TypeScript whenever your PHP files change. The bundled Vite plugin watches route and controller files and re-runs the generator:

```ts
import { viator } from "@crustum/vite-plugin-viator";

export default defineConfig({
    plugins: [
        viator(),
        // ...
    ],
});
```

| Option | Default | Description |
|---|---|---|
| `patterns` | `config/routes.php`, `src/Controller/**/*.php`, `plugins/*/src/Controller/**/*.php` | Watched files that trigger regeneration |
| `actions` | `true` | Generate `actions/` (set `false` to pass `--skip-actions`) |
| `routes` | `true` | Generate `routes/` (set `false` to pass `--skip-routes`) |
| `formVariants` | `false` | Generate `.form` variants (`--with-form`) |
| `path` | generator default | Base output path (`--path=`) |
| `command` | `php bin/cake.php viator routes` | Generator invocation (e.g. with `herd`/`valet` prefix) |
| `plugins` | `[]` | CakePHP plugins to scan (`--plugin=A,B`) |

There is no `skipDatabase` option: Viator never touches the database.

<a name="routes-and-controller-actions"></a>
## Routes and Controller Actions

Viator generates TypeScript functions for every scanned controller action. These functions provide type-safe URL generation with full IDE autocomplete. Only explicitly routed verbs are emitted; there is no implicit `head` callable for GET routes.

<a name="basic-usage"></a>
### Basic Usage

Given a CakePHP controller:

```php
class PostsController
{
    public function index() { /* ... */ }
    public function show(string $post) { /* ... */ }
    public function store() { /* ... */ }
}
```

Viator generates callable route functions:

```typescript
import Posts from "@/actions/Posts";

// Get URL and method for a route
Posts.index();
// { url: '/posts', method: 'get' }

// Routes with parameters
Posts.show({ post: 1 });
// { url: '/posts/1', method: 'get' }
```

Every action group file ends with a default-export namespace object, and every branch directory gets an `index.ts` barrel, so `@/actions/Posts` and `@/actions/Admin/Users` resolve without deep imports.

<a name="url-generation"></a>
### URL Generation

Each route function includes a `.url()` method for generating just the URL string:

```typescript
Posts.index.url();
// '/posts'

Posts.show.url({ post: 42 });
// '/posts/42'

Posts.edit.url({ post: 42 });
// '/posts/42/edit'
```

Optional route parameters use `{param?}` placeholders, and `definition.url` matches those keys. Generated functions call `applyUrlDefaults()` and `validateParameters()` from the `viator/` runtime. Route defaults from `Viator.urlDefaults` are baked into `viator/index.ts` as a `setUrlDefaults()` call.

<a name="query-parameters"></a>
### Query Parameters

Add query parameters to any route:

```typescript
Posts.index({ query: { page: 2, sort: "created_at" } });
// { url: '/posts?page=2&sort=created_at', method: 'get' }

Posts.index({ query: { active: [true, false] } });
// { url: '/posts?active[]=1&active[]=0', method: 'get' }

// Merge with existing query string
Posts.index({ mergeQuery: { page: 3 } });
```

Serialization follows the `viator/` runtime (`queryParams`, `mergeQuery`, nested `QueryParams`).

<a name="http-method-variants"></a>
### HTTP Method Variants

Routes are generated with method-specific variants:

```typescript
// Default uses the primary method
Posts.index(); // GET
Posts.store(); // POST
Posts.update({ post: 1 }); // PUT
Posts.destroy({ post: 1 }); // DELETE

// Explicit method selection
Posts.index.get();
Posts.update.patch({ post: 1 });
```

Verbs come from the controller action methods, filtered by the route `_method` constraint when the template constrains it. An action named `options` keeps working: the query-arg helper is renamed to `routeOptions` inside that file to avoid the collision.

<a name="form-safe-routes"></a>
### Form-Safe Routes

HTML forms only support GET and POST methods. Viator provides `.form` variants that automatically add the `_method` field for method spoofing (generated by default; pass `--no-with-form` to skip):

```typescript
Posts.update.form({ post: 1 });
// { action: '/posts/1?_method=PATCH', method: 'post' }

Posts.destroy.form({ post: 1 });
// { action: '/posts/1?_method=DELETE', method: 'post' }
```

Use these with HTML forms, for example, in React:

```html
<form {...Posts.update.form({ post: post.id })}>
    <!-- fields -->
</form>
```

<a name="multiple-routes-to-the-same-action"></a>
### Multiple Routes to the Same Action

CakePHP fallback routes (`/{controller}/{action}/*`) coexist with explicit routes. Viator handles the collisions without forcing everything into dictionaries:

- Every parameter-less `index` action that matches both `/{controller}` and `/{controller}/{action}/*` keeps two callables: the root URL keeps the plain name (`index()` → `/demo`) and the explicit one gets an `Explicit` suffix (`indexExplicit()` → `/demo/index`).
- All other same-method collisions keep the URI-keyed dictionary export (`shared['<key>'](...)`).

If two of those routes share a URI and differ only by verb, each key is prefixed with the verb, so you can still pick the one you want:

```typescript
TwoRoutesSameAction["get /exports/{report}"]({ report: 1 });
TwoRoutesSameAction["post /exports/{report}"]({ report: 1 });
```

A route that answers to more than one verb joins them with `|`, as in `TwoRoutesSameAction["put|patch /exports/{report}"]`. Exports whose URIs are already unique keep the plain URI keys shown above.

In most cases it is easier to import the route by name instead, as described below.

<a name="named-routes"></a>
## Named Routes

Viator also generates files organized by CakePHP route names, making it easy to access routes the same way you would by name.

Given routes defined with names:

```php
$routes->connect('/posts', ['controller' => 'Posts', 'action' => 'index'])
    ->setName('posts.index');
$routes->connect('/posts/{post}', ['controller' => 'Posts', 'action' => 'show'])
    ->setName('posts.show');
```

Access them by name:

```typescript
import posts from "@/routes/posts";

posts.index();
// { url: '/posts', method: 'get' }

posts.show({ post: 1 });
// { url: '/posts/1', method: 'get' }
```

Greedy `/*` routes are skipped, and routing placeholders (`{controller}`, `{action}`, `{plugin}`, `{prefix}`) without concrete defaults are covered by the `actions/` scan instead. Defaulted placeholders render optional in the URI, so `definition.url` matches the `{param?}` replace keys. Named groups render one `index.ts` per dotted group (for example `routes/admin/users/index.ts`) with barrels above them.

<a name="form-types"></a>
## Form Types

Viator analyzes your CakePHP `Form` validator rules and generates corresponding TypeScript types. Only `*Form` classes are collected.

Given a form:

```php
class StorePostForm extends Form
{
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('title')
            ->notEmptyString('title')
            ->add('title', 'maxLength', ['rule' => ['maxLength', 255]]);

        $validator
            ->allowEmptyString('excerpt');

        $validator
            ->requirePresence('meta.description')
            ->add('meta.description', 'maxLength', ['rule' => ['maxLength', 500]]);

        return $validator;
    }
}
```

Viator generates:

```typescript
export type StorePostForm = {
    title: string;
    body: string;
    excerpt?: string | null;
    meta: {
        description: string;
    };
};
```

`requirePresence()` without any `allowEmpty*()` marks the key required; `inList` carries literal values and `enum` rules reference the enum class. Dotted fields nest. Import these types from the generated `types.d.ts` file. You can then use them with something like Inertia's [form helper](https://inertiajs.com/docs/v2/the-basics/forms#form-helper):

```typescript
import { App } from "@/viator/types";

const form = useForm<App.Form.StorePostForm>();
```

<a name="entities"></a>
## Entities

Viator generates TypeScript interfaces for your CakePHP entities, including their properties and relations.

Given an entity:

```php
/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $email_verified_at
 * @property bool $is_admin
 * @property array<int, \App\Model\Entity\Post> $posts
 */
class User extends Entity
{
}
```

Viator generates a TypeScript interface in `types.d.ts`:

```typescript
export namespace App.Model.Entity {
    export type User = {
        id: number;
        name: string;
        email: string;
        email_verified_at: string | null;
        is_admin: boolean;
        posts: App.Model.Entity.Post[];
    };
}
```

Entities sharing a namespace path merge into one namespace block (for example `App.Model.Entity` holding `User`, `Product`, and `Category`). Only collected entities with visible properties render. Use these types in your frontend:

```typescript
import { App } from "@/viator/types";

function displayUser(user: App.Model.Entity.User) {
    console.log(user.name, user.email);
}
```

A property marked with the `ViatorIgnore` attribute is left out — see [Leaving Things Out](#leaving-things-out).

<a name="php-enums"></a>
## PHP Enums

Viator converts native PHP enums found under `Viator.enumDirs` (defaults to your `src/` tree). Each collected enum renders one file following its PHP namespace (`App\Enum\PostStatus` becomes `App/Enum/PostStatus.ts`): named case constants, an inline const object with an `as const` assertion, and a default export. Unions also render in `types.d.ts` under the dotted FQN path.

Given a PHP enum:

```php
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
```

Viator generates both a type and constants:

```typescript
// Type for type-checking (in types.d.ts)
export namespace App.Enum {
    export type PostStatus = "draft" | "published" | "archived";
}

// Constants for runtime use (in App/Enum/PostStatus.ts)
export const Draft = "draft";
export const Published = "published";
export const Archived = "archived";

export const PostStatus = { Draft, Published, Archived } as const;

export default PostStatus;
```

Pure enums union their indices; empty enums become `never`. Case names colliding with TypeScript reserved words stay out of the individual constants but render as quoted object entries with literal values.

Use in your TypeScript:

```typescript
import PostStatus from "@/App/Enum/PostStatus";
import { App } from "@/viator/types";

// Use constants
if (post.status === PostStatus.Published) {
    // ...
}

// Type-safe status
function setStatus(status: App.Enum.PostStatus) {
    // Only accepts 'draft', 'published', or 'archived'
}
```

A case can be left out of the generated enum, either always or only for some builds — see [Leaving Things Out](#leaving-things-out).

<a name="enum-methods"></a>
### Enum Methods

Enums often carry methods alongside their cases:

```php
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Live',
            self::Archived => 'Archived',
        };
    }

    public function isVisible(): bool
    {
        return $this === self::Published;
    }
}
```

Set `Viator.enumMethods` to `true` and Viator calls each method on each case and writes the results to a companion constant, keyed by case value:

```typescript
export const PostStatusMeta = {
    "draft": { label: "Draft", isVisible: false },
    "published": { label: "Live", isVisible: true },
    "archived": { label: "Archived", isVisible: false },
} as const;
```

Because the keys are case values, a value straight from the server is the lookup key:

```typescript
import PostStatus, {
    PostStatusMeta,
} from "@/App/Enum/PostStatus";

PostStatusMeta[post.status].label;
PostStatusMeta[PostStatus.Published].isVisible;
```

Only methods that take no required arguments are called. Static, magic, protected and private methods are skipped, as are methods returning `void` or `never`, and any method marked with `ViatorIgnore` — see [Leaving Things Out](#leaving-things-out).

If a method throws for one case, that case alone loses the entry, and the same goes for a value that has no TypeScript equivalent — a plain object, for instance. Strings, numbers, booleans, `null`, arrays, backed enums, and other encodable values convert.

This runs your code at generation time, which is why it is off by default. Bear in mind that anything depending on the current locale, the database or the environment is frozen as it was when the command ran.

<a name="inertia-integration"></a>
## Inertia.js Integration

Viator provides support for Inertia.js applications, automatically generating types for your page props and shared data. It requires `crustum/inertia-cakephp` in the host application for the walked components to exist.

<a name="page-props"></a>
### Page Props

Pages come from the same scanned controller actions the `actions/` output uses, analyzed through the Scrutator response collector. Component paths nest on `/`, `::`, `.`, and `\` separators; a prop seen in only some renders is optional, and a prop with different types across renders is their union.

Given an Inertia controller rendering `Dashboard` with `stats` and `recentActivity` props, Viator generates types for the page props:

```typescript
export namespace Inertia.Pages {
    export type Dashboard = Inertia.SharedData & {
        stats: {
            users: number;
            posts: number;
        };
        recentActivity: App.Model.Entity.Activity[];
    };
}
```

Use in your Vue or React components:

```vue
<script setup lang="ts">
import { Inertia } from "@/viator/types";

defineProps<Inertia.Pages.Dashboard>();
</script>
```

> [!NOTE]
> Inertia `component` names and `withComponent()` helpers are not emitted — pages, URLs, and definitions only.

<a name="shared-data"></a>
### Shared Data

Viator also types your shared Inertia data from the collected shared-data component:

```typescript
export namespace Inertia {
    export type SharedData = {
        auth: {
            user: App.Model.Entity.User | null;
        };
        flash: {
            success: string | null;
            error: string | null;
        };
    };
}
```

When no shared-data component carries data, the base stays an open record (`{ [key: string]: unknown; }`).

Collected resource and JSON:API responses additionally render one `Response` union per controller action (for example `App.Controller.Posts.View.Response`).

<a name="broadcast-channels"></a>
## Broadcast Channels

Viator generates type-safe helpers for CakePHP broadcast channels. Channels resolve through the `crustum/broadcasting` broadcaster registry; when that package is not loaded, channel output is skipped.

Given channel definitions in `config/channels.php`:

```php
Broadcasting::channel('orders.{orderId}', function ($user, $orderId) {
    return $user->canViewOrder($orderId);
});

Broadcasting::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

Viator generates `viator/broadcast-channels.ts`:

```typescript
export type BroadcastChannel =
    | `orders.${string | number}`
    | `user.${string | number}.notifications`;

export const BroadcastChannels = {
    orders: (orderId: string | number) => `orders.${orderId}` as const,
    user: (userId: string | number) => ({
        notifications: `user.${userId}.notifications` as const,
    }),
} as const;
```

Static segments nest; `{param}` segments wrap the nearest static segment on the left as an arrow function. Use with Laravel Echo:

```typescript
import { BroadcastChannels } from "@/viator/broadcast-channels";
import Echo from "laravel-echo";

// Type-safe channel subscription
Echo.private(BroadcastChannels.orders(orderId)).listen("OrderShipped", (e) => {
    console.log(e.trackingNumber);
});

Echo.private(BroadcastChannels.user(userId).notifications).listen(
    "NewNotification",
    (e) => {
        // ...
    },
);
```

<a name="broadcast-events"></a>
## Broadcast Events

Viator generates types for your broadcast events. Events implementing the broadcasting contract are discovered through the Scrutator collector; names stay verbatim wire names (FQCN with backslashes for `static::class`, string literals as-is).

Given a broadcast event:

```php
class OrderShipped implements BroadcastableInterface
{
    public function __construct(
        public int $orderId,
        public string $trackingNumber,
        public string $carrier,
    ) {
        //
    }

    public function broadcastChannel(): Channel|array
    {
        return new Channel('orders.' . $this->orderId);
    }

    public function broadcastData(): ?array
    {
        return [
            'orderId' => $this->orderId,
            'trackingNumber' => $this->trackingNumber,
            'carrier' => $this->carrier,
        ];
    }
}
```

Viator generates `viator/broadcast-events.ts`:

```typescript
export type BroadcastEvent =
    | ".App\\Event\\OrderShipped"
    | ".App\\Event\\UserNotification";

export const BroadcastEvents = {
    App: {
        Event: {
            OrderShipped: ".App\\Event\\OrderShipped",
            UserNotification: ".App\\Event\\UserNotification",
        },
    },
} as const;

// Event payload types resolve through the type converter
// and render alongside App namespaces in types.d.ts
```

<a name="echo-integration"></a>
### Echo Integration

If you have `@laravel/echo-vue` or `@laravel/echo-react` installed, Viator generates type augmentations in `viator/echo-broadcast-events.d.ts`:

```typescript
// echo-broadcast-events.d.ts
declare global {
    interface Events {
        ".App\\Event\\OrderShipped": {
            orderId: number;
            trackingNumber: string;
            carrier: string;
        };
    }
}

export {};
```

This provides full type safety when listening for events:

```typescript
import { useEcho } from "@laravel/echo-vue";
import { BroadcastEvents } from "@/viator/broadcast-events";

useEcho("orders." + orderId, BroadcastEvents.App.Event.OrderShipped, (e) => {
    // e is fully typed!
    console.log(e.trackingNumber);
});
```

The Echo lookup starts from the host root (`ROOT`). When no Echo package is installed, only `broadcast-events.ts` is emitted.

<a name="environment-variables"></a>
## Environment Variables

Viator generates TypeScript declarations for your Vite environment variables (those prefixed with `VITE_`).

Names come from dotenv files (`Viator.envFiles`, defaults to `ROOT/config/.env`) plus `import.meta.env.VITE_*` usages collected under your JavaScript roots. All values type as `string`. Given a `.env` file:

```ini
VITE_APP_NAME="My App"
VITE_API_URL=https://api.example.com
VITE_PUSHER_KEY=your-key
```

Viator generates `vite-env.d.ts` at the output base:

```typescript
/// <reference types="vite/client" />

interface ImportMetaEnv {
    readonly VITE_APP_NAME: string;
    readonly VITE_API_URL: string;
    readonly VITE_PUSHER_KEY: string;
}

interface ImportMeta {
    readonly env: ImportMetaEnv;
}
```

Nothing is emitted when no `VITE_*` keys are found, or when your application already declares `ImportMetaEnv` itself (merging a second declaration would conflict). This provides autocomplete and type-checking for `import.meta.env`:

```typescript
// TypeScript knows this is a string
const appName = import.meta.env.VITE_APP_NAME;
```

<a name="leaving-things-out"></a>
## Leaving Things Out

Some of what Viator can see should not reach the browser. Mark it with the `ViatorIgnore` attribute and nothing is generated for it:

```php
use Crustum\Viator\Attribute\ViatorIgnore;

#[ViatorIgnore]
class InternalController
{
    // No action file, no route helper, no form variant.
}
```

The attribute works on a controller class or a single action, an entity property, an enum or one of its cases or methods, and a broadcast event or channel:

```php
class UsersController
{
    public function index() { /* generated */ }

    #[ViatorIgnore]
    public function impersonate() { /* not generated */ }
}
```

```php
class User extends Entity
{
    #[ViatorIgnore]
    public string $audit_token = '';
}
```

The attribute is registered before the walk (`Markers::registerAttributes(ViatorIgnore::class)`), so analysis-time checks agree with generation-time ones. Dropping an action drops everything that hangs off it: the route helper, the form variant, and the page/response types.

<a name="conditional-ignore"></a>
### Conditional Ignore

Some declarations belong in local builds only: a fake source provider, a seeding endpoint, a debug page. Pass `unless` and the declaration is kept only while the condition holds:

```php
// config/services.php
'fake_source_provider' => env('FAKE_SOURCE_PROVIDER', false),

// App\Enum\SourceProvider
enum SourceProvider: string
{
    case Github = 'github';
    case Gitlab = 'gitlab';

    #[ViatorIgnore(unless: 'services.fake_source_provider')]
    case GitFake = 'gitfake';
}
```

Locally, with the flag on:

```typescript
export type SourceProvider = "github" | "gitlab" | "gitfake";
```

In production, with the flag off or missing:

```typescript
export type SourceProvider = "github" | "gitlab";
```

`when` is the other way round, for something to leave out while a condition holds rather than keep:

```php
class DebugController
{
    #[ViatorIgnore(when: 'services.hide_debug_tools')]
    public function dump() { /* ... */ }
}
```

Either takes a `Configure` key, or a `[class, method]` callable for a condition with real logic behind it:

```php
#[ViatorIgnore(unless: [SourceProviders::class, 'fakeEnabled'])]
```

Whatever you pass is answered when the files are generated, by the environment generating them. A string condition is answered by `Configure::read()`; a callable is invoked. Each build gets the answer for where it runs.

| Condition | `unless` | `when` |
| --------- | -------- | ------ |
| Passes | Kept | Left out |
| Fails, or the config key is missing | Left out | Kept |
| Cannot be read | Left out | Left out |

A condition Viator cannot read leaves the declaration out whichever argument it was written with, so a typo shows up as a type error rather than shipping something. Only a config key or a callable counts as a condition, so `#[ViatorIgnore(true)]` is not a way to switch a marker on or off — it is a marker with no condition, which leaves the declaration out. Passing both arguments is allowed and each can only add hiding, so `unless` keeping something does not override `when` leaving it out. Reach for one or the other.

One consequence worth planning for: the production build genuinely does not have the member, so code that reads it has to sit somewhere the production build never typechecks.

<a name="payload-keys"></a>
### Payload Keys

An attribute cannot go on an array key, so mark those with a comment instead. Put `@viator-ignore` above the key or at the end of its line:

```php
return [
    'name' => $user->name,
    'apiToken' => $user->api_token, // @viator-ignore
    'billing' => [
        'plan' => $user->plan,
        // @viator-ignore
        'stripeId' => $user->stripe_id,
    ],
];
```

```typescript
export type Profile = SharedData & {
    name: string;
    billing: { plan: string };
};
```

The key is gone, not emptied, so code that still reads it fails to compile. This works anywhere Viator reads an array: page props, resource `toArray()` shapes, `broadcastWith()`/`broadcastData()` payloads, and form validator fields.

A marker hides a member from its own type. If something else hands the same value out under a key of its own, that key needs its own marker.

Nothing is generated for a trait itself, so mark the members inside it, which works whether the trait is used by one controller or twenty:

```php
trait HasAvatar
{
    #[ViatorIgnore]
    public function avatarUrl() { /* ... */ }
}
```

<a name="configuration"></a>
## Configuration

The configuration file is located at `config/viator.php`. Viator-specific discovery directories default to the host `src/` tree plus every scanned plugin; only set them explicitly when the default walk misses a location.

```php
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
        'entityDirs' => null,
        // Analysis cache. Disk caching speeds up repeat runs; `--fresh`
        // clears the cache before generating.
        'cache' => [
            'enabled' => true,
            'directory' => null,
        ],
    ],
];
```

<a name="configuration-options"></a>
### Configuration Options

| Option | Description | Default |
|--------|-------------|---------|
| `output` | Base output path for generated files | `null` (`ROOT/resources/js`) |
| `scanPlugins` | Plugin short names whose controllers and entities are scanned | `[]` |
| `ignoreActions` | Action names to skip (`fetchCollection`, or `Prefix:Controller:action`) | `[]` |
| `entityDirs` | Entity source directories (overrides the host plus scanned plugins) | `null` |
| `enumDirs` | Enum source directories | `[APP]` |
| `formDirs` | Form source directories | `[APP . 'Form']` |
| `eventDirs` | Broadcast event source directories | `[APP . 'Event']` |
| `envFiles` | Dotenv files scanned for `VITE_*` keys | `[ROOT . '/config/.env']` |
| `enumMethods` | Resolve enum methods for each case into `NameMeta` constants | `false` |
| `urlDefaults` | URL defaults baked into the runtime via `setUrlDefaults()` | `[]` |
| `only` | Dotted route/group patterns to include (mirrors `--only`) | `[]` |
| `except` | Dotted route/group patterns to exclude (mirrors `--except`) | `[]` |
| `cache.enabled` | Enable caching for faster regeneration | `true` |
| `cache.directory` | Directory for cache files | `null` (`CACHE . 'viator'/`) |

URL defaults from route defaults plus `Viator.urlDefaults` apply through the `viator/` runtime (`applyUrlDefaults`, `setUrlDefaults`, `addUrlDefault`). The per-file functions validate optional parameters with `validateParameters()` before building the URL.
