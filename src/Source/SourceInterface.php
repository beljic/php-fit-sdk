<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Source;

interface SourceInterface
{
    public function read(): string;
}
