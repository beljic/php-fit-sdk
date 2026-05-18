<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Parser;

use Beljic\FitSdk\Protocol\BaseType;
use Beljic\FitSdk\Protocol\MessageType;

final readonly class DefinitionMessage
{
    /**
     * @param FieldDefinition[] $fields
     * @param FieldDefinition[] $developerFields
     */
    public function __construct(
        public MessageType $messageType,
        public bool        $bigEndian,
        public array       $fields,
        public array       $developerFields = [],
    ) {}
}
