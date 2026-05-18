<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Data;

use Beljic\FitSdk\Profile\Sport;

final readonly class Session
{
    /**
     * @param Record[] $records
     * @param Lap[]    $laps
     */
    public function __construct(
        public Sport              $sport,
        public \DateTimeImmutable $startTime,
        public int                $totalElapsedTime,
        public int                $totalTimerTime,
        public float              $totalDistance,
        public ?float             $totalAscent,
        public ?float             $totalDescent,
        public ?int               $avgHeartRate,
        public ?int               $maxHeartRate,
        public ?float             $avgSpeed,
        public ?float             $maxSpeed,
        public ?float             $avgPower,
        public ?float             $maxPower,
        public ?int               $avgCadence,
        public array              $records = [],
        public array              $laps    = [],
    ) {}
}
