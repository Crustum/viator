import { expect, it } from "vitest";
import { archive, home } from "./generated/routes";
import nested from "./generated/routes/nested";
import { view } from "./generated/routes/users";

it("exports named routes", () => {
    expect(view.url({ id: 1 })).toBe("/users/1");
    expect(view({ id: 1 })).toEqual({
        url: "/users/1",
        method: "get",
    });
});

it("accepts scalar and array shorthands", () => {
    expect(view.url(1)).toBe("/users/1");
    expect(view.url([1])).toBe("/users/1");
});

it("resolves the root route without a trailing slash", () => {
    expect(home.url()).toBe("/");
    expect(home()).toEqual({ url: "/", method: "get" });
});

it("applies route defaults for optional parameters", () => {
    expect(archive.url()).toBe("/archive/2024");
    expect(archive.url({ year: "2025" })).toBe("/archive/2025");
    expect(archive.definition.url).toBe("/archive/{year?}");
});

it("handles conflicting nested route names", () => {
    expect(nested.child().url).toBe("/nested/child");
    expect(nested.child.grandchild().url).toBe("/nested/child/grandchild");
});
