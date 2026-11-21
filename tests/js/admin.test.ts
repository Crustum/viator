import { describe, expect, test } from "vitest";
import Dashboard, { overview, shared } from "./generated/actions/Admin/Dashboard";
import admin from "./generated/actions/Admin";

describe("prefixed controller", () => {
    test("url", () => {
        expect(overview.url()).toBe("/admin/dashboard/overview");
    });

    test("definition", () => {
        expect(overview.definition.url).toBe("/admin/dashboard/overview");
    });

    test("barrel re-exports the controller", () => {
        expect(admin.Dashboard.overview.url()).toBe("/admin/dashboard/overview");
        expect(Dashboard.overview.url()).toBe("/admin/dashboard/overview");
    });

    test("inherited actions resolve under the prefix", () => {
        expect(shared.url()).toBe("/admin/dashboard/shared");
    });
});
