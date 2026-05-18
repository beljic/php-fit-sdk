<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Protocol;

enum MessageType: int
{
    case FileId        = 0;
    case DeviceInfo    = 23;
    case Session       = 18;
    case Lap           = 19;
    case Record        = 20;
    case Event         = 21;
    case DeveloperDataId  = 207;
    case FieldDescription = 206;
    case Unknown       = -1;

    public static function fromGlobalNumber(int $number): self
    {
        return self::tryFrom($number) ?? self::Unknown;
    }
}
