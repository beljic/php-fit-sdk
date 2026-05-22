<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Analysis\ActivityAnalyzer;
use Beljic\FitSdk\Analysis\ActivityStats;
use Beljic\FitSdk\Analysis\LapStats;
use Beljic\FitSdk\Analysis\RouteBounds;
use Beljic\FitSdk\Analysis\RoutePoint;
use Beljic\FitSdk\Analysis\SensorPresence;
use Beljic\FitSdk\Data\Activity;
use Beljic\FitSdk\Data\Lap;
use Beljic\FitSdk\Data\Record;
use Beljic\FitSdk\Data\Session;
use Beljic\FitSdk\Profile\Sport;
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

    public function testActivityStatsIsConstructibleAndReadable(): void
    {
        $stats = new ActivityStats(
            sport: Sport::Running,
            startTime: new \DateTimeImmutable('2024-06-15T08:00:00+00:00'),
            endTime: new \DateTimeImmutable('2024-06-15T09:00:00+00:00'),
            sessionCount: 1,
            totalDistance: 10000.0,
            duration: 3600,
            movingTime: 3540,
            pace: 354.0,
            avgHeartRate: 158,
            maxHeartRate: 178,
            avgSpeed: 2.82,
            maxSpeed: 4.2,
            avgPower: null,
            maxPower: null,
            avgCadence: 89,
            totalAscent: 120.0,
            totalDescent: 118.0,
            bounds: null,
            laps: [],
            sensors: new SensorPresence(
                hasGps: true,
                hasHeartRate: true,
                hasCadence: true,
                hasPower: false,
                hasTemperature: false,
                hasDeveloperFields: false,
            ),
        );

        self::assertSame(Sport::Running, $stats->sport);
        self::assertSame(10000.0, $stats->totalDistance);
        self::assertSame(3540, $stats->movingTime);
        self::assertEqualsWithDelta(354.0, $stats->pace, 0.01);
        self::assertTrue($stats->sensors->hasGps);
    }

    public function testAnalyzeReturnsSingleSessionStats(): void
    {
        $session = $this->makeSession(
            distance: 5000.0,
            elapsed: 1500,
            moving: 1480,
            avgHr: 162,
            maxHr: 181,
        );
        $activity = new Activity(
            createdAt: new \DateTimeImmutable('2024-06-15T08:00:00+00:00'),
            device: null,
            sessions: [$session],
        );

        $stats = (new ActivityAnalyzer())->analyze($activity);

        self::assertSame(Sport::Running, $stats->sport);
        self::assertSame(1, $stats->sessionCount);
        self::assertSame(5000.0, $stats->totalDistance);
        self::assertSame(1500, $stats->duration);
        self::assertSame(1480, $stats->movingTime);
        self::assertSame(162, $stats->avgHeartRate);
        self::assertSame(181, $stats->maxHeartRate);
        // pace = 1480 / 5.0km = 296 s/km
        self::assertEqualsWithDelta(296.0, $stats->pace, 0.01);
    }

    public function testAnalyzeAggregatesMultipleSessions(): void
    {
        $s1 = $this->makeSession(distance: 3000.0, elapsed: 900, moving: 890);
        $s2 = $this->makeSession(distance: 2000.0, elapsed: 600, moving: 595);
        $activity = new Activity(
            createdAt: new \DateTimeImmutable('2024-06-15T08:00:00+00:00'),
            device: null,
            sessions: [$s1, $s2],
        );

        $stats = (new ActivityAnalyzer())->analyze($activity);

        self::assertSame(2, $stats->sessionCount);
        self::assertSame(5000.0, $stats->totalDistance);
        self::assertSame(1500, $stats->duration);
        self::assertSame(1485, $stats->movingTime);
    }

    public function testAnalyzeComputesBoundsFromRecords(): void
    {
        $records = [
            $this->makeRecord(lat: 44.80, lon: 20.40),
            $this->makeRecord(lat: 44.82, lon: 20.42),
            $this->makeRecord(lat: 44.81, lon: 20.41),
        ];
        $session  = $this->makeSession(records: $records);
        $activity = new Activity(
            createdAt: new \DateTimeImmutable('2024-06-15T08:00:00+00:00'),
            device: null,
            sessions: [$session],
        );

        $bounds = (new ActivityAnalyzer())->analyze($activity)->bounds;

        self::assertNotNull($bounds);
        self::assertEqualsWithDelta(44.80, $bounds->minLat, 0.0001);
        self::assertEqualsWithDelta(44.82, $bounds->maxLat, 0.0001);
        self::assertEqualsWithDelta(20.40, $bounds->minLon, 0.0001);
        self::assertEqualsWithDelta(20.42, $bounds->maxLon, 0.0001);
    }

    public function testAnalyzeBoundsAreNullWhenNoGps(): void
    {
        $session  = $this->makeSession(records: [$this->makeRecord(lat: null, lon: null)]);
        $activity = new Activity(
            createdAt: new \DateTimeImmutable(),
            device: null,
            sessions: [$session],
        );

        self::assertNull((new ActivityAnalyzer())->analyze($activity)->bounds);
    }

    public function testAnalyzeDetectsSensorPresence(): void
    {
        $records = [
            $this->makeRecord(hr: 155, cadence: 88, power: null),
            $this->makeRecord(hr: 158, cadence: 90, power: null),
        ];
        $session  = $this->makeSession(records: $records);
        $activity = new Activity(
            createdAt: new \DateTimeImmutable(),
            device: null,
            sessions: [$session],
        );

        $sensors = (new ActivityAnalyzer())->analyze($activity)->sensors;

        self::assertTrue($sensors->hasHeartRate);
        self::assertTrue($sensors->hasCadence);
        self::assertFalse($sensors->hasPower);
    }

    public function testAnalyzeMapsLapsToLapStats(): void
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
        $session  = $this->makeSession(laps: [$lap]);
        $activity = new Activity(
            createdAt: new \DateTimeImmutable(),
            device: null,
            sessions: [$session],
        );

        $stats = (new ActivityAnalyzer())->analyze($activity);

        self::assertCount(1, $stats->laps);
        self::assertSame(1, $stats->laps[0]->lapNumber);
        self::assertSame(1000.0, $stats->laps[0]->totalDistance);
    }

    // --- helpers ---

    private function makeSession(
        float $distance = 5000.0,
        int $elapsed = 1800,
        int $moving = 1780,
        ?int $avgHr = null,
        ?int $maxHr = null,
        array $records = [],
        array $laps = [],
    ): Session {
        return new Session(
            sport: Sport::Running,
            startTime: new \DateTimeImmutable('2024-06-15T08:00:00+00:00'),
            totalElapsedTime: $elapsed,
            totalTimerTime: $moving,
            totalDistance: $distance,
            totalAscent: null,
            totalDescent: null,
            avgHeartRate: $avgHr,
            maxHeartRate: $maxHr,
            avgSpeed: null,
            maxSpeed: null,
            avgPower: null,
            maxPower: null,
            avgCadence: null,
            records: $records,
            laps: $laps,
        );
    }

    private function makeRecord(
        ?float $lat = 44.81,
        ?float $lon = 20.46,
        ?int $hr = null,
        ?int $cadence = null,
        ?float $power = null,
    ): Record {
        return new Record(
            timestamp: new \DateTimeImmutable(),
            lat: $lat,
            lon: $lon,
            altitude: 117.0,
            heartRate: $hr,
            cadence: $cadence,
            speed: null,
            power: $power,
            distance: null,
            temperature: null,
        );
    }
}
