import { describe, expect, test } from "vitest";
import InertiaController, {
    conditional,
    dashboard,
    profile,
    settings,
    shared,
    unsafe,
    usersList,
} from "./generated/actions/Inertia";
import namedRoutes from "./generated/routes/inertia";
import modularRoutes from "./generated/routes/inertia/modular";
import duplicateRoutes from "./generated/routes/inertia/duplicate";

describe("inertia page actions (component metadata is not emitted yet)", () => {
    test("dashboard resolves its page URL", () => {
        expect(dashboard.url()).toBe("/inertia/dashboard");
        expect(dashboard()).toEqual({ url: "/inertia/dashboard", method: "get" });
    });

    test("definitions carry no component yet", () => {
        expect(dashboard.definition).toEqual({ methods: ["get"], url: "/inertia/dashboard" });
        expect(dashboard()).not.toHaveProperty("component");
        expect(dashboard.definition).not.toHaveProperty("component");
    });

    test("settings, profile, unsafe and usersList resolve", () => {
        expect(settings.url()).toBe("/inertia/settings");
        expect(profile.url()).toBe("/inertia/profile");
        expect(unsafe.url()).toBe("/inertia/unsafe");
        expect(usersList.url()).toBe("/inertia/users-list");
    });

    test("conditional resolves", () => {
        expect(conditional.url()).toBe("/inertia/conditional");
    });

    test("inherited shared action surfaces", () => {
        expect(shared.url()).toBe("/inertia/shared");
    });

    test("default export exposes every action", () => {
        expect(InertiaController.dashboard.url()).toBe("/inertia/dashboard");
        expect(InertiaController.settings.url()).toBe("/inertia/settings");
    });
});

describe("inertia named routes", () => {
    test("named routes resolve without component", () => {
        expect(namedRoutes.dashboard()).toEqual({
            url: "/inertia/dashboard",
            method: "get",
        });
        expect(namedRoutes.dashboard()).not.toHaveProperty("component");
        expect(namedRoutes.settings.url()).toBe("/inertia/settings");
        expect(namedRoutes.profile.url()).toBe("/inertia/profile");
        expect(namedRoutes.unsafe.url()).toBe("/inertia/unsafe");
        expect(namedRoutes.conditional.url()).toBe("/inertia/conditional");
    });

    test("modular logins resolve under their namespace", () => {
        expect(modularRoutes.login.url()).toBe("/inertia/modular/login");
        expect(modularRoutes.register.url()).toBe("/inertia/modular/register");
    });

    test("duplicate inertia route resolves", () => {
        expect(namedRoutes.duplicate.url()).toBe("/inertia/duplicate");
        expect(duplicateRoutes.withData.url()).toBe("/inertia/duplicate-with-data");
    });
});
