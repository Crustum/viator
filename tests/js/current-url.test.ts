import { describe, expect, test } from "vitest";
import { isCurrentOrParentUrl, isCurrentUrl } from "./generated/viator";
import { overview } from "./generated/actions/Admin/Dashboard";

describe("isCurrentUrl", () => {
    test("exact match", () => {
        expect(isCurrentUrl("/admin/dashboard/overview", "/admin/dashboard/overview")).toBe(true);
    });

    test("ignores trailing slashes", () => {
        expect(isCurrentUrl("/admin/dashboard/overview/", "/admin/dashboard/overview")).toBe(true);
        expect(isCurrentUrl("/admin/dashboard/overview", "/admin/dashboard/overview/")).toBe(true);
    });

    test("strips query string and hash from both sides", () => {
        expect(isCurrentUrl("/demo?page=2", "/demo")).toBe(true);
        expect(isCurrentUrl("/demo", "/demo?page=2#top")).toBe(true);
    });

    test("rejects prefix without segment boundary", () => {
        expect(isCurrentUrl("/foobar", "/foo/bar")).toBe(false);
        expect(isCurrentUrl("/foo", "/foobar")).toBe(false);
    });

    test("strict mode rejects children", () => {
        expect(isCurrentUrl("/foo", "/foo/bar")).toBe(false);
        expect(isCurrentUrl("/foo", "/foo/bar", false)).toBe(false);
    });

    test("works with generated .url() output", () => {
        expect(isCurrentUrl(overview.url(), "/admin/dashboard/overview")).toBe(true);
        expect(isCurrentUrl(overview.url(), "/admin/dashboard")).toBe(false);
    });

    test("defaults to window.location.pathname", () => {
        expect(window.location.pathname).toBe("/");
        expect(isCurrentUrl("/")).toBe(true);
    });
});

describe("isCurrentOrParentUrl", () => {
    test("parent matches child", () => {
        expect(isCurrentOrParentUrl("/admin", "/admin/dashboard/overview")).toBe(true);
        expect(isCurrentOrParentUrl("/admin/dashboard", "/admin/dashboard/overview")).toBe(true);
    });

    test("root is parent of everything", () => {
        expect(isCurrentOrParentUrl("/", "/admin/dashboard")).toBe(true);
    });

    test("rejects non-boundary prefixes", () => {
        expect(isCurrentOrParentUrl("/admin-dash", "/admin/dashboard")).toBe(false);
        expect(isCurrentOrParentUrl("/foo", "/foobar")).toBe(false);
    });

    test("exact still matches", () => {
        expect(isCurrentOrParentUrl("/admin/dashboard", "/admin/dashboard")).toBe(true);
    });
});
