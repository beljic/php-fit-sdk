<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Parser;

use Beljic\FitSdk\Protocol\BaseType;

final readonly class FieldDefinition
{
    public function __construct(
        public int      $fieldNumber,
        public int      $size,
        public BaseType $baseType,
        public ?int     $devDataIndex = null,
    ) {}
}
