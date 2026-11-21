import { exec } from "node:child_process";
import { minimatch } from "minimatch";
import osPath from "node:path";
import type { PluginContext } from "rollup";
import { promisify } from "node:util";
import type { HmrContext, Plugin } from "vite";

const execAsync = promisify(exec);

const debounceMs = 100;

export interface ViatorOptions {
    patterns?: string[];
    actions?: boolean;
    routes?: boolean;
    formVariants?: boolean;
    path?: string;
    command?: string;
    /** CakePHP plugins whose controllers are scanned (`--plugin=A,B`). */
    plugins?: string[];
}

let context: PluginContext;

/**
 * Resolve the generator CLI arguments for the given options.
 *
 * Pure (no Vite context) so the mapping is unit-testable.
 */
export const commandArgs = ({
    actions = true,
    routes = true,
    formVariants = false,
    path,
    plugins = [],
}: ViatorOptions = {}): string[] => {
    const args: string[] = [];

    if (!actions) {
        args.push("--skip-actions");
    }

    if (!routes) {
        args.push("--skip-routes");
    }

    if (formVariants) {
        args.push("--with-form");
    }

    if (path) {
        args.push(`--path=${path}`);
    }

    if (plugins.length > 0) {
        args.push(`--plugin=${plugins.join(",")}`);
    }

    return args;
};

export const viator = ({
    patterns = [
        "config/routes.php",
        "src/Controller/**/*.php",
        "plugins/*/src/Controller/**/*.php",
    ],
    actions = true,
    routes = true,
    formVariants = false,
    path,
    command = "php bin/cake.php viator routes",
    plugins = [],
}: ViatorOptions = {}): Plugin => {
    patterns = patterns.map((pattern) => pattern.replace("\\", "/"));

    const args = commandArgs({ actions, routes, formVariants, path, plugins });

    const generating: string[] = [];

    if (actions) {
        generating.push("actions");
    }

    if (routes) {
        generating.push("routes");
    }

    if (formVariants) {
        generating.push("form variants");
    }

    let serving = false;

    const generate = async () => {
        try {
            await execAsync(`${command} ${args.join(" ")}`);
        } catch (error) {
            context.error("Error generating types: " + error);
        }

        context.info(`Types generated for ${generating.join(", ")}`);
    };

    // Two runs writing the same output directory at once can leave torn files,
    // so every run queues behind the previous one.
    let tail: Promise<void> = Promise.resolve();

    const runCommand = () => {
        const result = tail.then(generate, generate);

        tail = result.catch(() => {});

        return result;
    };

    let timer: ReturnType<typeof setTimeout> | undefined;
    let pending: Promise<void> | undefined;
    let settle:
        | { resolve: () => void; reject: (error: unknown) => void }
        | undefined;

    const scheduleCommand = () => {
        pending ??= new Promise<void>((resolve, reject) => {
            settle = { resolve, reject };
        });

        clearTimeout(timer);

        timer = setTimeout(() => {
            const { resolve, reject } = settle!;

            pending = undefined;
            settle = undefined;

            runCommand().then(resolve, reject);
        }, debounceMs);

        return pending;
    };

    return {
        name: "@crustum/vite-plugin-viator",
        enforce: "pre",
        configResolved(config) {
            serving = config.command === "serve";
        },
        buildStart() {
            context = this;
            return runCommand();
        },
        handleHotUpdate({ file, server }) {
            if (!shouldRun(patterns, { file, server })) {
                return;
            }

            return serving ? scheduleCommand() : runCommand();
        },
    };
};

const shouldRun = (
    patterns: string[],
    opts: Pick<HmrContext, "file" | "server">,
): boolean => {
    const file = opts.file.replaceAll("\\", "/");

    return patterns.some((pattern) => {
        pattern = osPath
            .resolve(opts.server.config.root, pattern)
            .replaceAll("\\", "/");

        return minimatch(file, pattern);
    });
};
