<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Source;

use Beljic\FitSdk\Exception\FitFileNotFoundException;

final class FileSource implements SourceInterface
{
    public function __construct(private readonly string $path) {}

    #[\Override]
    public function read(): string
    {
        if (!is_file($this->path)) {
            throw new FitFileNotFoundException("FIT file not found: {$this->path}");
        }

        return (string) file_get_contents($this->path);
    }
}
