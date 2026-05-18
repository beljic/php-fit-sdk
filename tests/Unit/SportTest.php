<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Profile\Sport;
use PHPUnit\Framework\TestCase;

final class SportTest extends TestCase
{
    public function testFromFitValueKnownSport(): void
    {
        self::assertSame(Sport::Running, Sport::fromFitValue(1));
        self::assertSame(Sport::Cycling, Sport::fromFitValue(2));
        self::assertSame(Sport::Swimming, Sport::fromFitValue(5));
        self::assertSame(Sport::Hiking, Sport::fromFitValue(17));
    }

    public function testFromFitValueUnknownReturnsUnknown(): void
    {
        self::assertSame(Sport::Unknown, Sport::fromFitValue(999));
        self::assertSame(Sport::Unknown, Sport::fromFitValue(255));
    }

    public function testIsPaceBased(): void
    {
        self::assertTrue(Sport::Running->isPaceBased());
        self::assertTrue(Sport::Walking->isPaceBased());
        self::assertTrue(Sport::Hiking->isPaceBased());

        self::assertFalse(Sport::Cycling->isPaceBased());
        self::assertFalse(Sport::Swimming->isPaceBased());
        self::assertFalse(Sport::Unknown->isPaceBased());
    }

    public function testIsSpeedBased(): void
    {
        self::assertTrue(Sport::Cycling->isSpeedBased());
        self::assertTrue(Sport::Swimming->isSpeedBased());
        self::assertTrue(Sport::Rowing->isSpeedBased());

        self::assertFalse(Sport::Running->isSpeedBased());
        self::assertFalse(Sport::Hiking->isSpeedBased());
        self::assertFalse(Sport::Unknown->isSpeedBased());
    }

    public function testPaceAndSpeedAreMutuallyExclusive(): void
    {
        foreach (Sport::cases() as $sport) {
            self::assertFalse(
                $sport->isPaceBased() && $sport->isSpeedBased(),
                "{$sport->name} cannot be both pace-based and speed-based"
            );
        }
    }
}
