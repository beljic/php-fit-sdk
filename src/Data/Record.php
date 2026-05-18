<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Data;

final readonly class Record
{
    public function __construct(
        public \DateTimeImmutable $timestamp,
        public ?float $lat,
        public ?float $lon,
        public ?float $altitude,
        public ?int   $heartRate,
        public ?int   $cadence,
        public ?float $speed,
        public ?float $power,
        public ?float $distance,
        public ?float $temperature,
        /** @var array<string, float|int|string> */
        public array  $developerFields = [],
    ) {}
}
