<?php

namespace Ikuzo\SyliusBoxtalPlugin\EventListener;

use BitBag\SyliusShippingExportPlugin\Entity\ShippingExportInterface;
use Doctrine\Persistence\ObjectManager;
use Ikuzo\SyliusBoxtalPlugin\Boxtal\Client;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Webmozart\Assert\Assert;

final class BoxtalShippingExportEventListener
{

    public function __construct(
        private FlashBagInterface $flashBag,
        private Filesystem $filesystem,
        private ObjectManager $shippingExportManager,
        private string $shippingLabelsPath,
        private Client $client
    ) {
    }

    public function exportShipment(ResourceControllerEvent $event): void
    {
        /** @var ShippingExportInterface $shippingExport */
        $shippingExport = $event->getSubject();
        Assert::isInstanceOf($shippingExport, ShippingExportInterface::class);

        $shippingGateway = $shippingExport->getShippingGateway();
        Assert::notNull($shippingGateway);

        if ('boxtal' !== $shippingGateway->getCode()) {
            return;
        }

        try {
            $data = $this->client->createLabel($shippingExport);
        } catch (\Throwable $th) {
            $this->flashBag->add('error', $th->getMessage());

            return;
        }

        if ($data['shipment']) {
            try {
                $pdfContent = file_get_contents($data['shipment']['labels']['label']);
                $this->saveShippingLabel($shippingExport, $pdfContent, 'pdf'); // Save label
            } catch (\Throwable $th) {
            }

            $shippingExport->getShipment()->setTracking($data['shipment']['reference']);
            $this->markShipmentAsExported($shippingExport); // Mark shipment as "Exported"
        }

        $this->flashBag->add('success', 'bitbag.ui.shipment_data_has_been_exported'); // Add success notification
    }

    public function saveShippingLabel(
        ShippingExportInterface $shippingExport,
        string $labelContent,
        string $labelExtension
    ): void {
        $labelPath = $this->shippingLabelsPath
            . '/' . $this->getFilename($shippingExport)
            . '.' . $labelExtension;

        $this->filesystem->dumpFile($labelPath, $labelContent);
        $shippingExport->setLabelPath($labelPath);

        $this->shippingExportManager->persist($shippingExport);
        $this->shippingExportManager->flush();
    }

    private function getFilename(ShippingExportInterface $shippingExport): string
    {
        $shipment = $shippingExport->getShipment();
        Assert::notNull($shipment);

        $order = $shipment->getOrder();
        Assert::notNull($order);

        $orderNumber = $order->getNumber();

        $shipmentId = $shipment->getId();

        return implode(
            '_',
            [
                $shipmentId,
                preg_replace('~[^A-Za-z0-9]~', '', $orderNumber),
            ]
        );
    }

    private function markShipmentAsExported(ShippingExportInterface $shippingExport): void
    {
        $shippingExport->setState(ShippingExportInterface::STATE_EXPORTED);
        $shippingExport->setExportedAt(new \DateTime());

        $this->shippingExportManager->persist($shippingExport);
        $this->shippingExportManager->flush();
    }
}