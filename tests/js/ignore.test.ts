import { existsSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, test } from "vitest";

const generated = join(dirname(fileURLToPath(import.meta.url)), "generated");
const actions = (p: string) => readFileSync(join(generated, "actions", p), "utf-8");
const types = () => readFileSync(join(generated, "viator", "types.d.ts"), "utf-8");

describe("Ignore", () => {
    test("nothing is generated for a marked controller", () => {
        expect(types()).not.toContain("IgnoreWholeController");
        expect(existsSync(join(generated, "actions", "IgnoreWhole.ts"))).toBe(false);
    });

    test("a marked method is dropped and its siblings are kept", () => {
        const controller = actions("Secrets.ts");

        expect(controller).toContain("export const index");
        expect(controller).toContain("export const resource");
        expect(controller).not.toContain("reveal");
        expect(types()).not.toContain("Inertia.Pages.Reveal");
    });

    test("a marked enum method is dropped and its siblings are kept", () => {
        const orderStatus = readFileSync(
            join(generated, "TestApp", "Enum", "OrderStatus.ts"),
            "utf-8",
        );

        expect(orderStatus).toContain("label:");
        expect(orderStatus).toContain("icon:");
        expect(orderStatus).not.toContain("internalCode");
        expect(orderStatus).not.toContain("note:");
    });

    test("a marked page prop is dropped from the page type", () => {
        const secrets = types().split("export type Secrets")[1]?.split("export type")[0];

        expect(secrets).toBeDefined();
        expect(secrets).toContain("name: string");
        expect(secrets).toContain("email: string");
        expect(secrets).not.toContain("socialSecurityNumber");
    });

    test("a marked prop nested in a page prop is dropped", () => {
        const secrets = types().split("export type Secrets")[1]?.split("export type")[0];

        expect(secrets).toContain("label: string");
        expect(secrets).not.toContain("routingNumber");
    });

    test("nothing is generated for a marked model", () => {
        expect(types()).not.toContain("AuditLog");
    });

    test("a case is dropped when its unless condition fails", () => {
        const sourceProvider = readFileSync(
            join(generated, "TestApp", "Enum", "SourceProvider.ts"),
            "utf-8",
        );

        expect(sourceProvider).toContain('export const Github = "github"');
        expect(sourceProvider).toContain("{ Github, Gitlab }");
        expect(sourceProvider).not.toContain("GitFake");
        expect(types()).toContain('export type SourceProvider = "github" | "gitlab"');
    });

    test("a case is dropped when its when condition passes", () => {
        const sourceProvider = readFileSync(
            join(generated, "TestApp", "Enum", "SourceProvider.ts"),
            "utf-8",
        );

        expect(sourceProvider).not.toContain("GitRetired");
        expect(types()).not.toContain("gitretired");
    });

    test("routes for marked controllers are not registered", () => {
        expect(existsSync(join(generated, "routes", "ignore"))).toBe(false);
    });

    test("named routes for marked actions stay registered (explicit routes are kept)", () => {
        const secrets = readFileSync(join(generated, "routes", "secrets", "index.ts"), "utf-8");

        expect(secrets).toContain("export const reveal");
    });
});
