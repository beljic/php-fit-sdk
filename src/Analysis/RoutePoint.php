<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Analysis;

final readonly class RoutePoint
{
    public function __construct(
        public float  $lat,
        public float  $lon,
        public ?float $altitude = null,
    ) {}
}
