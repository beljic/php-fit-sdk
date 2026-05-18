<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Protocol\MessageType;
use PHPUnit\Framework\TestCase;

final class MessageTypeTest extends TestCase
{
    public function testFromGlobalNumberKnownTypes(): void
    {
        self::assertSame(MessageType::FileId,     MessageType::fromGlobalNumber(0));
        self::assertSame(MessageType::Session,    MessageType::fromGlobalNumber(18));
        self::assertSame(MessageType::Lap,        MessageType::fromGlobalNumber(19));
        self::assertSame(MessageType::Record,     MessageType::fromGlobalNumber(20));
        self::assertSame(MessageType::Event,      MessageType::fromGlobalNumber(21));
        self::assertSame(MessageType::DeviceInfo, MessageType::fromGlobalNumber(23));
        self::assertSame(MessageType::FieldDescription,  MessageType::fromGlobalNumber(206));
        self::assertSame(MessageType::DeveloperDataId,   MessageType::fromGlobalNumber(207));
    }

    public function testFromGlobalNumberUnknownReturnsUnknown(): void
    {
        self::assertSame(MessageType::Unknown, MessageType::fromGlobalNumber(999));
        self::assertSame(MessageType::Unknown, MessageType::fromGlobalNumber(100));
    }
}
