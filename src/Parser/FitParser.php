<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Parser;

use Beljic\FitSdk\Data\Activity;
use Beljic\FitSdk\Exception\InvalidFitFileException;
use Beljic\FitSdk\Source\FileSource;
use Beljic\FitSdk\Source\SourceInterface;
use Beljic\FitSdk\Source\StringSource;

final class FitParser
{
    private const string FIT_MAGIC = '.FIT';
    private const int    HEADER_SIZE_MIN = 12;

    public function parseFile(string $path): Activity
    {
        return $this->parse(new FileSource($path));
    }

    public function parseString(string $data): Activity
    {
        return $this->parse(new StringSource($data));
    }

    public function parse(SourceInterface $source): Activity
    {
        $data   = $source->read();
        $reader = new BinaryReader($data);

        $dataEndPosition = $this->validateHeader($reader);

        return (new ActivityBuilder($reader, $dataEndPosition))->build();
    }

    private function validateHeader(BinaryReader $reader): int
    {
        $headerSize = $reader->readUint8();

        if ($headerSize < self::HEADER_SIZE_MIN) {
            throw new InvalidFitFileException("Invalid FIT header size: {$headerSize}");
        }

        $reader->skip(1); // protocol version
        $reader->skip(2); // profile version
        $dataSize = $reader->readUint32(); // data size — stop here, do not go past this

        $magic = $reader->readString(4);

        if ($magic !== self::FIT_MAGIC) {
            throw new InvalidFitFileException("Not a FIT file — expected '.FIT', got '{$magic}'");
        }

        // skip optional header CRC (2 bytes present when headerSize == 14)
        if ($headerSize === 14) {
            $reader->skip(2);
        }

        // data ends at: current reader position + dataSize
        return $reader->position() + $dataSize;
    }
}
