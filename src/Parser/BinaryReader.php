<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Parser;

use Beljic\FitSdk\Exception\InvalidFitFileException;

final class BinaryReader
{
    private int $position = 0;

    public function __construct(private readonly string $data) {}

    public function readUint8(): int
    {
        return unpack('C', $this->read(1))[1];
    }

    public function readUint16(bool $bigEndian = false): int
    {
        return unpack($bigEndian ? 'n' : 'v', $this->read(2))[1];
    }

    public function readUint32(bool $bigEndian = false): int
    {
        return unpack($bigEndian ? 'N' : 'V', $this->read(4))[1];
    }

    public function readInt8(): int
    {
        return unpack('c', $this->read(1))[1];
    }

    public function readInt16(bool $bigEndian = false): int
    {
        $v = $this->readUint16($bigEndian);
        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    public function readInt32(bool $bigEndian = false): int
    {
        $v = $this->readUint32($bigEndian);
        return $v >= 0x80000000 ? $v - 0x100000000 : $v;
    }

    public function readFloat32(bool $bigEndian = false): float
    {
        return unpack($bigEndian ? 'G' : 'g', $this->read(4))[1];
    }

    public function readBytes(int $length): string
    {
        return $this->read($length);
    }

    public function readString(int $length): string
    {
        return rtrim($this->read($length), "\0");
    }

    public function skip(int $bytes): void
    {
        $this->read($bytes);
    }

    public function position(): int
    {
        return $this->position;
    }

    public function remaining(): int
    {
        return strlen($this->data) - $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->data);
    }

    private function read(int $length): string
    {
        if ($this->position + $length > strlen($this->data)) {
            throw new InvalidFitFileException(
                "Unexpected end of FIT data at position {$this->position}"
            );
        }

        $bytes = substr($this->data, $this->position, $length);
        $this->position += $length;

        return $bytes;
    }
}
