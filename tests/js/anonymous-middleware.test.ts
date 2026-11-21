import { describe, expect, test } from "vitest";
import { show } from "./generated/actions/AnonymousMiddleware";

describe("closure-middleware equivalent (plain route, no middleware params)", () => {
    test("explicit URI", () => {
        expect(show["/anonymous-middleware"].url()).toBe("/anonymous-middleware");
        expect(show["/anonymous-middleware"]()).toEqual({
            url: "/anonymous-middleware",
            method: "get",
        });
    });

    test("fallback URI", () => {
        expect(show["/anonymous-middleware/show"].url()).toBe("/anonymous-middleware/show");
    });

    test("definition", () => {
        expect(show["/anonymous-middleware"].definition).toEqual({
            methods: ["get"],
            url: "/anonymous-middleware",
        });
    });
});
