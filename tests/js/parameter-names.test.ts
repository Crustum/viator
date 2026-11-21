import { describe, expect, test } from "vitest";
import {
    camel,
    screamingSnake,
    snake,
    studly,
} from "./generated/actions/ParameterName";

describe("parameter casing", () => {
    test("explicit workbench-shaped URIs", () => {
        expect(
            camel["/parameter-names/{camelCase}/camel"].url({
                camelCase: "v",
            }),
        ).toBe("/parameter-names/v/camel");
        expect(
            studly["/parameter-names/{StudlyCase}/studly"].url({
                StudlyCase: "v",
            }),
        ).toBe("/parameter-names/v/studly");
        expect(
            snake["/parameter-names/{snake_case}/snake"].url({
                snake_case: "v",
            }),
        ).toBe("/parameter-names/v/snake");
        expect(
            screamingSnake[
                "/parameter-names/{SCREAMING_SNAKE_CASE}/screaming-snake"
            ].url({ SCREAMING_SNAKE_CASE: "v" }),
        ).toBe("/parameter-names/v/screaming-snake");
    });

    test("fallback dasherized URIs", () => {
        expect(
            camel["/parameter-name/camel/{camelCase}"].url({
                camelCase: "v",
            }),
        ).toBe("/parameter-name/camel/v");
    });

    test("explicit definitions keep the cased placeholders", () => {
        expect(camel["/parameter-names/{camelCase}/camel"].definition).toEqual({
            methods: ["get"],
            url: "/parameter-names/{camelCase}/camel",
        });
        expect(studly["/parameter-names/{StudlyCase}/studly"].definition).toEqual({
            methods: ["get"],
            url: "/parameter-names/{StudlyCase}/studly",
        });
        expect(snake["/parameter-names/{snake_case}/snake"].definition).toEqual({
            methods: ["get"],
            url: "/parameter-names/{snake_case}/snake",
        });
        expect(
            screamingSnake["/parameter-names/{SCREAMING_SNAKE_CASE}/screaming-snake"].definition,
        ).toEqual({
            methods: ["get"],
            url: "/parameter-names/{SCREAMING_SNAKE_CASE}/screaming-snake",
        });
    });
});
