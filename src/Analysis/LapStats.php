<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Analysis;

use Beljic\FitSdk\Data\Lap;

final readonly class LapStats
{
    public function __construct(
        public int                $lapNumber,
        public \DateTimeImmutable $startTime,
        public \DateTimeImmutable $endTime,
        public float              $totalDistance,
        public int                $totalElapsedTime,
        public int                $movingTime,
        public ?int               $avgHeartRate,
        public ?int               $maxHeartRate,
        public ?float             $avgSpeed,
        public ?float             $maxSpeed,
        public ?float             $avgPower,
        public ?float             $maxPower,
        public ?int               $avgCadence,
        public ?float             $totalAscent,
        public ?float             $totalDescent,
        /** seconds per km, null when distance is zero */
        public ?float             $pace,
    ) {}

    public static function fromLap(Lap $lap): self
    {
        $pace = $lap->totalDistance > 0.0
            ? $lap->totalTimerTime / ($lap->totalDistance / 1000.0)
            : null;

        return new self(
            lapNumber: $lap->lapNumber,
            startTime: $lap->startTime,
            endTime: $lap->endTime,
            totalDistance: $lap->totalDistance,
            totalElapsedTime: $lap->totalElapsedTime,
            movingTime: $lap->totalTimerTime,
            avgHeartRate: $lap->avgHeartRate,
            maxHeartRate: $lap->maxHeartRate,
            avgSpeed: $lap->avgSpeed,
            maxSpeed: $lap->maxSpeed,
            avgPower: $lap->avgPower,
            maxPower: $lap->maxPower,
            avgCadence: $lap->avgCadence,
            totalAscent: $lap->totalAscent,
            totalDescent: $lap->totalDescent,
            pace: $pace,
        );
    }
}
