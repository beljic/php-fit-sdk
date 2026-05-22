<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Analysis\LapStats;
use Beljic\FitSdk\Analysis\RouteBounds;
use Beljic\FitSdk\Analysis\RoutePoint;
use Beljic\FitSdk\Analysis\SensorPresence;
use Beljic\FitSdk\Data\Lap;
use PHPUnit\Framework\TestCase;

final class ActivityAnalyzerTest extends TestCase
{
    public function testRouteBoundsCenterIsAverage(): void
    {
        $bounds = new RouteBounds(
            minLat: 44.0,
            maxLat: 46.0,
            minLon: 20.0,
            maxLon: 22.0,
        );

        $center = $bounds->center();

        self::assertEqualsWithDelta(45.0, $center['lat'], 0.0001);
        self::assertEqualsWithDelta(21.0, $center['lon'], 0.0001);
    }

    public function testRoutePointHoldsCoordinates(): void
    {
        $point = new RoutePoint(lat: 44.8125, lon: 20.4612, altitude: 117.0);

        self::assertEqualsWithDelta(44.8125, $point->lat, 0.00001);
        self::assertEqualsWithDelta(20.4612, $point->lon, 0.00001);
        self::assertEqualsWithDelta(117.0, $point->altitude, 0.01);
    }

    public function testRoutePointAltitudeIsNullable(): void
    {
        $point = new RoutePoint(lat: 44.8125, lon: 20.4612);

        self::assertNull($point->altitude);
    }

    public function testSensorPresenceFlagsAreReadable(): void
    {
        $sensors = new SensorPresence(
            hasGps: true,
            hasHeartRate: true,
            hasCadence: false,
            hasPower: false,
            hasTemperature: false,
            hasDeveloperFields: true,
        );

        self::assertTrue($sensors->hasGps);
        self::assertTrue($sensors->hasHeartRate);
        self::assertFalse($sensors->hasCadence);
        self::assertFalse($sensors->hasPower);
        self::assertFalse($sensors->hasTemperature);
        self::assertTrue($sensors->hasDeveloperFields);
    }

    public function testLapStatsFromLapComputesPace(): void
    {
        $lap = new Lap(
            startTime: new \DateTimeImmutable('2024-06-15T08:00:00+00:00'),
            endTime: new \DateTimeImmutable('2024-06-15T08:05:00+00:00'),
            totalDistance: 1000.0,
            totalElapsedTime: 300,
            totalTimerTime: 295,
            avgHeartRate: 155,
            maxHeartRate: 172,
            avgSpeed: 3.38,
            maxSpeed: 4.1,
            avgPower: null,
            maxPower: null,
            avgCadence: 88,
            totalAscent: 5.0,
            totalDescent: 3.0,
            lapNumber: 1,
        );

        $stats = LapStats::fromLap($lap);

        self::assertSame(1, $stats->lapNumber);
        self::assertSame(1000.0, $stats->totalDistance);
        self::assertSame(295, $stats->movingTime);
        self::assertSame(155, $stats->avgHeartRate);
        // pace = 295s / 1.0km = 295 s/km
        self::assertEqualsWithDelta(295.0, $stats->pace, 0.01);
    }

    public function testLapStatsPaceIsNullWhenDistanceIsZero(): void
    {
        $lap = new Lap(
            startTime: new \DateTimeImmutable('2024-06-15T08:00:00+00:00'),
            endTime: new \DateTimeImmutable('2024-06-15T08:00:10+00:00'),
            totalDistance: 0.0,
            totalElapsedTime: 10,
            totalTimerTime: 10,
            avgHeartRate: null,
            maxHeartRate: null,
            avgSpeed: null,
            maxSpeed: null,
            avgPower: null,
            maxPower: null,
            avgCadence: null,
            totalAscent: null,
            totalDescent: null,
            lapNumber: 1,
        );

        self::assertNull(LapStats::fromLap($lap)->pace);
    }
}
