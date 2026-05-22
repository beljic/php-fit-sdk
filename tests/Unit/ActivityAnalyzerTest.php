<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Analysis\RouteBounds;
use Beljic\FitSdk\Analysis\RoutePoint;
use Beljic\FitSdk\Analysis\SensorPresence;
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
        self::assertTrue($sensors->hasDeveloperFields);
    }
}
