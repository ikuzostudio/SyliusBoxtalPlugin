<?php

namespace Ikuzo\SyliusBoxtalPlugin\Shipping\Resolver;

use Sylius\Component\Shipping\Resolver\ShippingMethodsResolverInterface;
use Sylius\Component\Shipping\Checker\Eligibility\ShippingMethodEligibilityCheckerInterface;
use Sylius\Component\Shipping\Model\ShippingSubjectInterface;
use Doctrine\Persistence\ObjectRepository;
use Ikuzo\SyliusBoxtalPlugin\Boxtal\ClientInterface;

class ShippingMethodsResolver implements ShippingMethodsResolverInterface
{
    public function __construct(
        private ObjectRepository $shippingMethodRepository,
        private ShippingMethodEligibilityCheckerInterface $eligibilityChecker,
        private ClientInterface $client
    ) {
    }

    public function getSupportedMethods(ShippingSubjectInterface $subject): array
    {
        try {
            $this->client->getCotation($subject);
        } catch (\Throwable $th) {
            // silence
        }

        foreach ($this->shippingMethodRepository->findBy(['enabled' => true]) as $shippingMethod) {
            if ($this->eligibilityChecker->isEligible($subject, $shippingMethod)) {
                $methods[] = $shippingMethod;
            }
        }

        return $methods;
    }

    public function supports(ShippingSubjectInterface $subject): bool
    {
        return true;
    }

}