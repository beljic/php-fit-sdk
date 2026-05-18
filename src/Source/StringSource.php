<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Source;

final class StringSource implements SourceInterface
{
    public function __construct(private readonly string $data) {}

    #[\Override]
    public function read(): string
    {
        return $this->data;
    }
}
