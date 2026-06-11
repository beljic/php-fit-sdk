<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Protocol;

enum BaseType: int
{
    case Enum    = 0x00;
    case Sint8   = 0x01;
    case Uint8   = 0x02;
    case Sint16  = 0x83;
    case Uint16  = 0x84;
    case Sint32  = 0x85;
    case Uint32  = 0x86;
    case String  = 0x07;
    case Float32 = 0x88;
    case Float64 = 0x89;
    case Uint8z  = 0x0A;
    case Uint16z = 0x8B;
    case Uint32z = 0x8C;
    case Byte    = 0x0D;
    case Sint64  = 0x8E;
    case Uint64  = 0x8F;
    case Uint64z = 0x90;

    public function size(): int
    {
        return match ($this) {
            self::Sint8, self::Uint8, self::Uint8z, self::Enum, self::Byte => 1,
            self::Sint16, self::Uint16, self::Uint16z => 2,
            self::Sint32, self::Uint32, self::Uint32z, self::Float32 => 4,
            self::Float64, self::Sint64, self::Uint64, self::Uint64z => 8,
            self::String => 1,
        };
    }

    public function invalidValue(): int
    {
        return match ($this) {
            self::Uint8, self::Enum, self::Byte => 0xFF,
            self::Sint8  => 0x7F,
            self::Uint16 => 0xFFFF,
            self::Sint16 => 0x7FFF,
            self::Uint32 => 0xFFFFFFFF,
            self::Sint32 => 0x7FFFFFFF,
            self::Float32 => 0xFFFFFFFF,
            self::Float64, self::Uint64 => PHP_INT_MAX,
            self::Sint64 => PHP_INT_MAX,
            // "z" types use 0 as the invalid value (FIT spec table 4-6)
            self::Uint8z, self::Uint16z, self::Uint32z, self::Uint64z => 0x00,
            self::String => 0x00,
        };
    }
}
