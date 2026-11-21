<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * Parameter-casing fixture mirroring workbench ParameterNameController.
 */
class ParameterNameController extends DemoAppController
{
    /**
     * Camel-case parameter.
     *
     * @param string $camelCase Parameter value
     * @return void
     */
    public function camel(string $camelCase): void
    {
    }

    /**
     * Studly-case parameter.
     *
     * @param string $StudlyCase Parameter value
     * @return void
     */
    public function studly(string $StudlyCase): void
    {
    }

    /**
     * Snake-case parameter.
     *
     * @param string $snake_case Parameter value
     * @return void
     */
    public function snake(string $snake_case): void
    {
    }

    /**
     * Screaming-snake parameter.
     *
     * @param string $SCREAMING_SNAKE_CASE Parameter value
     * @return void
     */
    public function screamingSnake(string $SCREAMING_SNAKE_CASE): void
    {
    }
}
