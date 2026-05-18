<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Profile;

enum Manufacturer: int
{
    case Garmin         = 1;
    case GarminFr405Antfs = 2;
    case Zephyr         = 3;
    case Dayton         = 4;
    case Idt            = 5;
    case Srm            = 6;
    case Quarq          = 7;
    case Ibike          = 8;
    case Saris          = 9;
    case SparkHlt       = 10;
    case Polar          = 32;
    case Wahoo          = 32896;
    case Decathlon      = 2069;
    case Development    = 255;
    case Unknown        = 0;

    public static function fromFitValue(int $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }
}
