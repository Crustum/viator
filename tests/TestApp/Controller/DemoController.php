<?php
declare(strict_types=1);

namespace TestApp\Controller;

use Cake\Event\EventInterface;

/**
 * Fixture controller for the routes generator.
 */
class DemoController extends DemoAppController
{
    use DemoHelperTrait;

    /**
     * Lifecycle override, must never leak into generated functions.
     *
     * @param \Cake\Event\EventInterface $event Event
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
    }

    /**
     * Index action without parameters.
     *
     * @return void
     */
    public function index(): void
    {
    }

    /**
     * View action with a required parameter.
     *
     * @param string $id Record id
     * @return void
     */
    public function view(string $id): void
    {
    }

    /**
     * Edit action with a required and an optional parameter.
     *
     * @param string $id Record id
     * @param string|null $mode Edit mode
     * @return void
     */
    public function edit(string $id, ?string $mode = null): void
    {
    }

    /**
     * Add action serving both GET (form) and POST (submit).
     *
     * @return void
     */
    public function add(): void
    {
        if ($this->request->is('post')) {
            return;
        }
    }

    /**
     * Delete action restricted to POST and DELETE.
     *
     * @param string $id Record id
     * @return void
     */
    public function delete(string $id): void
    {
        $this->request->allowMethod(['post', 'delete']);
    }

    /**
     * Filter action with three optional parameters.
     *
     * @param string|null $one First filter
     * @param string|null $two Second filter
     * @param string|null $three Third filter
     * @return void
     */
    public function filter(?string $one = null, ?string $two = null, ?string $three = null): void
    {
    }

    /**
     * Action named like the `options` query variable.
     *
     * The generated function arguments must use a collision-safe local
     * name (`routeOptions`) instead of shadowing it.
     *
     * @return void
     */
    public function options(): void
    {
    }
}
