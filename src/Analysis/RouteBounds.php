<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Analysis;

final readonly class RouteBounds
{
    public function __construct(
        public float $minLat,
        public float $maxLat,
        public float $minLon,
        public float $maxLon,
    ) {}

    /** @return array{lat: float, lon: float} */
    public function center(): array
    {
        return [
            'lat' => ($this->minLat + $this->maxLat) / 2.0,
            'lon' => ($this->minLon + $this->maxLon) / 2.0,
        ];
    }
}
