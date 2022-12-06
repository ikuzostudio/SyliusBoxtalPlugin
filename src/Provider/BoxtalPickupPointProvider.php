<?php

declare(strict_types=1);

namespace Ikuzo\SyliusBoxtalPlugin\Provider;

use Setono\SyliusPickupPointPlugin\Model\PickupPointCode;
use Setono\SyliusPickupPointPlugin\Model\PickupPointInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Webmozart\Assert\Assert;
use Setono\SyliusPickupPointPlugin\Provider\ProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\ShippingMethodRepository;
use Ikuzo\SyliusBoxtalPlugin\Boxtal\ClientInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Doctrine\ORM\EntityManagerInterface;

final class BoxtalPickupPointProvider implements ProviderInterface
{
    public function __construct(
        private FactoryInterface $pickupPointFactory,
        private RepositoryInterface $pickupPointRepository,
        private RequestStack $requestStack,
        private ShippingMethodRepository $shippingMethodRepository,
        private ClientInterface $client,
        private EntityManagerInterface $em,
    )
    {
    }

    private function getShippingMethod(): ShippingMethodInterface
    {
        $request = $this->requestStack->getMainRequest();

        if (!$request->query->has('methodCode')) {
            throw new \Exception("methodCode must be provided", 1);
        }

        $methodCode = $request->query->get('methodCode');
        $method = $this->shippingMethodRepository->findOneBy([
            'code' => $methodCode,
            'enabled' => true
        ]);

        if (!$method) {
            throw new \Exception("method doesnt exist or disabled", 1);
        }
        
        return $method;
    }

    public function findPickupPoints(OrderInterface $order): iterable
    {
        $method = $this->getShippingMethod();
        
        $address = $order->getShippingAddress();
        Assert::notNull($address);

        $countryCode = $address->getCountryCode();
        Assert::notNull($countryCode);

        $pickupPoints = [];

        $rows = $this->client->findPickupPoints($address, $method);

        foreach ($rows as $row) {

            $pickupPoint = $this->pickupPointRepository->findOneByCode(new PickupPointCode($row['code'], $this->getCode(), $countryCode));
            if (!$pickupPoint) {
                $pickupPoint = $this->pickupPointFactory->createNew();
                $pickupPoint->setCode(new PickupPointCode($row['code'], $this->getCode(), $countryCode));

                $this->em->persist($pickupPoint);
            }

            $pickupPoint->setName($row['name']);
            $pickupPoint->setAddress($row['address']);
            $pickupPoint->setZipCode((string) $row['zipcode']);
            $pickupPoint->setCity($row['city']);
            $pickupPoint->setCountry($row['country']);
            $pickupPoint->setLatitude((float)$row['latitude']);
            $pickupPoint->setLongitude((float)$row['longitude']);

            $pickupPoints[] = $pickupPoint;
        }

        $this->em->flush();

        return $pickupPoints;
    }

    public function findPickupPoint(PickupPointCode $code): ?PickupPointInterface
    {
        return $this->pickupPointRepository->findOneByCode($code);
    }

    public function findAllPickupPoints(): iterable
    {
        return [];
    }

    public function getCode(): string
    {
        return 'boxtal';
    }

    public function getName(): string
    {
        return 'Boxtal';
    }

    public function __toString()
    {
        return 'Boxtal';
    }
}
