<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Exception\InvalidFitFileException;
use Beljic\FitSdk\Parser\BinaryReader;
use PHPUnit\Framework\TestCase;

final class BinaryReaderTest extends TestCase
{
    public function testReadUint8(): void
    {
        $r = new BinaryReader(pack('C', 0xAB));
        self::assertSame(0xAB, $r->readUint8());
    }

    public function testReadUint16LittleEndian(): void
    {
        $r = new BinaryReader(pack('v', 0x1234));
        self::assertSame(0x1234, $r->readUint16());
    }

    public function testReadUint16BigEndian(): void
    {
        $r = new BinaryReader(pack('n', 0x1234));
        self::assertSame(0x1234, $r->readUint16(true));
    }

    public function testReadUint32LittleEndian(): void
    {
        $r = new BinaryReader(pack('V', 0xDEADBEEF));
        self::assertSame(0xDEADBEEF, $r->readUint32());
    }

    public function testReadUint32BigEndian(): void
    {
        $r = new BinaryReader(pack('N', 0xDEADBEEF));
        self::assertSame(0xDEADBEEF, $r->readUint32(true));
    }

    public function testReadInt8Positive(): void
    {
        $r = new BinaryReader(pack('c', 42));
        self::assertSame(42, $r->readInt8());
    }

    public function testReadInt8Negative(): void
    {
        $r = new BinaryReader(pack('c', -10));
        self::assertSame(-10, $r->readInt8());
    }

    public function testReadInt32Negative(): void
    {
        $r = new BinaryReader(pack('V', 0xFFFFFFFF));
        self::assertSame(-1, $r->readInt32());
    }

    public function testReadString(): void
    {
        $r = new BinaryReader("hello\x00\x00");
        self::assertSame('hello', $r->readString(7));
    }

    public function testReadBytes(): void
    {
        $r = new BinaryReader("\x01\x02\x03");
        self::assertSame("\x01\x02", $r->readBytes(2));
        self::assertSame("\x03", $r->readBytes(1));
    }

    public function testPositionAdvances(): void
    {
        $r = new BinaryReader(pack('CC', 0x01, 0x02));
        self::assertSame(0, $r->position());
        $r->readUint8();
        self::assertSame(1, $r->position());
        $r->readUint8();
        self::assertSame(2, $r->position());
    }

    public function testSkip(): void
    {
        $r = new BinaryReader(pack('CCC', 0x01, 0x02, 0x03));
        $r->skip(2);
        self::assertSame(0x03, $r->readUint8());
    }

    public function testRemaining(): void
    {
        $r = new BinaryReader(pack('CCC', 1, 2, 3));
        self::assertSame(3, $r->remaining());
        $r->readUint8();
        self::assertSame(2, $r->remaining());
    }

    public function testEof(): void
    {
        $r = new BinaryReader(pack('C', 1));
        self::assertFalse($r->eof());
        $r->readUint8();
        self::assertTrue($r->eof());
    }

    public function testThrowsOnReadPastEnd(): void
    {
        $this->expectException(InvalidFitFileException::class);
        $r = new BinaryReader(pack('C', 1));
        $r->readUint16();
    }
}
