<?php
declare(strict_types=1);

namespace TestApp\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property string|null $email_verified_at
 * @property string|null $created
 * @property string|null $modified
 * @property \TestApp\Model\Entity\Product $featuredProduct
 * @property array<int, \TestApp\Model\Entity\Product> $relatedProducts
 */
class User extends Entity
{
}
