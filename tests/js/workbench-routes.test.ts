import { describe, expect, test } from "vitest";
import {
    dashboard,
    exportMethod,
    invalid_js_name,
    myDashedRoute,
} from "./generated/routes/index";
import { customerSector } from "./generated/routes/projects/application";
import { tasks } from "./generated/routes/api/v1";
import { index as taskStatusIndex } from "./generated/routes/api/v1/task-status";
import { index as photosIndex } from "./generated/routes/photos";
import photos from "./generated/routes/photos";
import albums from "./generated/routes/albums";
import { items } from "./generated/routes/dashed-parent/admin";
import { store } from "./generated/routes/my-package";

describe("closure-style named routes", () => {
    test("dashboard", () => {
        expect(dashboard.url()).toBe("/dashboard");
    });

    test("export with reserved param name", () => {
        expect(exportMethod.url({ report: "r", export: "e" })).toBe(
            "/export/r/e",
        );
    });

    test("sanitized and dasherized names", () => {
        expect(invalid_js_name.url()).toBe("/invalid-js-name");
        expect(myDashedRoute.url()).toBe("/dashed-route");
    });

    test("nested and dashed names", () => {
        expect(customerSector.url()).toBe(
            "/projects/application/customer-sector",
        );
        expect(items.url()).toBe("/dashed-parent/admin/items");
        expect(tasks.url()).toBe("/api/v1/tasks");
        expect(taskStatusIndex.url({ task: 7 })).toBe(
            "/api/v1/tasks/7/task-status",
        );
    });

    test("photos and albums", () => {
        expect(photosIndex.url()).toBe("/photos");
        expect(photos.index.window.url()).toBe("/photos/window");
        expect(albums.index.recent.url()).toBe("/albums/recent");
    });

    test("double-colon name collapses", () => {
        expect(store.url()).toBe("/package-route");
    });
});
