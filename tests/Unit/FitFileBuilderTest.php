<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Tests\Fixtures\FitFileBuilder;
use PHPUnit\Framework\TestCase;

final class FitFileBuilderTest extends TestCase
{
    public function testBuildProducesValidFitMagic(): void
    {
        $binary = (new FitFileBuilder())->build();
        self::assertSame('.FIT', substr($binary, 8, 4));
    }

    public function testBuildHeaderSize14(): void
    {
        $binary = (new FitFileBuilder())->build();
        self::assertSame(14, ord($binary[0]));
    }

    public function testFitTimestampConversion(): void
    {
        $dt = new \DateTimeImmutable('2024-01-01 00:00:00', new \DateTimeZone('UTC'));
        $ts = FitFileBuilder::fitTs($dt);
        // Should be positive and reasonable (after FIT epoch 1989-12-31)
        self::assertGreaterThan(0, $ts);
        self::assertLessThan(0xFFFFFFFF, $ts);
    }

    public function testLatToSemicirclesRoundtrip(): void
    {
        $lat = 44.8125;
        $semicircles = FitFileBuilder::latToSemicircles($lat);
        $back = $semicircles * (180.0 / 2147483648.0);
        self::assertEqualsWithDelta($lat, $back, 0.0001);
    }

    public function testAltToRawRoundtrip(): void
    {
        $alt = 320.0;
        $raw = FitFileBuilder::altToRaw($alt);
        $back = $raw / 5 - 500;
        self::assertEqualsWithDelta($alt, $back, 0.1);
    }

    public function testDistToRawRoundtrip(): void
    {
        $dist = 1234.56;
        $raw  = FitFileBuilder::distToRaw($dist);
        $back = $raw / 100;
        self::assertEqualsWithDelta($dist, $back, 0.01);
    }
}
