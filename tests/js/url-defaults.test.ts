import { describe, expect, test } from "vitest";
import { mixedDefaults, onlyDefaults, quotedDefaults } from "./generated/actions/UrlDefaults";

describe("onlyDefaults", () => {
    test("url falls back to the route default", () => {
        expect(onlyDefaults["/with-defaults/{locale?}"].url()).toBe("/with-defaults/en");
    });

    test("explicit value overrides the default", () => {
        expect(onlyDefaults["/with-defaults/{locale?}"].url({ locale: "es" })).toBe(
            "/with-defaults/es",
        );
    });

    test("default method", () => {
        expect(onlyDefaults["/with-defaults/{locale?}"]()).toEqual({
            url: "/with-defaults/en",
            method: "post",
        });
    });

    test("definition marks the defaulted parameter optional", () => {
        expect(onlyDefaults["/with-defaults/{locale?}"].definition).toEqual({
            methods: ["post"],
            url: "/with-defaults/{locale?}",
        });
    });

    test("fallback URI stays reachable through the dict", () => {
        expect(onlyDefaults["/url-defaults/only-defaults"].url()).toBe(
            "/url-defaults/only-defaults",
        );
    });
});

describe("mixedDefaults", () => {
    test("url fills the default and the argument", () => {
        expect(
            mixedDefaults["/with-defaults/{locale?}/also/{timezone}"]({ timezone: "UTC" }),
        ).toEqual({ url: "/with-defaults/en/also/UTC", method: "post" });
    });

    test("explicit locale overrides the default", () => {
        expect(
            mixedDefaults["/with-defaults/{locale?}/also/{timezone}"]({
                timezone: "UTC",
                locale: "es",
            }),
        ).toEqual({ url: "/with-defaults/es/also/UTC", method: "post" });
    });

    test("definition", () => {
        expect(mixedDefaults["/with-defaults/{locale?}/also/{timezone}"].definition).toEqual({
            methods: ["post"],
            url: "/with-defaults/{locale?}/also/{timezone}",
        });
    });
});

describe("quotedDefaults", () => {
    test("quoted default is escaped in the baked definition", () => {
        expect(quotedDefaults["/with-quoted-defaults/{quoted?}"].definition.url).toBe(
            "/with-quoted-defaults/{quoted?}",
        );
    });

    test("url falls back to the quoted default", () => {
        expect(quotedDefaults["/with-quoted-defaults/{quoted?}"].url()).toBe(
            '/with-quoted-defaults/say "hi" now',
        );
    });

    test("explicit value overrides the default", () => {
        expect(quotedDefaults["/with-quoted-defaults/{quoted?}"]({ quoted: "yo" })).toEqual({
            url: "/with-quoted-defaults/yo",
            method: "post",
        });
    });
});
