<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Analysis;

use Beljic\FitSdk\Data\Activity;
use Beljic\FitSdk\Data\Session;
use Beljic\FitSdk\Exception\CannotAnalyzeActivityException;

final class ActivityAnalyzer
{
    public function analyze(Activity $activity): ActivityStats
    {
        $sessions = $activity->sessions;

        if ($sessions === []) {
            throw new CannotAnalyzeActivityException('Cannot analyze an Activity with no sessions.');
        }

        $totalDistance = 0.0;
        $totalDuration = 0;
        $totalMoving   = 0;
        $maxHr         = null;
        $maxSpeed      = null;
        $maxPower      = null;
        $totalAscent   = null;
        $totalDescent  = null;
        // Session averages are weighted by moving time so short sessions
        // don't skew multi-session (e.g. triathlon) aggregates.
        $hrSum         = 0.0;
        $hrWeight      = 0;
        $speedSum      = 0.0;
        $speedWeight   = 0;
        $powerSum      = 0.0;
        $powerWeight   = 0;
        $cadenceSum    = 0.0;
        $cadenceWeight = 0;
        $allLaps       = [];

        foreach ($sessions as $session) {
            $totalDistance += $session->totalDistance;
            $totalDuration += $session->totalElapsedTime;
            $totalMoving   += $session->totalTimerTime;

            $weight = max(1, $session->totalTimerTime);

            if ($session->maxHeartRate !== null) {
                $maxHr = $maxHr === null ? $session->maxHeartRate : max($maxHr, $session->maxHeartRate);
            }
            if ($session->avgHeartRate !== null) {
                $hrSum    += $session->avgHeartRate * $weight;
                $hrWeight += $weight;
            }
            if ($session->maxSpeed !== null) {
                $maxSpeed = $maxSpeed === null ? $session->maxSpeed : max($maxSpeed, $session->maxSpeed);
            }
            if ($session->avgSpeed !== null) {
                $speedSum    += $session->avgSpeed * $weight;
                $speedWeight += $weight;
            }
            if ($session->maxPower !== null) {
                $maxPower = $maxPower === null ? $session->maxPower : max($maxPower, $session->maxPower);
            }
            if ($session->avgPower !== null) {
                $powerSum    += $session->avgPower * $weight;
                $powerWeight += $weight;
            }
            if ($session->avgCadence !== null) {
                $cadenceSum    += $session->avgCadence * $weight;
                $cadenceWeight += $weight;
            }
            if ($session->totalAscent !== null) {
                $totalAscent = ($totalAscent ?? 0.0) + $session->totalAscent;
            }
            if ($session->totalDescent !== null) {
                $totalDescent = ($totalDescent ?? 0.0) + $session->totalDescent;
            }

            foreach ($session->laps as $lap) {
                $allLaps[] = LapStats::fromLap($lap);
            }
        }

        $firstSession = $sessions[0];
        $lastSession  = $sessions[count($sessions) - 1];

        $endTime = (new \DateTimeImmutable())
            ->setTimestamp(
                $lastSession->startTime->getTimestamp() + $lastSession->totalElapsedTime
            );

        return new ActivityStats(
            sport: $firstSession->sport,
            startTime: $firstSession->startTime,
            endTime: $endTime,
            sessionCount: count($sessions),
            totalDistance: $totalDistance,
            duration: $totalDuration,
            movingTime: $totalMoving,
            pace: $totalDistance > 0.0 ? $totalMoving / ($totalDistance / 1000.0) : null,
            avgHeartRate: $hrWeight > 0 ? (int) round($hrSum / $hrWeight) : null,
            maxHeartRate: $maxHr,
            avgSpeed: $speedWeight > 0 ? $speedSum / $speedWeight : null,
            maxSpeed: $maxSpeed,
            avgPower: $powerWeight > 0 ? $powerSum / $powerWeight : null,
            maxPower: $maxPower,
            avgCadence: $cadenceWeight > 0 ? (int) round($cadenceSum / $cadenceWeight) : null,
            totalAscent: $totalAscent,
            totalDescent: $totalDescent,
            bounds: $this->computeBounds($sessions),
            laps: $allLaps,
            sensors: $this->detectSensors($sessions),
        );
    }

    /** @param Session[] $sessions */
    private function computeBounds(array $sessions): ?RouteBounds
    {
        $minLat = PHP_FLOAT_MAX;
        $maxLat = -PHP_FLOAT_MAX;
        $minLon = PHP_FLOAT_MAX;
        $maxLon = -PHP_FLOAT_MAX;
        $found  = false;

        foreach ($sessions as $session) {
            foreach ($session->records as $record) {
                if ($record->lat === null || $record->lon === null) {
                    continue;
                }
                $found  = true;
                $minLat = min($minLat, $record->lat);
                $maxLat = max($maxLat, $record->lat);
                $minLon = min($minLon, $record->lon);
                $maxLon = max($maxLon, $record->lon);
            }
        }

        return $found ? new RouteBounds($minLat, $maxLat, $minLon, $maxLon) : null;
    }

    /** @param Session[] $sessions */
    private function detectSensors(array $sessions): SensorPresence
    {
        $hasGps = $hasHr = $hasCadence = $hasPower = $hasTemp = $hasDev = false;

        foreach ($sessions as $session) {
            foreach ($session->records as $record) {
                if ($record->lat !== null)           { $hasGps     = true; }
                if ($record->heartRate !== null)     { $hasHr      = true; }
                if ($record->cadence !== null)       { $hasCadence = true; }
                if ($record->power !== null)         { $hasPower   = true; }
                if ($record->temperature !== null)   { $hasTemp    = true; }
                if ($record->developerFields !== []) { $hasDev     = true; }
            }
        }

        return new SensorPresence($hasGps, $hasHr, $hasCadence, $hasPower, $hasTemp, $hasDev);
    }

    /**
     * Returns a downsampled route for web map rendering.
     * Keeps every Nth GPS point so total count ≤ $maxPoints.
     * Always includes first and last GPS point.
     *
     * @return RoutePoint[]
     */
    public function sampleRoute(Activity $activity, int $maxPoints = 500): array
    {
        if ($maxPoints < 2) {
            throw new \InvalidArgumentException('maxPoints must be at least 2.');
        }

        $points = [];

        foreach ($activity->sessions as $session) {
            foreach ($session->records as $record) {
                if ($record->lat !== null && $record->lon !== null) {
                    $points[] = new RoutePoint($record->lat, $record->lon, $record->altitude);
                }
            }
        }

        $total = count($points);

        if ($total <= $maxPoints) {
            return $points;
        }

        $step   = (int) ceil($total / ($maxPoints - 1));
        $result = [];

        for ($i = 0; $i < $total - 1; $i += $step) {
            $result[] = $points[$i];
        }

        $result[] = $points[$total - 1];

        return $result;
    }
}
