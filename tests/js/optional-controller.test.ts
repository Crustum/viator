import { describe, expect, test } from "vitest";
import { manyOptional, optional } from "./generated/actions/Optional";

describe("optional parameters", () => {
    test("single optional: explicit and fallback URIs", () => {
        expect(optional["/optional/{parameter?}"].url()).toBe("/optional");
        expect(optional["/optional/optional/{parameter?}"].url()).toBe(
            "/optional/optional",
        );
        expect(
            optional["/optional/{parameter?}"].url({ parameter: "x" }),
        ).toBe("/optional/x");
        expect(optional["/optional/{parameter?}"]({ parameter: "x" })).toEqual(
            { url: "/optional/x", method: "post" },
        );
    });

    test("three optionals fill left to right", () => {
        expect(manyOptional["/many-optional/{one?}/{two?}/{three?}"].url()).toBe(
            "/many-optional",
        );
        expect(
            manyOptional["/many-optional/{one?}/{two?}/{three?}"].url({
                one: "a",
                two: "b",
                three: "c",
            }),
        ).toBe("/many-optional/a/b/c");
    });
});
