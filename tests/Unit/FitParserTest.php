<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Exception\FitFileNotFoundException;
use Beljic\FitSdk\Exception\InvalidFitFileException;
use Beljic\FitSdk\Parser\FitParser;
use Beljic\FitSdk\Profile\Manufacturer;
use Beljic\FitSdk\Profile\Sport;
use Beljic\FitSdk\Source\StringSource;
use Beljic\FitSdk\Tests\Fixtures\FitFileBuilder;
use PHPUnit\Framework\TestCase;

final class FitParserTest extends TestCase
{
    // BaseType constants used in field definitions
    private const int UINT8   = 0x02;
    private const int UINT16  = 0x84;
    private const int UINT32  = 0x86;
    private const int SINT32  = 0x85;

    private FitParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FitParser();
    }

    public function testThrowsOnInvalidMagicBytes(): void
    {
        $this->expectException(InvalidFitFileException::class);
        $bad = pack('C', 14) . pack('C', 0x20) . pack('v', 2100) . pack('V', 0) . 'NOPE' . pack('v', 0);
        $this->parser->parse(new StringSource($bad));
    }

    public function testThrowsOnHeaderTooSmall(): void
    {
        $this->expectException(InvalidFitFileException::class);
        $bad = pack('C', 5); // headerSize=5 < 12
        $this->parser->parse(new StringSource($bad . str_repeat("\x00", 50)));
    }

    public function testThrowsOnMissingFile(): void
    {
        $this->expectException(FitFileNotFoundException::class);
        $this->parser->parseFile('/nonexistent/path/activity.fit');
    }

    public function testParseEmptyBodyReturnsActivity(): void
    {
        $binary   = (new FitFileBuilder())->build();
        $activity = $this->parser->parse(new StringSource($binary));

        self::assertCount(0, $activity->sessions);
        self::assertNull($activity->device);
    }

    public function testParsesFileIdCreationTime(): void
    {
        $expectedTime = new \DateTimeImmutable('2024-06-15 08:00:00', new \DateTimeZone('UTC'));

        $binary = (new FitFileBuilder())
            ->definition(0, 0, [  // localType=0, global=0 (file_id)
                ['num' => 4, 'size' => 4, 'baseType' => self::UINT32], // time_created
            ])
            ->data(0, [
                4 => FitFileBuilder::fitTs($expectedTime),
            ])
            ->build();

        $activity = $this->parser->parse(new StringSource($binary));

        self::assertSame(
            $expectedTime->getTimestamp(),
            $activity->createdAt->getTimestamp()
        );
    }

    public function testParsesSessionSport(): void
    {
        $startTime = new \DateTimeImmutable('2024-06-15 08:00:00', new \DateTimeZone('UTC'));

        $binary = (new FitFileBuilder())
            ->definition(0, 18, [  // global=18 (session)
                ['num' => 253, 'size' => 4, 'baseType' => self::UINT32], // timestamp
                ['num' => 2,   'size' => 4, 'baseType' => self::UINT32], // start_time
                ['num' => 5,   'size' => 1, 'baseType' => self::UINT8],  // sport
                ['num' => 7,   'size' => 4, 'baseType' => self::UINT32], // total_elapsed_time (ms)
                ['num' => 8,   'size' => 4, 'baseType' => self::UINT32], // total_timer_time (ms)
                ['num' => 9,   'size' => 4, 'baseType' => self::UINT32], // total_distance (cm)
            ])
            ->data(0, [
                253 => FitFileBuilder::fitTs($startTime),
                2   => FitFileBuilder::fitTs($startTime),
                5   => 1,  // Sport::Running
                7   => 3_600_000,  // 1 hour in ms
                8   => 3_600_000,
                9   => FitFileBuilder::distToRaw(10000.0),  // 10 km
            ])
            ->build();

        $activity = $this->parser->parse(new StringSource($binary));

        self::assertCount(1, $activity->sessions);
        $session = $activity->sessions[0];
        self::assertSame(Sport::Running, $session->sport);
        self::assertSame(3600, $session->totalElapsedTime);
        self::assertEqualsWithDelta(10000.0, $session->totalDistance, 1.0);
    }

    public function testParsesRecordCoordinates(): void
    {
        $ts  = new \DateTimeImmutable('2024-06-15 08:00:00', new \DateTimeZone('UTC'));
        $lat = 44.8125;
        $lon = 20.4612;

        $binary = (new FitFileBuilder())
            ->definition(0, 20, [  // global=20 (record)
                ['num' => 253, 'size' => 4, 'baseType' => self::UINT32],
                ['num' => 0,   'size' => 4, 'baseType' => self::SINT32], // lat
                ['num' => 1,   'size' => 4, 'baseType' => self::SINT32], // lon
                ['num' => 3,   'size' => 1, 'baseType' => self::UINT8],  // heart_rate
            ])
            ->data(0, [
                253 => FitFileBuilder::fitTs($ts),
                0   => FitFileBuilder::latToSemicircles($lat),
                1   => FitFileBuilder::latToSemicircles($lon),
                3   => 158,
            ])
            ->definition(1, 18, [  // session to flush records
                ['num' => 253, 'size' => 4, 'baseType' => self::UINT32],
                ['num' => 2,   'size' => 4, 'baseType' => self::UINT32],
                ['num' => 5,   'size' => 1, 'baseType' => self::UINT8],
                ['num' => 7,   'size' => 4, 'baseType' => self::UINT32],
                ['num' => 8,   'size' => 4, 'baseType' => self::UINT32],
                ['num' => 9,   'size' => 4, 'baseType' => self::UINT32],
            ])
            ->data(1, [
                253 => FitFileBuilder::fitTs($ts),
                2   => FitFileBuilder::fitTs($ts),
                5   => 1,
                7   => 0, 8 => 0, 9 => 0,
            ])
            ->build();

        $activity = $this->parser->parse(new StringSource($binary));

        self::assertCount(1, $activity->sessions[0]->records);
        $record = $activity->sessions[0]->records[0];

        self::assertEqualsWithDelta($lat, $record->lat, 0.0001);
        self::assertEqualsWithDelta($lon, $record->lon, 0.0001);
        self::assertSame(158, $record->heartRate);
    }

    public function testParsesLapData(): void
    {
        $start = new \DateTimeImmutable('2024-06-15 08:00:00', new \DateTimeZone('UTC'));
        $end   = new \DateTimeImmutable('2024-06-15 08:05:00', new \DateTimeZone('UTC'));

        $binary = (new FitFileBuilder())
            ->definition(0, 19, [  // global=19 (lap)
                ['num' => 253, 'size' => 4, 'baseType' => self::UINT32],
                ['num' => 2,   'size' => 4, 'baseType' => self::UINT32], // start_time
                ['num' => 7,   'size' => 4, 'baseType' => self::UINT32], // total_elapsed_time ms
                ['num' => 8,   'size' => 4, 'baseType' => self::UINT32], // total_timer_time ms
                ['num' => 9,   'size' => 4, 'baseType' => self::UINT32], // total_distance cm
                ['num' => 15,  'size' => 1, 'baseType' => self::UINT8],  // avg_heart_rate
            ])
            ->data(0, [
                253 => FitFileBuilder::fitTs($end),
                2   => FitFileBuilder::fitTs($start),
                7   => 300_000,  // 5 min in ms
                8   => 300_000,
                9   => FitFileBuilder::distToRaw(1000.0),
                15  => 165,
            ])
            ->definition(1, 18, [
                ['num' => 253, 'size' => 4, 'baseType' => self::UINT32],
                ['num' => 2,   'size' => 4, 'baseType' => self::UINT32],
                ['num' => 5,   'size' => 1, 'baseType' => self::UINT8],
                ['num' => 7,   'size' => 4, 'baseType' => self::UINT32],
                ['num' => 8,   'size' => 4, 'baseType' => self::UINT32],
                ['num' => 9,   'size' => 4, 'baseType' => self::UINT32],
            ])
            ->data(1, [
                253 => FitFileBuilder::fitTs($end),
                2   => FitFileBuilder::fitTs($start),
                5   => 1, 7 => 300_000, 8 => 300_000,
                9   => FitFileBuilder::distToRaw(1000.0),
            ])
            ->build();

        $activity = $this->parser->parse(new StringSource($binary));

        self::assertCount(1, $activity->sessions[0]->laps);
        $lap = $activity->sessions[0]->laps[0];

        self::assertSame(300, $lap->totalElapsedTime);
        self::assertEqualsWithDelta(1000.0, $lap->totalDistance, 1.0);
        self::assertSame(165, $lap->avgHeartRate);
        self::assertSame(0, $lap->lapNumber);
    }

    public function testInvalidSentinelValuesBecomeNull(): void
    {
        $ts = new \DateTimeImmutable('2024-06-15 08:00:00', new \DateTimeZone('UTC'));

        $binary = (new FitFileBuilder())
            ->definition(0, 20, [  // global=20 (record)
                ['num' => 253, 'size' => 4, 'baseType' => self::UINT32],
                ['num' => 0,   'size' => 4, 'baseType' => self::SINT32], // lat
                ['num' => 1,   'size' => 4, 'baseType' => self::SINT32], // lon
                ['num' => 2,   'size' => 2, 'baseType' => self::UINT16], // altitude
                ['num' => 3,   'size' => 1, 'baseType' => self::UINT8],  // heart_rate
                ['num' => 5,   'size' => 4, 'baseType' => self::UINT32], // distance
                ['num' => 6,   'size' => 2, 'baseType' => self::UINT16], // speed
                ['num' => 7,   'size' => 2, 'baseType' => self::UINT16], // power
            ])
            ->data(0, [
                253 => FitFileBuilder::fitTs($ts),
                0   => 0x7FFFFFFF, // sint32 invalid
                1   => 0x7FFFFFFF,
                2   => 0xFFFF,     // uint16 invalid
                3   => 0xFF,       // uint8 invalid
                5   => 0xFFFFFFFF, // uint32 invalid
                6   => 0xFFFF,
                7   => 0xFFFF,
            ])
            ->definition(1, 18, [
                ['num' => 253, 'size' => 4, 'baseType' => self::UINT32],
                ['num' => 2,   'size' => 4, 'baseType' => self::UINT32],
                ['num' => 5,   'size' => 1, 'baseType' => self::UINT8],
            ])
            ->data(1, [253 => FitFileBuilder::fitTs($ts), 2 => FitFileBuilder::fitTs($ts), 5 => 1])
            ->build();

        $activity = $this->parser->parse(new StringSource($binary));

        self::assertCount(1, $activity->sessions[0]->records);
        $record = $activity->sessions[0]->records[0];

        self::assertNull($record->lat);
        self::assertNull($record->lon);
        self::assertNull($record->altitude);
        self::assertNull($record->heartRate);
        self::assertNull($record->distance);
        self::assertNull($record->speed);
        self::assertNull($record->power);
    }

    public function testRecordWithInvalidTimestampIsSkipped(): void
    {
        $ts = new \DateTimeImmutable('2024-06-15 08:00:00', new \DateTimeZone('UTC'));

        $binary = (new FitFileBuilder())
            ->definition(0, 20, [
                ['num' => 253, 'size' => 4, 'baseType' => self::UINT32],
                ['num' => 3,   'size' => 1, 'baseType' => self::UINT8],
            ])
            ->data(0, [253 => 0xFFFFFFFF, 3 => 150]) // invalid timestamp
            ->definition(1, 18, [
                ['num' => 253, 'size' => 4, 'baseType' => self::UINT32],
                ['num' => 2,   'size' => 4, 'baseType' => self::UINT32],
                ['num' => 5,   'size' => 1, 'baseType' => self::UINT8],
            ])
            ->data(1, [253 => FitFileBuilder::fitTs($ts), 2 => FitFileBuilder::fitTs($ts), 5 => 1])
            ->build();

        $activity = $this->parser->parse(new StringSource($binary));

        self::assertCount(0, $activity->sessions[0]->records);
    }

    public function testSkipsUnknownMessageTypes(): void
    {
        // Global message 999 — unknown, should be skipped without exception
        $binary = (new FitFileBuilder())
            ->definition(0, 999, [
                ['num' => 1, 'size' => 4, 'baseType' => self::UINT32],
            ])
            ->data(0, [1 => 0xDEADBEEF])
            ->build();

        $activity = $this->parser->parse(new StringSource($binary));

        self::assertCount(0, $activity->sessions);
    }

    public function testMultipleSessions(): void
    {
        $ts = new \DateTimeImmutable('2024-06-15 08:00:00', new \DateTimeZone('UTC'));

        $sessionDef = [
            ['num' => 253, 'size' => 4, 'baseType' => self::UINT32],
            ['num' => 2,   'size' => 4, 'baseType' => self::UINT32],
            ['num' => 5,   'size' => 1, 'baseType' => self::UINT8],
            ['num' => 7,   'size' => 4, 'baseType' => self::UINT32],
            ['num' => 8,   'size' => 4, 'baseType' => self::UINT32],
            ['num' => 9,   'size' => 4, 'baseType' => self::UINT32],
        ];

        $binary = (new FitFileBuilder())
            ->definition(0, 18, $sessionDef)
            ->data(0, [253 => FitFileBuilder::fitTs($ts), 2 => FitFileBuilder::fitTs($ts), 5 => 1, 7 => 0, 8 => 0, 9 => 0])
            ->data(0, [253 => FitFileBuilder::fitTs($ts), 2 => FitFileBuilder::fitTs($ts), 5 => 2, 7 => 0, 8 => 0, 9 => 0])
            ->build();

        $activity = $this->parser->parse(new StringSource($binary));

        self::assertCount(2, $activity->sessions);
        self::assertSame(Sport::Running, $activity->sessions[0]->sport);
        self::assertSame(Sport::Cycling, $activity->sessions[1]->sport);
    }
}
