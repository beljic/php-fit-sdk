<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Profile;

enum Sport: int
{
    case Generic        = 0;
    case Running        = 1;
    case Cycling        = 2;
    case Transition     = 3;
    case FitnessEquipment = 4;
    case Swimming       = 5;
    case Basketball     = 6;
    case Soccer         = 7;
    case Tennis         = 8;
    case AmericanFootball = 9;
    case Training       = 10;
    case Walking        = 11;
    case CrossCountrySkiing = 12;
    case AlpineSkiing   = 13;
    case Snowboarding   = 14;
    case Rowing         = 15;
    case Mountaineering = 16;
    case Hiking         = 17;
    case Multisport     = 18;
    case Paddling       = 19;
    case Unknown        = 255;

    public static function fromFitValue(int $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }

    public function isPaceBased(): bool
    {
        return match ($this) {
            self::Running, self::Walking, self::Hiking => true,
            default => false,
        };
    }

    public function isSpeedBased(): bool
    {
        return match ($this) {
            self::Cycling, self::Swimming, self::Rowing => true,
            default => false,
        };
    }
}
