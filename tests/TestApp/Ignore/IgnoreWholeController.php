<?php
declare(strict_types=1);

namespace TestApp\Ignore;

use Cake\Controller\Controller;
use Crustum\Viator\Attribute\ViatorIgnore;

/**
 * Fixture controller ignored as a whole.
 */
#[ViatorIgnore]
class IgnoreWholeController extends Controller
{
    /**
     * @return void
     */
    public function index(): void
    {
    }
}
