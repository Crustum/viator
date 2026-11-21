import { expect, it } from "vitest";
import { BroadcastChannels } from "./generated/viator/broadcast-channels";

it("exports BroadcastChannels object", () => {
    expect(BroadcastChannels).toBeDefined();
    expect(typeof BroadcastChannels).toBe("object");
});

it("has static channel without parameters", () => {
    expect(BroadcastChannels["public-announcements"]).toBe("public-announcements");
});

it("has channel with single parameter", () => {
    expect(typeof BroadcastChannels.orders).toBe("function");
    expect(BroadcastChannels.orders(123)).toBe("orders.123");
    expect(BroadcastChannels.orders("abc")).toBe("orders.abc");
});

it("has nested channel with parameter", () => {
    expect(typeof BroadcastChannels.user).toBe("function");
    const userChannel = BroadcastChannels.user(42);
    expect(typeof userChannel).toBe("object");
    expect(userChannel.notifications).toBe("user.42.notifications");
});

it("has deeply nested channel with parameter", () => {
    expect(typeof BroadcastChannels.chat).toBe("function");
    const chatChannel = BroadcastChannels.chat("room-1");
    expect(typeof chatChannel).toBe("object");
    expect(chatChannel.messages).toBe("chat.room-1.messages");
});

it("keeps backticks and dollars in channel names escaped", () => {
    const quirky = BroadcastChannels.quirky["`tick`-$dollar"]("room-1");
    expect(quirky).toBe("quirky.`tick`-$dollar.room-1");
});
