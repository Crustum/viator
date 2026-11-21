<?php
declare(strict_types=1);

namespace Crustum\Viator\Lang\Trait;

/**
 * Fluent `export` / `export default` flag for declaration builders.
 */
trait CanExportTrait
{
    /**
     * @var bool
     */
    protected bool $export = false;

    /**
     * @var bool
     */
    protected bool $exportDefault = false;

    /**
     * Mark the declaration as exported.
     *
     * @param bool $export Whether to export
     * @return $this
     */
    public function export(bool $export = true): static
    {
        $this->export = $export;

        return $this;
    }

    /**
     * Mark the declaration as a default export.
     *
     * @param bool $export Whether to default-export
     * @return $this
     */
    public function exportDefault(bool $export = true): static
    {
        $this->exportDefault = $export;

        return $this;
    }

    /**
     * Render the export prefix.
     *
     * @return string
     */
    public function exportFormatted(): string
    {
        if ($this->exportDefault) {
            return 'export default ';
        }

        return $this->export ? 'export ' : '';
    }
}
