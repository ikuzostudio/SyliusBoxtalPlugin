<?php

namespace Ikuzo\SyliusBoxtalPlugin\Type;

use Symfony\Component\Form\AbstractTypeExtension;
use Ikuzo\SyliusBoxtalPlugin\Boxtal\ClientInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\ChoiceList\Loader\CallbackChoiceLoader;
use Doctrine\Persistence\ObjectRepository;

class BoxtalShippingGatewayTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private ClientInterface $client,
        private ObjectRepository $taxCategoryRepository,
    )
    {   
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('defaultTaxCategory', ChoiceType::class, [
            'label' => 'ikuzo.boxtal.default_tax_category',
            'choice_loader' => new CallbackChoiceLoader(function () {
                $categories = $this->taxCategoryRepository->findAll();

                $choices = [];
                foreach ($categories as $category) {
                    $choices[$category->getName()] = $category->getCode();
                }

                return $choices;
            }),
            'required' => false
        ])->add('contentType', ChoiceType::class, [
            'label' => 'ikuzo.boxtal.content_type',
            'required' => true,
            'empty_data' => 100,
            'choice_loader' => new CallbackChoiceLoader(function () {
                $choices = [];
                $contents = $this->client->getContents();

                foreach ($contents as $row) {
                    $choices[$row['label']] = $row['code'];
                }

                return $choices;
            }),
        ])->add('enabledServices', ChoiceType::class, [
            'label' => 'ikuzo.boxtal.services',
            'multiple' => true,
            'expanded' => true,
            'choice_loader' => new CallbackChoiceLoader(function () {
                $choices = [];
                $carriers = $this->client->getCarriersList();
                
                foreach ($carriers as $carrier) {
                    if (isset($carrier['services']['service'])) {
                        if (isset($carrier['services']['service']['code'])) {
                            $label = sprintf('%s - %s', $carrier['name'], $carrier['services']['service']['label']);
                            $value = sprintf('%s_%s', $carrier['code'], $carrier['services']['service']['code']);
                            $choices[$label] = $value;
                        } else {
                            foreach ($carrier['services']['service'] as $service) {
                                $label = sprintf('%s - %s', $carrier['name'], $service['label']);
                                $value = sprintf('%s_%s', $carrier['code'], $service['code']);
                                $choices[$label] = $value;
                            }   
                        }
                    }

                }

                ksort($choices);
                return $choices;
            }),
            'group_by' => function($choice, $key, $value) {
                return explode('-', $key)[0];
            },
        ]);
    }

    /**
     * Returns an array of extended types.
     */
    public static function getExtendedTypes(): iterable
    {
        return [BoxtalShippingGatewayType::class];
    }
}