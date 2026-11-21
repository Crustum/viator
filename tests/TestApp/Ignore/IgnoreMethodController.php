<?php
declare(strict_types=1);

namespace TestApp\Ignore;

use Cake\Controller\Controller;
use Crustum\Viator\Attribute\ViatorIgnore;

/**
 * Fixture controller with method-level ignore markers.
 */
class IgnoreMethodController extends Controller
{
    /**
     * @return void
     */
    public function index(): void
    {
    }

    /**
     * @return void
     */
    #[ViatorIgnore]
    public function hidden(): void
    {
    }

    /**
     * @return void
     */
    #[ViatorIgnore(when: 'Viator.testHideConditional')]
    public function conditional(): void
    {
    }

    /**
     * @return void
     */
    #[ViatorIgnore(unless: 'Viator.testKeepConditional')]
    public function keptUnless(): void
    {
    }
}
