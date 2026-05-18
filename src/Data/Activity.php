<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Data;

final readonly class Activity
{
    /**
     * @param Session[] $sessions
     */
    public function __construct(
        public \DateTimeImmutable $createdAt,
        public ?DeviceInfo        $device,
        public array              $sessions = [],
    ) {}
}
