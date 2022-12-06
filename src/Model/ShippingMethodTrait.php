<?php

namespace Ikuzo\SyliusBoxtalPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait ShippingMethodTrait
{
    /** 
     * @ORM\Column(name="boxtal_operator_code", type="string", nullable=true) 
     */
    protected ?string $boxtalOperatorCode = null;

    /** 
     * @ORM\Column(name="boxtal_service_code", type="string", nullable=true) 
     */
    protected ?string $boxtalServiceCode = null;

    public function hasBoxtalOperatorCode(): bool
    {
        return null !== $this->boxtalOperatorCode;
    }

    public function setBoxtalOperatorCode(?string $boxtalOperatorCode): void
    {
        $this->boxtalOperatorCode = $boxtalOperatorCode;
    }

    public function getBoxtalOperatorCode(): ?string
    {
        return $this->boxtalOperatorCode;
    }

    public function hasBoxtalServiceCode(): bool
    {
        return null !== $this->boxtalServiceCode;
    }

    public function setBoxtalServiceCode(?string $boxtalServiceCode): void
    {
        $this->boxtalServiceCode = $boxtalServiceCode;
    }

    public function getBoxtalServiceCode(): ?string
    {
        return $this->boxtalServiceCode;
    }

}