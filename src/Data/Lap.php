<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Data;

final readonly class Lap
{
    public function __construct(
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public float  $totalDistance,
        public int    $totalElapsedTime,
        public int    $totalTimerTime,
        public ?int   $avgHeartRate,
        public ?int   $maxHeartRate,
        public ?float $avgSpeed,
        public ?float $maxSpeed,
        public ?float $avgPower,
        public ?float $maxPower,
        public ?int   $avgCadence,
        public ?float $totalAscent,
        public ?float $totalDescent,
        public int    $lapNumber,
    ) {}
}
