import { afterEach, describe, expect, test, vi } from "vitest";
import {
    addUrlDefault,
    applyUrlDefaults,
    setUrlDefaults,
} from "./generated/viator";
import { onlyDefaults } from "./generated/actions/UrlDefaults";

afterEach(() => {
    setUrlDefaults({});
});

describe("frontend url defaults runtime (domains are Laravel-only)", () => {
    test("applyUrlDefaults fills missing params from backend-registered defaults", () => {
        setUrlDefaults({ locale: "en" });

        expect(applyUrlDefaults({ timezone: "UTC" })).toEqual({
            timezone: "UTC",
            locale: "en",
        });
    });

    test("explicit values win over defaults", () => {
        setUrlDefaults({ locale: "en" });

        expect(applyUrlDefaults({ locale: "es" })).toEqual({ locale: "es" });
    });

    test("addUrlDefault layers onto existing defaults", () => {
        setUrlDefaults({ locale: "en" });
        addUrlDefault("timezone", "UTC");

        expect(applyUrlDefaults(undefined)).toEqual({ locale: "en", timezone: "UTC" });
    });

    test("dynamic function-based defaults are re-evaluated per call", () => {
        let callCount = 0;
        setUrlDefaults(() => {
            callCount += 1;

            return { locale: `dynamic-${callCount}` };
        });

        expect(applyUrlDefaults(undefined)).toEqual({ locale: "dynamic-1" });
        expect(applyUrlDefaults(undefined)).toEqual({ locale: "dynamic-2" });
        expect(callCount).toBe(2);
    });

    test("frontend defaults flow into defaulted route params", () => {
        setUrlDefaults({ locale: "fr" });

        expect(onlyDefaults["/with-defaults/{locale?}"].url()).toBe("/with-defaults/fr");
    });

    test("setUrlDefaults accepts a function", () => {
        const spy = vi.fn(() => ({ locale: "en" }));
        setUrlDefaults(spy);

        expect(applyUrlDefaults(undefined)).toEqual({ locale: "en" });
        expect(spy).toHaveBeenCalled();
    });
});
