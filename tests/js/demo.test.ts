import { describe, expect, test } from "vitest";
import {
    add,
    deleteMethod,
    edit,
    filter,
    index,
    indexExplicit,
    options,
    shared,
    view,
} from "./generated/actions/Demo";

describe("index", () => {
    test("properties", () => {
        expect(Object.keys(index)).toEqual([
            "definition",
            "url",
            "get",
            "form",
        ]);
    });

    test("root URL keeps the plain method name", () => {
        expect(index.url()).toBe("/demo");
        expect(index()).toEqual({ url: "/demo", method: "get" });
    });

    test("definition", () => {
        expect(index.definition).toEqual({ methods: ["get"], url: "/demo" });
    });
});

describe("indexExplicit", () => {
    test("explicit action segment gets its own callable", () => {
        expect(indexExplicit.url()).toBe("/demo/index");
        expect(indexExplicit()).toEqual({ url: "/demo/index", method: "get" });
        expect(indexExplicit.definition).toEqual({
            methods: ["get"],
            url: "/demo/index",
        });
    });
});

describe("view", () => {
    test("properties", () => {
        expect(Object.keys(view)).toEqual([
            "definition",
            "url",
            "get",
            "form",
        ]);
    });

    test("url", () => {
        expect(view.url({ id: 1 })).toBe("/demo/view/1");
    });

    test("accepts a scalar shorthand", () => {
        expect(view.url(1)).toBe("/demo/view/1");
    });

    test("accepts an array", () => {
        expect(view.url([1])).toBe("/demo/view/1");
    });

    test("default method", () => {
        expect(view({ id: 1 })).toEqual({
            url: "/demo/view/1",
            method: "get",
        });
    });

    test("definition", () => {
        expect(view.definition).toEqual({
            methods: ["get"],
            url: "/demo/view/{id}",
        });
    });
});

describe("edit", () => {
    test("properties", () => {
        expect(Object.keys(edit)).toEqual([
            "definition",
            "url",
            "get",
            "form",
        ]);
    });

    test("url with required and optional parameters", () => {
        expect(edit.url({ id: 1 })).toBe("/demo/edit/1");
        expect(edit.url({ id: 1, mode: "fast" })).toBe("/demo/edit/1/fast");
    });

    test("definition", () => {
        expect(edit.definition).toEqual({
            methods: ["get"],
            url: "/demo/edit/{id}/{mode?}",
        });
    });
});

describe("add", () => {
    test("properties", () => {
        expect(Object.keys(add)).toEqual([
            "definition",
            "url",
            "get",
            "post",
            "form",
        ]);
    });

    test("serves GET and POST verbs", () => {
        expect(add.url()).toBe("/demo/add");
        expect(add()).toEqual({ url: "/demo/add", method: "get" });
        expect(add.post()).toEqual({ url: "/demo/add", method: "post" });
    });

    test("definition lists both verbs", () => {
        expect(add.definition.methods).toEqual(["get", "post"]);
    });

    test("form post spoofs the method over post", () => {
        expect(add.form.post()).toEqual({
            action: "/demo/add",
            method: "post",
        });
    });
});

describe("delete", () => {
    test("properties", () => {
        expect(Object.keys(deleteMethod)).toEqual([
            "definition",
            "url",
            "post",
            "delete",
            "form",
        ]);
    });

    test("reserved action name is suffixed", () => {
        expect(deleteMethod.url({ id: 1 })).toBe("/demo/delete/1");
        expect(deleteMethod.post({ id: 1 })).toEqual({
            url: "/demo/delete/1",
            method: "post",
        });
        expect(deleteMethod.delete({ id: 1 })).toEqual({
            url: "/demo/delete/1",
            method: "delete",
        });
    });

    test("definition", () => {
        expect(deleteMethod.definition).toEqual({
            methods: ["post", "delete"],
            url: "/demo/delete/{id}",
        });
    });

    test("form variants tunnel non-post verbs", () => {
        expect(deleteMethod.form.delete({ id: 1 })).toEqual({
            action: "/demo/delete/1?_method=DELETE",
            method: "post",
        });
    });
});

describe("shared", () => {
    test("properties", () => {
        expect(Object.keys(shared)).toEqual([
            "definition",
            "url",
            "get",
            "form",
        ]);
    });

    test("base-controller action surfaces through the subclass", () => {
        expect(shared.url()).toBe("/demo/shared");
    });
});
