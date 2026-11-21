<?php
declare(strict_types=1);

namespace TestApp\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $name
 * @property float $price
 * @property bool $in_stock
 * @property string|null $sku
 * @property string|null $label
 */
class Product extends Entity
{
}
