<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Analysis;

use Beljic\FitSdk\Profile\Sport;

final readonly class ActivityStats
{
    /**
     * @param LapStats[] $laps
     */
    public function __construct(
        public Sport              $sport,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public int                $sessionCount,
        public float              $totalDistance,
        public int                $duration,
        public int                $movingTime,
        /** seconds per km, null when distance is zero */
        public ?float             $pace,
        public ?int               $avgHeartRate,
        public ?int               $maxHeartRate,
        public ?float             $avgSpeed,
        public ?float             $maxSpeed,
        public ?float             $avgPower,
        public ?float             $maxPower,
        public ?int               $avgCadence,
        public ?float             $totalAscent,
        public ?float             $totalDescent,
        public ?RouteBounds       $bounds,
        public array              $laps,
        public SensorPresence     $sensors,
    ) {}
}
