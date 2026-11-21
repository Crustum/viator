import { describe, expect, test } from "vitest";
import { edit, show } from "./generated/actions/Key";

describe("keyed show (CakePHP has no model binding keys; keys stay strings)", () => {
    test("explicit URI resolves a primitive key", () => {
        expect(show["/keys/{key}"].url({ key: "547a7452-9dc5-4f64-a275-d646dea6ebcf" })).toBe(
            "/keys/547a7452-9dc5-4f64-a275-d646dea6ebcf",
        );
    });

    test("accepts scalar and array shorthands", () => {
        expect(show["/keys/{key}"].url("abc")).toBe("/keys/abc");
        expect(show["/keys/{key}"].url(["abc"])).toBe("/keys/abc");
    });

    test("default method", () => {
        expect(show["/keys/{key}"]({ key: "abc" })).toEqual({
            url: "/keys/abc",
            method: "get",
        });
    });

    test("definition", () => {
        expect(show["/keys/{key}"].definition).toEqual({
            methods: ["get"],
            url: "/keys/{key}",
        });
    });

    test("fallback URI resolves the key", () => {
        expect(show["/key/show/{key}"].url({ key: "abc" })).toBe("/key/show/abc");
    });
});

describe("keyed edit", () => {
    test("explicit URI resolves the key", () => {
        expect(edit["/keys/{key}/edit"].url({ key: "abc" })).toBe("/keys/abc/edit");
    });

    test("definition", () => {
        expect(edit["/keys/{key}/edit"].definition.url).toBe("/keys/{key}/edit");
    });
});
