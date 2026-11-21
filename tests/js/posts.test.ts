import { describe, expect, test } from "vitest";
import {
    create,
    destroy,
    edit,
    index,
    indexExplicit,
    shared,
    show,
    store,
    update,
} from "./generated/actions/Posts";

describe("posts index", () => {
    test("root URL keeps the plain method name", () => {
        expect(index.url()).toBe("/posts");
        expect(index()).toEqual({ url: "/posts", method: "get" });
    });

    test("explicit action segment gets its own callable", () => {
        expect(indexExplicit.url()).toBe("/posts/index");
    });
});

describe("posts create/store", () => {
    test("create is GET", () => {
        expect(create.url()).toBe("/posts/create");
        expect(create()).toEqual({ url: "/posts/create", method: "get" });
    });

    test("store is a URI-keyed dict (fallback plus REST)", () => {
        expect(store["/posts"].url()).toBe("/posts");
        expect(store["/posts"]()).toEqual({ url: "/posts", method: "post" });
        expect(store["/posts/store"].url()).toBe("/posts/store");
    });
});

describe("posts show/edit/update/destroy", () => {
    test("show resolves both URIs", () => {
        expect(show["/posts/{post}"].url({ post: 1 })).toBe("/posts/1");
        expect(show["/posts/show/{post}"].url({ post: 1 })).toBe(
            "/posts/show/1",
        );
    });

    test("edit resolves both URIs", () => {
        expect(edit["/posts/{post}/edit"].url({ post: 2 })).toBe(
            "/posts/2/edit",
        );
        expect(edit["/posts/edit/{post}"].url({ post: 2 })).toBe(
            "/posts/edit/2",
        );
    });

    test("update tunnels PATCH over post forms", () => {
        expect(update["/posts/{post}"]({ post: 3 })).toEqual({
            url: "/posts/3",
            method: "patch",
        });
        expect(update["/posts/{post}"].form.patch({ post: 3 })).toEqual({
            action: "/posts/3?_method=PATCH",
            method: "post",
        });
    });

    test("destroy resolves both URIs as delete", () => {
        expect(destroy["/posts/{post}"]({ post: 4 })).toEqual({
            url: "/posts/4",
            method: "delete",
        });
        expect(destroy["/posts/destroy/{post}"]({ post: 4 })).toEqual({
            url: "/posts/destroy/4",
            method: "delete",
        });
    });

    test("base shared action surfaces", () => {
        expect(shared.url()).toBe("/posts/shared");
    });
});
