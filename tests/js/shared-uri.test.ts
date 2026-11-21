import { describe, expect, test } from "vitest";
import { handle } from "./generated/actions/SharedUri";

describe("shared URI under several verbs", () => {
    test("fallback keeps all detected verbs", () => {
        expect(handle["/shared-uri/handle/{name}"].url({ name: "a" })).toBe(
            "/shared-uri/handle/a",
        );
    });

    test("explicit routes split by verb with prefixed keys", () => {
        expect(handle["get /shared-uri/{name}"]({ name: "a" })).toEqual({
            url: "/shared-uri/a",
            method: "get",
        });
        expect(handle["post /shared-uri/{name}"]({ name: "a" })).toEqual({
            url: "/shared-uri/a",
            method: "post",
        });
        expect(handle["put|patch /shared-uri/{name}"]({ name: "a" })).toEqual({
            url: "/shared-uri/a",
            method: "put",
        });
        expect(
            handle["put|patch /shared-uri/{name}"].put({ name: "a" }),
        ).toEqual({ url: "/shared-uri/a", method: "put" });
    });
});
