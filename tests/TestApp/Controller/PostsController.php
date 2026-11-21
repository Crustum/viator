<?php
declare(strict_types=1);

namespace TestApp\Controller;

/**
 * REST CRUD fixture mirroring workbench PostController.
 */
class PostsController extends DemoAppController
{
    /**
     * List posts.
     *
     * @return void
     */
    public function index(): void
    {
    }

    /**
     * Show the create form.
     *
     * @return void
     */
    public function create(): void
    {
    }

    /**
     * Store a new post.
     *
     * @return void
     */
    public function store(): void
    {
        $this->request->allowMethod(['post']);
    }

    /**
     * Show one post.
     *
     * @param string $post Post id
     * @return void
     */
    public function show(string $post): void
    {
    }

    /**
     * Show the edit form.
     *
     * @param string $post Post id
     * @return void
     */
    public function edit(string $post): void
    {
    }

    /**
     * Update one post.
     *
     * @param string $post Post id
     * @return void
     */
    public function update(string $post): void
    {
        $this->request->allowMethod(['patch', 'put']);
    }

    /**
     * Delete one post.
     *
     * @param string $post Post id
     * @return void
     */
    public function destroy(string $post): void
    {
        $this->request->allowMethod(['delete']);
    }
}
