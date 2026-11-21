<?php
declare(strict_types=1);

namespace TestApp\Model\Entity;

use Cake\ORM\Entity;
use Crustum\Viator\Attribute\ViatorIgnore;

/**
 * Internal audit log, excluded from generated types.
 *
 * @property int $id
 * @property string $internalNote
 */
#[ViatorIgnore]
class AuditLog extends Entity
{
}
