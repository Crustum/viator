<?php
declare(strict_types=1);

namespace TestApp\Enum;

/**
 * Quoting/escaping fixture mirroring workbench QuotedEnum.
 */
enum QuotedEnum: string
{
    case Double = 'say "hi"';
    case Single = "it's here";
    case Backslash = 'back\\slash';
    case Newline = "line\nbreak";

    /**
     * Human label.
     *
     * @return string
     */
    public function label(): string
    {
        return $this->value;
    }
}
