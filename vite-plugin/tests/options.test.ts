import { describe, expect, test } from "vitest";
import { commandArgs } from "../src/vite-plugin-viator";

describe("commandArgs", () => {
    test("defaults produce no flags", () => {
        expect(commandArgs()).toEqual([]);
    });

    test("skips actions", () => {
        expect(commandArgs({ actions: false })).toEqual(["--skip-actions"]);
    });

    test("skips routes", () => {
        expect(commandArgs({ routes: false })).toEqual(["--skip-routes"]);
    });

    test("enables form variants", () => {
        expect(commandArgs({ formVariants: true })).toEqual(["--with-form"]);
    });

    test("passes a custom path", () => {
        expect(commandArgs({ path: "resources/js" })).toEqual([
            "--path=resources/js",
        ]);
    });

    test("passes scanned plugins", () => {
        expect(commandArgs({ plugins: ["CakeDC/Admin", "Monitor"] })).toEqual([
            "--plugin=CakeDC/Admin,Monitor",
        ]);
    });

    test("combines every option in order", () => {
        expect(
            commandArgs({
                actions: false,
                routes: false,
                formVariants: true,
                path: "resources/js",
                plugins: ["Monitor"],
            }),
        ).toEqual([
            "--skip-actions",
            "--skip-routes",
            "--with-form",
            "--path=resources/js",
            "--plugin=Monitor",
        ]);
    });
});
