<?php
declare(strict_types=1);

namespace Crustum\Viator\Lang\Trait;

use Crustum\Viator\Lang\TypeScript;

/**
 * Fluent docblock metadata for declaration builders.
 *
 * `@see` links point at CakePHP classes and paths.
 */
trait HasMetaTrait
{
    /**
     * @var array<int, string>
     */
    protected array $meta = [];

    /**
     * Reference a class method (`@see \App\Controller\Pages::display`).
     *
     * @param string $class Fully qualified class name
     * @param string $method Method name
     * @param string|null $path Source path for a linked reference
     * @return $this
     */
    public function referenceMethod(string $class, string $method, ?string $path = null): static
    {
        $class = $this->prepClass($class);

        if ($path !== null && $path !== '') {
            $this->link("{$class}::{$method}", $this->prepPath($path));
        } else {
            $this->meta[] = sprintf('@see %s::%s', $class, $method);
        }

        return $this;
    }

    /**
     * Reference a class (`@see \App\Model\Entity\Contact`).
     *
     * @param string $class Fully qualified class name
     * @param string|null $path Source path for a linked reference
     * @return $this
     */
    public function referenceClass(string $class, ?string $path = null): static
    {
        $class = $this->prepClass($class);

        if ($path !== null && $path !== '') {
            $this->link($class, $this->prepPath($path));
        } else {
            $this->meta[] = '@see ' . $class;
        }

        return $this;
    }

    /**
     * Add a linked `@see` entry.
     *
     * @param string $text Link text
     * @param string|null $url Link target, defaults to the text
     * @return $this
     */
    public function link(string $text, ?string $url = null): static
    {
        $this->meta[] = sprintf('@see [%s](%s)', $text, $url ?? $text);

        return $this;
    }

    /**
     * Reference a source file with a line number.
     *
     * @param string $path Source path
     * @param int|null $lineNumber Line number
     * @return $this
     */
    public function referenceFile(string $path, ?int $lineNumber = null): static
    {
        $path = DIRECTORY_SEPARATOR . ltrim($path, '/\\');
        $this->meta[] = $lineNumber === null
            ? sprintf('@see %s', $path)
            : sprintf('@see %s:%d', $path, $lineNumber);

        return $this;
    }

    /**
     * Add a free-form annotation.
     *
     * @param string $key Annotation name
     * @param string $value Annotation value
     * @return $this
     */
    public function annotation(string $key, string $value): static
    {
        $this->meta[] = sprintf('@%s %s', $key, $value);

        return $this;
    }

    /**
     * Render the collected metadata as a docblock.
     *
     * @return string Empty string when no metadata was collected
     */
    protected function meta(): string
    {
        if ($this->meta === []) {
            return '';
        }

        return TypeScript::dockblock($this->meta);
    }

    /**
     * Normalize a class name with a leading backslash.
     *
     * @param string $class Fully qualified class name
     * @return string
     */
    protected function prepClass(string $class): string
    {
        return '\\' . ltrim($class, '\\');
    }

    /**
     * Normalize a path with a leading slash from the project root.
     *
     * @param string $path Source path
     * @return string Root-relative path with slashes
     */
    protected function prepPath(string $path): string
    {
        return TypeScript::relativeDocPath($path);
    }
}
