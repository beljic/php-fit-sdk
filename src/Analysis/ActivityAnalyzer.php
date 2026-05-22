<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Analysis;

use Beljic\FitSdk\Data\Activity;
use Beljic\FitSdk\Data\Session;

final class ActivityAnalyzer
{
    public function analyze(Activity $activity): ActivityStats
    {
        $sessions = $activity->sessions;

        $totalDistance = 0.0;
        $totalDuration = 0;
        $totalMoving   = 0;
        $maxHr         = null;
        $maxSpeed      = null;
        $maxPower      = null;
        $totalAscent   = null;
        $totalDescent  = null;
        $hrSum         = 0;
        $hrCount       = 0;
        $speedSum      = 0.0;
        $speedCount    = 0;
        $powerSum      = 0.0;
        $powerCount    = 0;
        $cadenceSum    = 0;
        $cadenceCount  = 0;
        $allLaps       = [];

        foreach ($sessions as $session) {
            $totalDistance += $session->totalDistance;
            $totalDuration += $session->totalElapsedTime;
            $totalMoving   += $session->totalTimerTime;

            if ($session->maxHeartRate !== null) {
                $maxHr = $maxHr === null ? $session->maxHeartRate : max($maxHr, $session->maxHeartRate);
            }
            if ($session->avgHeartRate !== null) {
                $hrSum   += $session->avgHeartRate;
                $hrCount++;
            }
            if ($session->maxSpeed !== null) {
                $maxSpeed = $maxSpeed === null ? $session->maxSpeed : max($maxSpeed, $session->maxSpeed);
            }
            if ($session->avgSpeed !== null) {
                $speedSum += $session->avgSpeed;
                $speedCount++;
            }
            if ($session->maxPower !== null) {
                $maxPower = $maxPower === null ? $session->maxPower : max($maxPower, $session->maxPower);
            }
            if ($session->avgPower !== null) {
                $powerSum += $session->avgPower;
                $powerCount++;
            }
            if ($session->avgCadence !== null) {
                $cadenceSum += $session->avgCadence;
                $cadenceCount++;
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
            avgHeartRate: $hrCount > 0 ? (int) round($hrSum / $hrCount) : null,
            maxHeartRate: $maxHr,
            avgSpeed: $speedCount > 0 ? $speedSum / $speedCount : null,
            maxSpeed: $maxSpeed,
            avgPower: $powerCount > 0 ? $powerSum / $powerCount : null,
            maxPower: $maxPower,
            avgCadence: $cadenceCount > 0 ? (int) round($cadenceSum / $cadenceCount) : null,
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
}
