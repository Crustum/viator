import { describe, expect, test } from "vitest";
import { same } from "./generated/actions/TwoRoutesSameAction";

describe("one action on two URLs", () => {
    test("URI-keyed dict", () => {
        expect(same["/two-routes-one-action-1"].url()).toBe(
            "/two-routes-one-action-1",
        );
        expect(same["/two-routes-one-action-2"]()).toEqual({
            url: "/two-routes-one-action-2",
            method: "get",
        });
    });
});
