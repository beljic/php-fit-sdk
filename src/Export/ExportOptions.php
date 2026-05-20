<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Export;

final readonly class ExportOptions
{
    public function __construct(
        public bool $routeOnly = false, // false = full export (HR, cadence); true = geometry only
    ) {}
}
