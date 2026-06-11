<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Fixtures;

/**
 * Builds minimal valid FIT binary data for unit tests.
 * Follows Garmin FIT Protocol v2.0 — little-endian only.
 */
final class FitFileBuilder
{
    private const int FIT_EPOCH_OFFSET = 631065600;

    private string $body = '';

    /** @var array<int, list<array{num:int, size:int, baseType:int}>> localType → field defs */
    private array $definitions = [];

    /** @param list<array{num: int, size: int, baseType: int}> $fields */
    public function definition(int $localType, int $globalNum, array $fields): self
    {
        $this->definitions[$localType] = $fields;

        // header: bit6=1 (definition), bits0-3=localType
        $this->body .= pack('C', 0x40 | ($localType & 0x0F));
        $this->body .= pack('C', 0);               // reserved
        $this->body .= pack('C', 0);               // architecture: 0 = little-endian
        $this->body .= pack('v', $globalNum);      // global message number
        $this->body .= pack('C', count($fields));  // field count

        foreach ($fields as $f) {
            $this->body .= pack('CCC', $f['num'], $f['size'], $f['baseType']);
        }

        return $this;
    }

    /** @param array<int, int> $values field number → raw value */
    public function data(int $localType, array $values): self
    {
        // header: bit6=0 (data), bits0-3=localType
        $this->body .= pack('C', $localType & 0x0F);

        foreach ($this->definitions[$localType] as $f) {
            $value = $values[$f['num']] ?? 0;
            $this->body .= match ($f['size']) {
                1 => pack('C', (int) $value & 0xFF),
                2 => pack('v', (int) $value & 0xFFFF),
                4 => pack('V', (int) $value & 0xFFFFFFFF),
                default => str_pad('', $f['size'], "\x00"),
            };
        }

        return $this;
    }

    public function build(): string
    {
        $dataSize = strlen($this->body);

        $header = pack('C', 14)      // header size
            . pack('C', 0x20)        // protocol version
            . pack('v', 2100)        // profile version
            . pack('V', $dataSize)   // data size
            . '.FIT'                 // magic
            . pack('v', 0);         // header CRC (skip validation)

        return $header . $this->body;
    }

    // --- Conversion helpers ---

    public static function fitTs(\DateTimeImmutable $dt): int
    {
        return $dt->getTimestamp() - self::FIT_EPOCH_OFFSET;
    }

    public static function latToSemicircles(float $deg): int
    {
        return (int) round($deg * (2147483648.0 / 180.0));
    }

    public static function altToRaw(float $meters): int
    {
        return (int) round(($meters + 500) * 5);
    }

    public static function speedToRaw(float $ms): int
    {
        return (int) round($ms * 1000);
    }

    public static function distToRaw(float $meters): int
    {
        return (int) round($meters * 100);
    }
}