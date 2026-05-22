<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Analysis;

final readonly class SensorPresence
{
    public function __construct(
        public bool $hasGps,
        public bool $hasHeartRate,
        public bool $hasCadence,
        public bool $hasPower,
        public bool $hasTemperature,
        public bool $hasDeveloperFields,
    ) {}
}
