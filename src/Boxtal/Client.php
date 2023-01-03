<?php

namespace Ikuzo\SyliusBoxtalPlugin\Boxtal;

use GuzzleHttp\Client as HttpClient;
use BitBag\SyliusShippingExportPlugin\Repository\ShippingGatewayRepositoryInterface;
use BitBag\SyliusShippingExportPlugin\Entity\ShippingGatewayInterface;
use Sylius\Component\Resource\Factory\TranslatableFactory;
use Sylius\Bundle\CoreBundle\Doctrine\ORM\ShippingMethodRepository;
use Sylius\Component\Addressing\Matcher\ZoneMatcherInterface;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Shipping\Model\ShippingSubjectInterface;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\TaxationBundle\Doctrine\ORM\TaxCategoryRepository;
use Sylius\Component\Addressing\Model\AddressInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use BitBag\SyliusShippingExportPlugin\Entity\ShippingExportInterface;
use Ikuzo\SyliusBoxtalPlugin\Service\PickupPointService;
use Sylius\Component\Resource\Factory\FactoryInterface;

class Client implements ClientInterface
{
    public function __construct(
        private string $email,
        private string $password,
        private bool $testMode,
        private ShippingGatewayRepositoryInterface $shippingGatewayRepository,
        private ZoneMatcherInterface $zoneMatcher,
        private FactoryInterface $shippingMethodFactory,
        private ShippingMethodRepository $shippingMethodRepository,
        private EntityManagerInterface $em,
        private TaxCategoryRepository $taxCategoryRepository,
        private PickupPointService $pickupPointService
    )
    {
        $this->initClient();
    }
    
    public function findGateway(): ShippingGatewayInterface
    {
        $gateway = $this->shippingGatewayRepository->findOneByCode('boxtal');

        if (!$gateway instanceof ShippingGatewayInterface) {
            throw new \Exception("Boxtal Shipping Gateway is not configured", 1);
        }

        return $gateway;
    }

    public function findPickupPoints(AddressInterface $address, ShippingMethodInterface $shippingMethod): ?array
    {
        $response = $this->client->request('GET', '/api/v1/listpoints', [
            'query' => [
                'collecte' => 'dest',
                'pays' => $address->getCountryCode(),
                'ville' => $address->getCity(),
                'cp' => $address->getPostcode(),
                'adresse' => $address->getStreet(),
                'carriers' => [$shippingMethod->getBoxtalOperatorCode()]
            ]
        ]);

        $xml = simplexml_load_string($response->getBody());
        $array = json_decode(json_encode((array)$xml), TRUE);
        
        return $array['carrier']['points']['point'];
    }

    private function initClient(): void
    {
        $url = ($this->testMode) ? 'https://test.envoimoinscher.com' : 'https://www.envoimoinscher.com';

        $this->client = new HttpClient([
            'base_uri' => $url,
            'headers' => [
                'Authorization' => base64_encode(sprintf('%s:%s', 
                    $this->email,
                    $this->password
                ))
            ]
        ]);
    }

    public function getCarriersList(): array
    {
        $response = $this->client->request('GET', '/api/v1/carriers_list', [
            'query' => [
                'channel' => 'prestashop',
                'version' => '1.7'
            ]
        ]);

        $xml = simplexml_load_string($response->getBody());
        $array = json_decode(json_encode((array)$xml), TRUE);
        
        return $array['operator'];
    }

    public function getContents(): array
    {
        $response = $this->client->request('GET', '/api/v1/contents');
        $xml = simplexml_load_string($response->getBody());
        $array = json_decode(json_encode((array)$xml), TRUE);
        return $array['content'];
    }

    public function getCotation(ShippingSubjectInterface $subject): array
    {
        $order = $subject->getOrder();
        $channel = $order->getChannel();

        $response = $this->client->request('GET', '/api/v1/cotation', [
            'query' => [
                'colis_1.poids' => ($subject->getShippingWeight() > 0) ? $subject->getShippingWeight() : 1,
                'colis_1.longueur' => 10,
                'colis_1.largeur' => 10,
                'colis_1.hauteur' => 5,
                'colis.valeur' => round($order->getTotal() / 100, 2), 
                'code_contenu' => $this->findGateway()->getConfigValue('contentType'),
                'shipper.country' => $channel->getShopBillingData()->getCountryCode(),
                'shipper.zipcode' => $channel->getShopBillingData()->getPostcode(),
                'shipper.city' => $channel->getShopBillingData()->getCity(),
                'shipper.societe' => $channel->getShopBillingData()->getCompany(),
                'shipper.type' => 'company',
                'recipient.country' => $order->getShippingAddress()->getCountryCode(),
                'recipient.zipcode' => $order->getShippingAddress()->getPostcode(),
                'recipient.city' => $order->getShippingAddress()->getCity(),
                'recipient.type' => 'individual',
            ]
        ]);

        $xml = simplexml_load_string($response->getBody());
        $array = json_decode(json_encode((array)$xml), TRUE);

        return $this->insertShippingMethods($array['shipment']['offer'], $subject);
    }

    public function getPriceForShippingMethod(ShippingSubjectInterface $subject, ?string $operatorCode, ?string $serviceCode): ?int
    {
        $order = $subject->getOrder();
        $channel = $order->getChannel();
        $shippingAddress = ($order->getShippingAddress()) ? $order->getShippingAddress() : $channel->getShopBillingData();

        $response = $this->client->request('GET', '/api/v1/cotation', [
            'query' => [
                'colis_1.poids' => ($subject->getShippingWeight() > 0) ? $subject->getShippingWeight() : 1,
                'colis_1.longueur' => 10,
                'colis_1.largeur' => 10,
                'colis_1.hauteur' => 5,
                'colis.valeur' => round($order->getTotal() / 100, 2), 
                'code_contenu' => $this->findGateway()->getConfigValue('contentType'),
                'shipper.country' => $channel->getShopBillingData()->getCountryCode(),
                'shipper.zipcode' => $channel->getShopBillingData()->getPostcode(),
                'shipper.city' => $channel->getShopBillingData()->getCity(),
                'shipper.societe' => $channel->getShopBillingData()->getCompany(),
                'shipper.type' => 'company',
                'recipient.country' => $shippingAddress->getCountryCode(),
                'recipient.zipcode' => $shippingAddress->getPostcode(),
                'recipient.city' => $shippingAddress->getCity(),
                'recipient.type' => 'individual',
                'operator' => $operatorCode,
                'service' => $serviceCode
            ]
        ]);

        $xml = simplexml_load_string($response->getBody());
        $array = json_decode(json_encode((array)$xml), TRUE);

        if (isset($array['shipment']['offer'][0])) {
            return (int)round($array['shipment']['offer'][0]['price']['tax-exclusive'] * 100);
        }

        return (int)round($array['shipment']['offer']['price']['tax-exclusive'] * 100);
    }

    public function createLabel(ShippingExportInterface $shippingExport): array
    {
        $shipment = $shippingExport->getShipment();
        $order = $shipment->getOrder();
        $channel = $order->getChannel();

        $phoneNumberUtil = \libphonenumber\PhoneNumberUtil::getInstance();

        $channelPhoneNumber = $phoneNumberUtil->parse($channel->getContactPhoneNumber(), $channel->getShopBillingData()->getCountryCode());
        $recipientPhoneNumber = $phoneNumberUtil->parse($order->getShippingAddress()->getPhoneNumber(), $order->getShippingAddress()->getCountryCode());

        $body = [
            'colis_1.poids' => ($shipment->getShippingWeight() > 0) ? $shipment->getShippingWeight() : 1,
            'colis_1.longueur' => 10,
            'colis_1.largeur' => 10,
            'colis_1.hauteur' => 5,
            'colis.valeur' => round($order->getTotal() / 100, 2), 
            'colis.description'	=> sprintf('Commande #%s - Expédition #%s', 
                $order->getNumber(),
                $shipment->getId()
            ),
            'code_contenu' => $this->findGateway()->getConfigValue('contentType'),
            'shipper.country' => $channel->getShopBillingData()->getCountryCode(),
            'shipper.zipcode' => $channel->getShopBillingData()->getPostcode(),
            'shipper.city' => $channel->getShopBillingData()->getCity(),
            'shipper.societe' => $channel->getShopBillingData()->getCompany(),
            'shipper.name' => $channel->getShopBillingData()->getCompany(),
            'shipper.type' => 'company',
            'shipper.email' => $channel->getContactEmail(),
            'shipper.firstname' => 'Jon',
            'shipper.lastname' => 'Snow',
            'shipper.phone' => $phoneNumberUtil->format($channelPhoneNumber, \libphonenumber\PhoneNumberFormat::NATIONAL),
            'recipient.country' => $order->getShippingAddress()->getCountryCode(),
            'recipient.zipcode' => $order->getShippingAddress()->getPostcode(),
            'recipient.city' => $order->getShippingAddress()->getCity(),
            'recipient.type' => 'individual',
            'recipient.firstname' => $order->getShippingAddress()->getFirstName(),
            'recipient.lastname' => $order->getShippingAddress()->getLastName(),
            'recipient.email' => ($order->getCustomer()->getEmail()) ? $order->getCustomer()->getEmail() : $channel->getContactEmail(),
            'recipient.phone' => $phoneNumberUtil->format($recipientPhoneNumber, \libphonenumber\PhoneNumberFormat::NATIONAL),
            'operator' => $shipment->getMethod()->getBoxtalOperatorCode(),
            'service' => $shipment->getMethod()->getBoxtalServiceCode(),
            'reference_externe' => $shipment->getId(),
            'raison' => 'sale'
        ];

        if ($shipment->getPickupPointId() !== null) {
            $pickupPoint = $this->pickupPointService->resolvePickupPointWithId($shipment->getPickupPointId());
            if ($pickupPoint) {
                $body['retrait.pointrelais'] = $pickupPoint->getCode()->getIdPart();
            }
        }

        foreach ($order->getItems() as $key => $item) {
            $body['proforma_'.($key+1).'.description_en'] = $item->getVariant()->getProduct()->getName();
            $body['proforma_'.($key+1).'.description_fr'] = $item->getVariant()->getProduct()->getName();
            $body['proforma_'.($key+1).'.nombre'] = $item->getQuantity();
            $body['proforma_'.($key+1).'.valeur'] = (float) round($item->getTotal()/100, 2);
            $body['proforma_'.($key+1).'.origine'] = $order->getChannel()->getShopBillingData()->getCountryCode();
            $body['proforma_'.($key+1).'.poids'] = ($shipment->getShippingWeight() > 0) ? $shipment->getShippingWeight() / $order->getTotalQuantity() : 1 / $order->getTotalQuantity();
        }

        try {
            $response = $this->client->request('POST', '/api/v1/order', [
                'query' => $body   
            ]);
        } catch (\Throwable $th) {
            $xml = simplexml_load_string($th->getResponse()->getBody());
            $array = json_decode(json_encode((array)$xml), TRUE);

            throw new \Exception($array['message'], 1);
        }


        $xml = simplexml_load_string($response->getBody());
        $array = json_decode(json_encode((array)$xml), TRUE);
        
        return $array;
    }

    private function insertShippingMethods(array $offers, ShippingSubjectInterface $subject): array
    {
        $zone = $this->zoneMatcher->match($subject->getOrder()->getShippingAddress());
        $gateway = $this->findGateway();
        $shippingMethods = [];

        if ($zone instanceof ZoneInterface) {
            foreach ($offers as $offer) {
                $shippingMethodCode = sprintf('BOXTAL_%s_%s_%s',
                    $offer['operator']['code'],
                    $offer['service']['code'],
                    $zone->getCode()
                );

                $shippingMethod = $this->shippingMethodRepository->findOneByCode($shippingMethodCode);

                if ($shippingMethod === null) {
                    if (!in_array($offer['operator']['code'].'_'.$offer['service']['code'], $gateway->getConfigValue('enabledServices'))) {
                        continue;
                    }

                    $shippingMethod = $this->shippingMethodFactory->createNew();
                    $shippingMethod->setCode($shippingMethodCode);
                    $shippingMethod->addChannel($subject->getOrder()->getChannel());
                    $shippingMethod->setEnabled(true);
                    $shippingMethod->setName($offer['operator']['label'].' - '.$offer['service']['label']);
                    
                    if (isset($offer['delivery']['type']['label'])) {
                        $shippingMethod->setDescription($offer['delivery']['type']['label']);
                    }

                    $this->em->persist($shippingMethod);
                } else {
                    if (!in_array($offer['operator']['code'].'_'.$offer['service']['code'], $gateway->getConfigValue('enabledServices'))) {
                        $shippingMethod->setEnabled(false);
                    }
                }

                $shippingMethod->setConfiguration([
                    'boxtal' => [
                        'operator' => $offer['operator']['code'],
                        'service' => $offer['service']['code'],
                    ]
                ]);

                if ($offer['delivery']['type']['code'] === 'PICKUP_POINT') {
                    $shippingMethod->setPickupPointProvider('boxtal');
                }
                
                if ($shippingMethod->getTaxCategory() === null && $gateway->getConfigValue('defaultTaxCategory') !== null) {
                    $taxCategory = $this->taxCategoryRepository->findOneBy(['code' => $gateway->getConfigValue('defaultTaxCategory')]);
                    $shippingMethod->setTaxCategory($taxCategory);
                }

                $shippingMethod->setBoxtalOperatorCode($offer['operator']['code']);
                $shippingMethod->setBoxtalServiceCode($offer['service']['code']);
                $shippingMethod->setCalculator('boxtal');
                $shippingMethod->setZone($zone);

                $toAdd = true;
                foreach ($gateway->getShippingMethods() as $gatewayShippingMethod) {
                    if ($gatewayShippingMethod->getCode() === $shippingMethodCode) {
                        $toAdd = false;
                    }
                }

                if ($toAdd) {
                    $gateway->addShippingMethod($shippingMethod);
                }

                if ($shippingMethod->isEnabled()) {
                    $shippingMethods[] = $shippingMethod;
                }

                
                $this->em->flush();
            }
        }

        return $shippingMethods;
    }
}