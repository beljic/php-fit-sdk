<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Data;

use Beljic\FitSdk\Profile\Manufacturer;

final readonly class DeviceInfo
{
    public function __construct(
        public Manufacturer $manufacturer,
        public ?string      $productName,
        public ?int         $serialNumber,
        public ?string      $softwareVersion,
    ) {}
}
