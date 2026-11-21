<?php
declare(strict_types=1);

namespace TestApp\Dto;

/**
 * Request-object fixture: resolved from request data, never from the URL.
 */
final class WidgetData
{
    /**
     * @param array<string, mixed> $data Request data
     */
    private function __construct(
        private array $data,
    ) {
    }

    /**
     * Build the fixture from raw request data.
     *
     * @param array<string, mixed> $data Request data
     * @return static
     */
    public static function createFromArray(array $data): static
    {
        return new self($data);
    }

    /**
     * Read the fixture data back.
     *
     * @return array<string, mixed> Request data
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
