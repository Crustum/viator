<?php
declare(strict_types=1);

namespace TestApp\Enum;

/**
 * Simple post status.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
