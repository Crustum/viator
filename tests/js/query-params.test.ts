import { expect, it } from "vitest";
import { deleteMethod, index, view } from "./generated/actions/Demo";

const demo = index;

it("can convert basic params", () => {
    expect(
        demo({
            query: {
                foo: "bar",
                bar: "baz",
            },
        }),
    ).toEqual({
        url: "/demo?foo=bar&bar=baz",
        method: "get",
    });
});

it("can convert array params", () => {
    expect(
        demo({
            query: {
                foo: ["bar", "baz"],
                bar: "qux",
            },
        }),
    ).toEqual({
        url: "/demo?foo%5B%5D=bar&foo%5B%5D=baz&bar=qux",
        method: "get",
    });
});

it("can convert object params", () => {
    expect(
        demo({
            query: {
                foo: {
                    a: "baz",
                    b: "qux",
                },
                bar: "something",
            },
        }),
    ).toEqual({
        url: "/demo?foo%5Ba%5D=baz&foo%5Bb%5D=qux&bar=something",
        method: "get",
    });
});

it("can convert boolean params", () => {
    expect(
        demo({
            query: {
                foo: true,
                bar: false,
            },
        }),
    ).toEqual({
        url: "/demo?foo=1&bar=0",
        method: "get",
    });
});

it("will ignore existing params without star", () => {
    window.location.search = "?foo=bar&bar=baz";

    expect(
        demo({
            query: {
                also: "yes",
                bar: "no",
            },
        }),
    ).toEqual({
        url: "/demo?also=yes&bar=no",
        method: "get",
    });
});

it("can integrate basic params with existing window params", () => {
    window.location.search = "?foo=bar&bar=baz";

    expect(
        demo({
            mergeQuery: {
                also: "yes",
                bar: "no",
            },
        }),
    ).toEqual({
        url: "/demo?foo=bar&also=yes&bar=no",
        method: "get",
    });
});

it("can integrate array params with existing window params", () => {
    window.location.search = "?foo[]=bar&bar=baz";

    expect(
        demo({
            mergeQuery: {
                foo: ["qux", "baz"],
                also: "yes",
            },
        }),
    ).toEqual({
        url: "/demo?bar=baz&foo%5B%5D=qux&foo%5B%5D=baz&also=yes",
        method: "get",
    });
});

it("can integrate object params with existing window params", () => {
    window.location.search = "?foo[baz]=bar&something=else";

    expect(
        demo({
            mergeQuery: {
                foo: { qux: "baz" },
                also: "yes",
            },
        }),
    ).toEqual({
        url: "/demo?something=else&foo%5Bqux%5D=baz&also=yes",
        method: "get",
    });
});

it("can delete existing params via null", () => {
    window.location.search = "?foo=bar&bar=baz";

    expect(
        demo({
            mergeQuery: {
                foo: null,
            },
        }),
    ).toEqual({
        url: "/demo?bar=baz",
        method: "get",
    });
});

it("can delete existing params via undefined", () => {
    window.location.search = "?foo=bar&bar=baz";

    expect(
        demo({
            mergeQuery: {
                foo: undefined,
            },
        }),
    ).toEqual({
        url: "/demo?bar=baz",
        method: "get",
    });
});

it("replaces an existing scalar param when merging an array param", () => {
    window.location.search = "?foo=bar&status=active";

    expect(
        demo({
            mergeQuery: {
                foo: ["qux", "baz"],
            },
        }),
    ).toEqual({
        url: "/demo?status=active&foo%5B%5D=qux&foo%5B%5D=baz",
        method: "get",
    });
});

it("replaces an existing scalar param when merging an object param", () => {
    window.location.search = "?foo=bar&status=active";

    expect(
        demo({
            mergeQuery: {
                foo: { role: "admin" },
            },
        }),
    ).toEqual({
        url: "/demo?status=active&foo%5Brole%5D=admin",
        method: "get",
    });
});

it("replaces an existing array param when merging a scalar param", () => {
    window.location.search = "?foo[]=bar&foo[]=baz&status=active";

    expect(
        demo({
            mergeQuery: {
                foo: "qux",
            },
        }),
    ).toEqual({
        url: "/demo?status=active&foo=qux",
        method: "get",
    });
});

it("can delete existing array params via null", () => {
    window.location.search = "?foo[]=bar&foo[]=baz&status=active";

    expect(
        demo({
            mergeQuery: {
                foo: null,
            },
        }),
    ).toEqual({
        url: "/demo?status=active",
        method: "get",
    });
});

it("can delete existing nested params via null", () => {
    window.location.search = "?foo[role]=admin&foo[team]=ops&status=active";

    expect(
        demo({
            mergeQuery: {
                foo: null,
            },
        }),
    ).toEqual({
        url: "/demo?status=active",
        method: "get",
    });
});

it("replaces an existing array param when merging an object param", () => {
    window.location.search = "?foo[]=bar&foo[]=baz&status=active";

    expect(
        demo({
            mergeQuery: {
                foo: { role: "admin" },
            },
        }),
    ).toEqual({
        url: "/demo?status=active&foo%5Brole%5D=admin",
        method: "get",
    });
});

it("replaces an existing object param when merging an array param", () => {
    window.location.search = "?foo[role]=admin&foo[team]=ops&status=active";

    expect(
        demo({
            mergeQuery: {
                foo: ["qux", "baz"],
            },
        }),
    ).toEqual({
        url: "/demo?status=active&foo%5B%5D=qux&foo%5B%5D=baz",
        method: "get",
    });
});

it("does not clobber params that share a prefix with the merged key", () => {
    window.location.search = "?foo=bar&foobar=keep&foo_baz=keep";

    expect(
        demo({
            mergeQuery: {
                foo: null,
            },
        }),
    ).toEqual({
        url: "/demo?foobar=keep&foo_baz=keep",
        method: "get",
    });
});

it("clears deeply nested params when merging a scalar", () => {
    window.location.search = "?foo[a][b]=x&foo[a][c]=y&status=active";

    expect(
        demo({
            mergeQuery: {
                foo: "scalar",
            },
        }),
    ).toEqual({
        url: "/demo?status=active&foo=scalar",
        method: "get",
    });
});

it("can merge with the form method", () => {
    window.location.search = "?foo=bar&bar=baz";

    expect(
        deleteMethod.form.delete(
            { id: 1 },
            {
                mergeQuery: {
                    foo: "sure",
                },
            },
        ),
    ).toEqual({
        action: "/demo/delete/1?bar=baz&_method=DELETE&foo=sure",
        method: "post",
    });
});

it("can pass nested query parameters", () => {
    expect(
        view(
            { id: 1 },
            {
                query: {
                    search: "Hello World",
                    filters: {
                        users: ["james", "michael", "john"],
                        attributes: {
                            visible: true,
                            status: "example",
                        },
                    },
                },
            },
        ),
    ).toEqual({
        url: "/demo/view/1?search=Hello+World&filters%5Busers%5D%5B%5D=james&filters%5Busers%5D%5B%5D=michael&filters%5Busers%5D%5B%5D=john&filters%5Battributes%5D%5Bvisible%5D=1&filters%5Battributes%5D%5Bstatus%5D=example",
        method: "get",
    });
});

it("replaces existing scalar params when merging nested object values", () => {
    window.location.search = "?parent=og";

    const query = (): {
        string: string;
        number: number;
        boolean: boolean;
    } => {
        const obj = {
            string: "string",
            number: 5,
            boolean: true,
            undefined: undefined,
            null: null,
            array: [],
            object: {},
        };

        return obj;
    };

    expect(
        deleteMethod.form.delete(
            { id: 1 },
            {
                mergeQuery: {
                    parent: query(),
                },
            },
        ),
    ).toEqual({
        action: "/demo/delete/1?_method=DELETE&parent%5Bstring%5D=string&parent%5Bnumber%5D=5&parent%5Bboolean%5D=1",
        method: "post",
    });
});
