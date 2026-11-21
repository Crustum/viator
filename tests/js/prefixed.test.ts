import { describe, expect, test } from "vitest";
import Items from "./generated/actions/Mixed/Items";
import Nested from "./generated/actions/Nested/Nested";
import Prism from "./generated/actions/Prism/Prism";
import PrismNested from "./generated/actions/Prism/Prism/Prism";

describe("prefixed and nested barrels", () => {
    test("mixed items", () => {
        expect(Items.index.url()).toBe("/mixed/items");
        expect(Items.edit.url({ item: 1 })).toBe("/mixed/items/1");
        expect(Items.update({ item: 1 })).toEqual({
            url: "/mixed/items/1",
            method: "patch",
        });
    });

    test("nested controller", () => {
        expect(Nested.nested.url()).toBe("/nested/controller");
        expect(Nested.child.url()).toBe("/nested/controller/child");
        expect(Nested.grandchild.url()).toBe(
            "/nested/controller/child/grandchild",
        );
    });

    test("prism collision namespaces", () => {
        expect(Prism.index.url()).toBe("/prism/prism");
        expect(PrismNested.nested.url()).toBe("/prism/prism/nested");
    });
});
