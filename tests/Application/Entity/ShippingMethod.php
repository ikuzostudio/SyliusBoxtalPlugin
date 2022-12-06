<?php

declare(strict_types=1);

namespace Tests\Ikuzo\SyliusBoxtalPlugin\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ikuzo\SyliusBoxtalPlugin\Model\ShippingMethodTrait;
use Sylius\Component\Core\Model\ShippingMethod as BaseShippingMethod;
use Setono\SyliusPickupPointPlugin\Model\PickupPointProviderAwareTrait;
use Setono\SyliusPickupPointPlugin\Model\ShippingMethodInterface;

/**
 * @ORM\Entity()
 * @ORM\Table(name="sylius_shipping_method")
 */
class ShippingMethod extends BaseShippingMethod implements ShippingMethodInterface
{
    use ShippingMethodTrait;
    use PickupPointProviderAwareTrait;
}