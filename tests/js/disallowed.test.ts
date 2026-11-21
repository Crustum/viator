import { describe, expect, test } from "vitest";
import {
    asset,
    deleteMethod,
    file,
    login,
    notFound,
    twoFa,
} from "./generated/actions/Disallowed";
import {
    method404,
    method2fa,
    defaultMethod,
    publicMethod,
    staticMethod,
} from "./generated/routes/disallowed";

describe("disallowed actions (fallback dasherized plus explicit)", () => {
    test("delete stays a single callable", () => {
        expect(deleteMethod.url()).toBe("/disallowed/delete");
    });

    test("fallback and explicit URIs form dicts", () => {
        expect(notFound["/disallowed/404"].url()).toBe("/disallowed/404");
        expect(notFound["/disallowed/not-found"].url()).toBe(
            "/disallowed/not-found",
        );
        expect(twoFa["/disallowed/2fa"].url()).toBe("/disallowed/2fa");
        expect(login["/disallowed/default"].url()).toBe("/disallowed/default");
        expect(asset["/disallowed/public"].url()).toBe("/disallowed/public");
        expect(file["/disallowed/static"].url()).toBe("/disallowed/static");
    });
});

describe("disallowed named routes sanitize to valid identifiers", () => {
    test("numeric and reserved names", () => {
        expect(method404.url()).toBe("/disallowed/404");
        expect(method2fa.url()).toBe("/disallowed/2fa");
        expect(defaultMethod.url()).toBe("/disallowed/default");
        expect(publicMethod.url()).toBe("/disallowed/public");
        expect(staticMethod.url()).toBe("/disallowed/static");
    });
});
