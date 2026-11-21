import { describe, expect, test } from "vitest";
import { options } from "./generated/actions/NavigationItem";

describe("action named options (must not shadow the query-options argument)", () => {
    test("explicit URI resolves the item", () => {
        expect(options["/navigation-items/{item}/options"]({ item: 1 })).toEqual({
            url: "/navigation-items/1/options",
            method: "get",
        });
    });

    test("options argument still carries query params", () => {
        expect(
            options["/navigation-items/{item}/options"].url({ item: 1 }, { query: { page: 2 } }),
        ).toBe("/navigation-items/1/options?page=2");
    });

    test("definition", () => {
        expect(options["/navigation-items/{item}/options"].definition).toEqual({
            methods: ["get"],
            url: "/navigation-items/{item}/options",
        });
    });

    test("fallback URI resolves the item", () => {
        expect(options["/navigation-item/options/{item}"].url({ item: 1 })).toBe(
            "/navigation-item/options/1",
        );
    });
});
