import { describe, expect, test } from "vitest";
import { nullableBinding, show } from "./generated/actions/ModelBinding";

describe("model binding equivalent (plain string params)", () => {
    test("show resolves the user param", () => {
        expect(show["/users/{user}"].url({ user: "tim" })).toBe("/users/tim");
        expect(show["/users/{user}"]({ user: "tim" })).toEqual({
            url: "/users/tim",
            method: "get",
        });
    });

    test("definition", () => {
        expect(show["/users/{user}"].definition).toEqual({
            methods: ["get"],
            url: "/users/{user}",
        });
    });

    test("fallback URI resolves the user param", () => {
        expect(show["/model-binding/show/{user}"].url({ user: "tim" })).toBe(
            "/model-binding/show/tim",
        );
    });
});

describe("nullable binding equivalent", () => {
    test("explicit URI resolves the token", () => {
        expect(nullableBinding["/users/by-token/{user}"].url({ user: "tok-1" })).toBe(
            "/users/by-token/tok-1",
        );
    });

    test("definition", () => {
        expect(nullableBinding["/users/by-token/{user}"].definition.url).toBe(
            "/users/by-token/{user}",
        );
    });
});
