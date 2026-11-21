import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, test } from "vitest";

describe("EnvironmentVariables", () => {
    const viteEnvPath = join(dirname(fileURLToPath(import.meta.url)), "generated/vite-env.d.ts");

    test("generates vite-env.d.ts file", () => {
        const content = readFileSync(viteEnvPath, "utf-8");
        expect(content).toBeDefined();
        expect(content.length).toBeGreaterThan(0);
    });

    test("contains vite client reference", () => {
        const content = readFileSync(viteEnvPath, "utf-8");
        expect(content).toContain('/// <reference types="vite/client" />');
    });

    test("defines ImportMetaEnv interface", () => {
        const content = readFileSync(viteEnvPath, "utf-8");
        expect(content).toContain("interface ImportMetaEnv");
    });

    test("defines ImportMeta interface", () => {
        const content = readFileSync(viteEnvPath, "utf-8");
        expect(content).toContain("interface ImportMeta");
        expect(content).toContain("readonly env: ImportMetaEnv");
    });

    test("includes VITE_ prefixed environment variables", () => {
        const content = readFileSync(viteEnvPath, "utf-8");
        expect(content).toContain("VITE_APP_NAME");
        expect(content).toContain("VITE_API_URL");
    });
});
