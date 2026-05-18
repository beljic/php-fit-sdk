<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Protocol\BaseType;
use PHPUnit\Framework\TestCase;

final class BaseTypeTest extends TestCase
{
    public function testSize(): void
    {
        self::assertSame(1, BaseType::Uint8->size());
        self::assertSame(1, BaseType::Sint8->size());
        self::assertSame(1, BaseType::Enum->size());
        self::assertSame(2, BaseType::Uint16->size());
        self::assertSame(2, BaseType::Sint16->size());
        self::assertSame(4, BaseType::Uint32->size());
        self::assertSame(4, BaseType::Sint32->size());
        self::assertSame(4, BaseType::Float32->size());
        self::assertSame(8, BaseType::Float64->size());
        self::assertSame(8, BaseType::Sint64->size());
        self::assertSame(8, BaseType::Uint64->size());
    }

    public function testInvalidValues(): void
    {
        self::assertSame(0xFF, BaseType::Uint8->invalidValue());
        self::assertSame(0x7F, BaseType::Sint8->invalidValue());
        self::assertSame(0xFFFF, BaseType::Uint16->invalidValue());
        self::assertSame(0x7FFF, BaseType::Sint16->invalidValue());
        self::assertSame(0xFFFFFFFF, BaseType::Uint32->invalidValue());
        self::assertSame(0x7FFFFFFF, BaseType::Sint32->invalidValue());
    }

    public function testTryFromValidValue(): void
    {
        self::assertSame(BaseType::Uint8, BaseType::tryFrom(0x02));
        self::assertSame(BaseType::Sint32, BaseType::tryFrom(0x85));
        self::assertSame(BaseType::Float32, BaseType::tryFrom(0x88));
    }

    public function testTryFromUnknownReturnsNull(): void
    {
        self::assertNull(BaseType::tryFrom(0xFF));
    }
}
