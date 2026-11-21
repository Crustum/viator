import Echo from "laravel-echo";
import { describe, expect, test, vi } from "vitest";
import { BroadcastEvents } from "./generated/viator/broadcast-events";

describe("BroadcastEvents", () => {
    test("subscribes Echo to the verbatim event name", () => {
        const bind = vi.fn();
        const echo = new Echo({
            broadcaster: "pusher",
            client: { subscribe: () => ({ bind }) },
            namespace: "Custom.Events",
            withoutInterceptors: true,
        });
        const listener = vi.fn();

        echo.channel("orders.1").listen(BroadcastEvents.TestApp.Event.OrderShipped, listener);

        expect(bind).toHaveBeenCalledWith("TestApp\\Event\\OrderShipped", listener);
    });

    test("exports BroadcastEvents object", () => {
        expect(BroadcastEvents).toBeDefined();
        expect(typeof BroadcastEvents).toBe("object");
    });

    test("has nested TestApp.Event structure", () => {
        expect(BroadcastEvents.TestApp).toBeDefined();
        expect(BroadcastEvents.TestApp.Event).toBeDefined();
    });

    test("has OrderShipped event in nested structure", () => {
        expect(BroadcastEvents.TestApp.Event.OrderShipped).toBe(".TestApp\\Event\\OrderShipped");
    });

    test("baked names keep the verbatim dot-prefixed name", () => {
        expect(BroadcastEvents.TestApp.Event.OrderShipped).toBe(".TestApp\\Event\\OrderShipped");
        expect(BroadcastEvents.TestApp.Event.OrderShipped).toContain("\\");
    });
});
