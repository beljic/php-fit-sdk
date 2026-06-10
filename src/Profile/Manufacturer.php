<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Profile;

/**
 * Manufacturer ids from the official FIT profile (Profile.xlsx, "manufacturer" type).
 */
enum Manufacturer: int
{
    case Garmin           = 1;
    case GarminFr405Antfs = 2;
    case Zephyr           = 3;
    case Dayton           = 4;
    case Idt              = 5;
    case Srm              = 6;
    case Quarq            = 7;
    case Ibike            = 8;
    case Saris            = 9;
    case SparkHk          = 10;
    case Suunto           = 23;
    case Wahoo            = 32;
    case Polar            = 123;
    case Development      = 255;
    case Coros            = 294;
    case Decathlon        = 310;
    case Unknown          = 0;

    public static function fromFitValue(int $value): self
    {
        return self::tryFrom($value) ?? self::Unknown;
    }
}
