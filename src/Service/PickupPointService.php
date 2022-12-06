<?php

namespace Ikuzo\SyliusBoxtalPlugin\Service;

use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\DataTransformerInterface;
use Setono\SyliusPickupPointPlugin\Model\PickupPointInterface;

class PickupPointService
{
    public function __construct(
        private RepositoryInterface $pickupPointRepository,
        private DataTransformerInterface $pickupPointToIdentifierTransformer
    )
    {
        
    }

    public function resolvePickupPointWithId(string $id): ?PickupPointInterface
    {
        $pickupPoint = $this->pickupPointToIdentifierTransformer->reverseTransform($id);
        
        return $pickupPoint;
    }
}