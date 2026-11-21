<?php
declare(strict_types=1);

namespace TestApp\Enum;

use Crustum\Viator\Attribute\ViatorIgnore;

/**
 * Source provider with conditional cases.
 */
enum SourceProvider: string
{
    case Github = 'github';
    case Gitlab = 'gitlab';
    #[ViatorIgnore(unless: 'Viator.testFakeSourceProvider')]
    case GitFake = 'gitfake';
    #[ViatorIgnore(when: 'Viator.testHideRetiredSourceProvider')]
    case GitRetired = 'gitretired';
}
