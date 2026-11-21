import { describe, expect, test } from "vitest";
import { edit, filter, options } from "./generated/actions/Demo";

describe("filter", () => {
    test("properties", () => {
        expect(Object.keys(filter)).toEqual([
            "definition",
            "url",
            "get",
            "form",
        ]);
    });

    test("url with several optional parameters", () => {
        expect(filter.url()).toBe("/demo/filter");
        expect(filter.url({ one: "1" })).toBe("/demo/filter/1");
        expect(filter.url({ one: "1", two: "2", three: "3" })).toBe(
            "/demo/filter/1/2/3",
        );
    });

    test("url supports falsy optional values", () => {
        expect(filter.url({ one: 0, two: 2 })).toBe("/demo/filter/0/2");
        expect(filter.url({ one: 0 })).toBe("/demo/filter/0");
    });

    test("throws when optional parameters skip a position", () => {
        expect(() => filter.url({ two: "2" })).toThrow();
        expect(() => filter.url({ three: "3" })).toThrow();
        expect(() => filter.url({ two: "2", three: "3" })).toThrow();
    });

    test("definition", () => {
        expect(filter.definition.url).toBe("/demo/filter/{one?}/{two?}/{three?}");
    });
});

describe("edit", () => {
    test("throws when the required parameter is missing", () => {
        expect(() => edit.url({ mode: "fast" } as unknown as { id: string })).toThrow();
    });
});

describe("options", () => {
    test("properties", () => {
        expect(Object.keys(options)).toEqual([
            "definition",
            "url",
            "get",
            "form",
        ]);
    });

    test("does not shadow a method named options", () => {
        expect(options({ query: { page: 2 } })).toEqual({
            url: "/demo/options?page=2",
            method: "get",
        });
    });
});
