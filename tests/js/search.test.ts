import { describe, expect, test } from "vitest";
import Search, { index, indexExplicit, shared, show } from "./generated/actions/Search";
import search from "./generated/routes/search";

describe("query only route (CakePHP has no QUERY verb; GET is the equivalent)", () => {
    test("properties", () => {
        expect(Object.keys(index)).toEqual(["definition", "url", "get", "form"]);
    });

    test("definition", () => {
        expect(index.definition).toEqual({ methods: ["get"], url: "/search" });
    });

    test("default method", () => {
        expect(index()).toEqual({ url: "/search", method: "get" });
    });

    test("get with query params", () => {
        expect(index.get({ query: { page: 2 } })).toEqual({
            url: "/search?page=2",
            method: "get",
        });
    });

    test("form get", () => {
        expect(index.form.get({ query: { page: 2 } })).toEqual({
            action: "/search?page=2",
            method: "get",
        });
    });

    test("named route", () => {
        expect(search.index()).toEqual({ url: "/search", method: "get" });
    });

    test("explicit action segment gets its own callable", () => {
        expect(indexExplicit.url()).toBe("/search/index");
        expect(indexExplicit()).toEqual({ url: "/search/index", method: "get" });
    });
});

describe("get route with a parameter", () => {
    test("explicit URI resolves the parameter", () => {
        expect(show["/search/{type}"].url({ type: "posts" })).toBe("/search/posts");
        expect(show["/search/{type}"]("posts")).toEqual({
            url: "/search/posts",
            method: "get",
        });
    });

    test("fallback URI resolves the parameter", () => {
        expect(show["/search/show/{type}"].url({ type: "posts" })).toBe(
            "/search/show/posts",
        );
    });

    test("form get", () => {
        expect(show["/search/{type}"].form.get("posts")).toEqual({
            action: "/search/posts",
            method: "get",
        });
    });

    test("named route", () => {
        expect(search.show("posts")).toEqual({ url: "/search/posts", method: "get" });
    });
});

describe("search barrel", () => {
    test("default export exposes every action", () => {
        expect(Search.index.url()).toBe("/search");
        expect(Search.show["/search/{type}"].url("posts")).toBe("/search/posts");
        expect(Search.shared.url()).toBe("/search/shared");
        expect(Search.indexExplicit.url()).toBe("/search/index");
    });

    test("inherited shared action surfaces", () => {
        expect(shared.url()).toBe("/search/shared");
    });
});
