<?php

declare(strict_types=1);

namespace Ikuzo\SyliusBoxtalPlugin\Shipping\Calculator;

use Ikuzo\SyliusBoxtalPlugin\Boxtal\ClientInterface;
use Sylius\Component\Shipping\Calculator\CalculatorInterface;
use Sylius\Component\Shipping\Model\ShipmentInterface;

final class BoxtalCalculator implements CalculatorInterface
{
    public function __construct(private ClientInterface $client)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function calculate(ShipmentInterface $subject, array $configuration): int
    {
        return $this->client->getPriceForShippingMethod($subject, $configuration['boxtal']['operator'], $configuration['boxtal']['service']);
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'boxtal';
    }
}