<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Parser;

use Beljic\FitSdk\Data\Activity;
use Beljic\FitSdk\Data\DeviceInfo;
use Beljic\FitSdk\Data\Lap;
use Beljic\FitSdk\Data\Record;
use Beljic\FitSdk\Data\Session;
use Beljic\FitSdk\Profile\Manufacturer;
use Beljic\FitSdk\Profile\Sport;
use Beljic\FitSdk\Protocol\BaseType;
use Beljic\FitSdk\Protocol\MessageType;

/**
 * Reads all FIT records from BinaryReader and assembles an Activity value object.
 * Follows the FIT protocol: definition messages register field layouts,
 * data messages carry the actual values.
 */
final class ActivityBuilder
{
    private const float FIT_EPOCH_OFFSET = 631065600.0; // FIT timestamp epoch: 1989-12-31 00:00:00 UTC
    private const float SEMICIRCLE_TO_DEG = 180.0 / 2147483648.0;

    /** @var array<int, DefinitionMessage> local message type → definition */
    private array $definitions = [];

    /** @var array<int, array<int, array{name: string, base_type: BaseType}>> devDataIndex → fieldNum → meta */
    private array $developerFields = [];

    private ?\DateTimeImmutable $createdAt = null;
    private ?DeviceInfo $device = null;
    private bool $deviceIsCreator = false;
    private int $lastTimestamp = 0;

    /** @var Session[] */
    private array $sessions = [];

    /** @var Record[] accumulated until flushed into a Session */
    private array $pendingRecords = [];

    /** @var Lap[] accumulated until flushed into a Session */
    private array $pendingLaps = [];

    public function __construct(
        private readonly BinaryReader $reader,
        private readonly int $dataEndPosition,
    ) {}

    public function build(): Activity
    {
        while ($this->reader->position() < $this->dataEndPosition) {
            $this->processRecord();
        }

        $this->flushPendingIntoFallbackSession();

        return new Activity(
            createdAt: $this->createdAt ?? new \DateTimeImmutable(),
            device: $this->device,
            sessions: $this->sessions,
        );
    }

    private function processRecord(): void
    {
        if ($this->reader->remaining() < 1) {
            return;
        }

        $header = $this->reader->readUint8();

        if ($header & 0x80) {
            // Compressed timestamp header (FIT spec §4.2.4): bit 7 = 1
            $localType  = ($header >> 5) & 0x03;
            $timeOffset = $header & 0x1F;
            $this->readCompressedTimestampMessage($localType, $timeOffset);
            return;
        }

        $isDefinition = (bool) (($header >> 6) & 1);
        $hasDevData   = (bool) (($header >> 5) & 1);
        $localType    = $header & 0x0F;

        if ($isDefinition) {
            $this->readDefinitionMessage($localType, $hasDevData);
        } else {
            $this->readDataMessage($localType);
        }
    }

    private function readCompressedTimestampMessage(int $localType, int $timeOffset): void
    {
        $definition = $this->definitions[$localType] ?? null;

        if ($definition === null) {
            return;
        }

        // Reconstruct timestamp: keep upper 27 bits of lastTimestamp, append 5-bit offset
        // Handle rollover: if offset < lower 5 bits, increment the upper part by 32
        $base      = $this->lastTimestamp & 0xFFFFFFE0;
        $prevLow   = $this->lastTimestamp & 0x1F;
        $rollover  = $timeOffset < $prevLow ? 32 : 0;
        $timestamp = $base + $rollover + $timeOffset;

        $this->lastTimestamp = $timestamp;

        $values = [];
        foreach ($definition->fields as $field) {
            if ($field->fieldNumber === 253) {
                // Timestamp field is omitted in compressed header records; inject synthetic value
                $values[253] = $timestamp;
                continue;
            }
            $values[$field->fieldNumber] = $this->readValue($field, $definition->bigEndian);
        }

        foreach ($definition->developerFields as $field) {
            $values['dev_' . $field->devDataIndex . '_' . $field->fieldNumber] = $this->readValue($field, $definition->bigEndian);
        }

        match ($definition->messageType) {
            MessageType::Record  => $this->handleRecord($values),
            MessageType::Lap     => $this->handleLap($values),
            MessageType::Session => $this->handleSession($values),
            default              => null,
        };
    }

    private function readDefinitionMessage(int $localType, bool $hasDevData): void
    {
        $this->reader->skip(1); // reserved

        $bigEndian  = $this->reader->readUint8() === 1;
        $globalNum  = $this->reader->readUint16($bigEndian);
        $fieldCount = $this->reader->readUint8();

        $fields = [];
        for ($i = 0; $i < $fieldCount; $i++) {
            $fieldNum  = $this->reader->readUint8();
            $size      = $this->reader->readUint8();
            $baseTypeId = $this->reader->readUint8();
            $baseType  = BaseType::tryFrom($baseTypeId & 0x9F) ?? BaseType::Byte;
            $fields[]  = new FieldDefinition($fieldNum, $size, $baseType);
        }

        $devFields = [];
        if ($hasDevData) {
            $devFieldCount = $this->reader->readUint8();
            for ($i = 0; $i < $devFieldCount; $i++) {
                $fieldNum = $this->reader->readUint8();
                $size     = $this->reader->readUint8();
                $devIdx   = $this->reader->readUint8();
                $baseType = $this->developerFields[$devIdx][$fieldNum]['base_type'] ?? BaseType::Uint8;
                $devFields[] = new FieldDefinition($fieldNum, $size, $baseType, $devIdx);
            }
        }

        $this->definitions[$localType] = new DefinitionMessage(
            messageType: MessageType::fromGlobalNumber($globalNum),
            bigEndian: $bigEndian,
            fields: $fields,
            developerFields: $devFields,
        );
    }

    private function readDataMessage(int $localType): void
    {
        $definition = $this->definitions[$localType] ?? null;

        if ($definition === null) {
            return;
        }

        $values = $this->readFieldValues($definition);

        if (isset($values[253])) {
            $this->lastTimestamp = (int) $values[253];
        }

        match ($definition->messageType) {
            MessageType::FileId           => $this->handleFileId($values),
            MessageType::DeviceInfo       => $this->handleDeviceInfo($values),
            MessageType::Record           => $this->handleRecord($values),
            MessageType::Lap              => $this->handleLap($values),
            MessageType::Session          => $this->handleSession($values),
            MessageType::FieldDescription => $this->handleFieldDescription($values),
            default                       => null,
        };
    }

    /** @return array<int, mixed> field number → value */
    private function readFieldValues(DefinitionMessage $definition): array
    {
        $values = [];

        foreach ($definition->fields as $field) {
            $values[$field->fieldNumber] = $this->readValue($field, $definition->bigEndian);
        }

        foreach ($definition->developerFields as $field) {
            $values['dev_' . $field->devDataIndex . '_' . $field->fieldNumber] = $this->readValue($field, $definition->bigEndian);
        }

        return $values;
    }

    /**
     * Reads a single field value and normalizes the FIT "invalid" sentinel
     * (e.g. 0xFFFF for uint16, 0x7FFFFFFF for sint32) to null.
     */
    private function readValue(FieldDefinition $field, bool $bigEndian): mixed
    {
        // String and Byte are always variable-length — use declared size
        if ($field->baseType === BaseType::String) {
            $value = $this->reader->readString($field->size);
            return $value === '' ? null : $value;
        }
        if ($field->baseType === BaseType::Byte) {
            return $this->reader->readBytes($field->size);
        }

        // Declared size takes priority over base type natural size (e.g. uint32 packed as 1 byte)
        if ($field->size !== $field->baseType->size()) {
            return $this->reader->readBytes($field->size);
        }

        $value = match ($field->baseType) {
            BaseType::Uint8, BaseType::Enum, BaseType::Uint8z
                => $this->reader->readUint8(),
            BaseType::Sint8
                => $this->reader->readInt8(),
            BaseType::Uint16, BaseType::Uint16z
                => $this->reader->readUint16($bigEndian),
            BaseType::Sint16
                => $this->reader->readInt16($bigEndian),
            BaseType::Uint32, BaseType::Uint32z
                => $this->reader->readUint32($bigEndian),
            BaseType::Sint32
                => $this->reader->readInt32($bigEndian),
            BaseType::Float32
                => $this->reader->readFloat32($bigEndian),
            default
                => $this->reader->readBytes($field->size),
        };

        // Float32 invalid (0xFFFFFFFF bit pattern) decodes to NaN
        if (is_float($value) && is_nan($value)) {
            return null;
        }

        return $value === $field->baseType->invalidValue() ? null : $value;
    }

    /** @param array<int, mixed> $v */
    private function handleFileId(array $v): void
    {
        if (isset($v[4])) {
            $this->createdAt = $this->toDateTime((int) $v[4]);
        }
    }

    /** @param array<int, mixed> $v */
    private function handleDeviceInfo(array $v): void
    {
        // device_index 0 is the file creator (the watch/bike computer that
        // recorded the activity); other indexes are paired sensors such as
        // an HR strap. Prefer the creator, fall back to the first message.
        $isCreator = isset($v[0]) && (int) $v[0] === 0;

        if ($this->device !== null && ($this->deviceIsCreator || !$isCreator)) {
            return;
        }

        $this->deviceIsCreator = $isCreator;

        $manufacturer = isset($v[1])
            ? Manufacturer::fromFitValue((int) $v[1])
            : Manufacturer::Unknown;

        $productName = isset($v[27]) && is_string($v[27]) && $v[27] !== ''
            ? $v[27]
            : null;

        $this->device = new DeviceInfo(
            manufacturer: $manufacturer,
            productName: $productName,
            serialNumber: isset($v[3]) ? (int) $v[3] : null,
            // software_version is stored with scale 100 (e.g. 950 → "9.50")
            softwareVersion: isset($v[5]) ? sprintf('%.2f', (int) $v[5] / 100) : null,
        );
    }

    /** @param array<int|string, mixed> $v */
    private function handleRecord(array $v): void
    {
        if (!isset($v[253])) {
            return; // no timestamp — skip
        }

        $devFields = [];
        foreach ($v as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'dev_')) {
                continue;
            }
            [, $devIdx, $fieldNum] = explode('_', $key, 3);
            $meta = $this->developerFields[(int) $devIdx][(int) $fieldNum] ?? null;
            if ($meta !== null) {
                $devFields[$meta['name']] = $value;
            }
        }

        $this->pendingRecords[] = new Record(
            timestamp: $this->toDateTime((int) $v[253]),
            lat: isset($v[0]) ? $this->semicirclesToDeg((int) $v[0]) : null,
            lon: isset($v[1]) ? $this->semicirclesToDeg((int) $v[1]) : null,
            altitude: isset($v[2]) ? ((int) $v[2] / 5 - 500) : null,
            heartRate: isset($v[3]) ? (int) $v[3] : null,
            cadence: isset($v[4]) ? (int) $v[4] : null,
            speed: isset($v[6]) ? ((int) $v[6] / 1000) : null,
            power: isset($v[7]) ? (float) $v[7] : null,
            distance: isset($v[5]) ? ((int) $v[5] / 100) : null,
            temperature: isset($v[13]) ? (float) $v[13] : null,
            developerFields: $devFields,
        );
    }

    /** @param array<int, mixed> $v */
    private function handleFieldDescription(array $v): void
    {
        $devIdx   = isset($v[0]) ? (int) $v[0] : null;
        $fieldNum = isset($v[1]) ? (int) $v[1] : null;
        $name     = isset($v[3]) && is_string($v[3]) && $v[3] !== '' ? $v[3] : null;

        if ($devIdx === null || $fieldNum === null || $name === null) {
            return;
        }

        $baseTypeId = isset($v[2]) ? ((int) $v[2] & 0x9F) : null;
        $baseType   = $baseTypeId !== null ? (BaseType::tryFrom($baseTypeId) ?? BaseType::Uint8) : BaseType::Uint8;

        $this->developerFields[$devIdx][$fieldNum] = [
            'name'      => $name,
            'base_type' => $baseType,
        ];
    }

    /** @param array<int, mixed> $v */
    private function handleLap(array $v): void
    {
        if (!isset($v[253], $v[2])) {
            return;
        }

        $this->pendingLaps[] = new Lap(
            startTime: $this->toDateTime((int) $v[2]),
            endTime: $this->toDateTime((int) $v[253]),
            totalDistance: isset($v[9]) ? ((int) $v[9] / 100) : 0.0,
            totalElapsedTime: isset($v[7]) ? (int) ((int) $v[7] / 1000) : 0,
            totalTimerTime: isset($v[8]) ? (int) ((int) $v[8] / 1000) : 0,
            avgHeartRate: isset($v[15]) ? (int) $v[15] : null,
            maxHeartRate: isset($v[16]) ? (int) $v[16] : null,
            avgSpeed: isset($v[13]) ? ((int) $v[13] / 1000) : null,
            maxSpeed: isset($v[14]) ? ((int) $v[14] / 1000) : null,
            avgPower: isset($v[19]) ? (float) $v[19] : null,
            maxPower: isset($v[20]) ? (float) $v[20] : null,
            avgCadence: isset($v[17]) ? (int) $v[17] : null,
            totalAscent: isset($v[21]) ? (float) $v[21] : null,
            totalDescent: isset($v[22]) ? (float) $v[22] : null,
            lapNumber: count($this->pendingLaps),
        );
    }

    /** @param array<int, mixed> $v */
    private function handleSession(array $v): void
    {
        $sport = isset($v[5]) ? Sport::fromFitValue((int) $v[5]) : Sport::Generic;

        $this->sessions[] = new Session(
            sport: $sport,
            startTime: isset($v[2]) ? $this->toDateTime((int) $v[2]) : new \DateTimeImmutable(),
            totalElapsedTime: isset($v[7]) ? (int) ((int) $v[7] / 1000) : 0,
            totalTimerTime: isset($v[8]) ? (int) ((int) $v[8] / 1000) : 0,
            totalDistance: isset($v[9]) ? ((int) $v[9] / 100) : 0.0,
            totalAscent: isset($v[22]) ? (float) $v[22] : null,
            totalDescent: isset($v[23]) ? (float) $v[23] : null,
            avgHeartRate: isset($v[16]) ? (int) $v[16] : null,
            maxHeartRate: isset($v[17]) ? (int) $v[17] : null,
            avgSpeed: isset($v[14]) ? ((int) $v[14] / 1000) : null,
            maxSpeed: isset($v[15]) ? ((int) $v[15] / 1000) : null,
            avgPower: isset($v[20]) ? (float) $v[20] : null,
            maxPower: isset($v[21]) ? (float) $v[21] : null,
            avgCadence: isset($v[18]) ? (int) $v[18] : null,
            records: $this->pendingRecords,
            laps: $this->pendingLaps,
        );

        $this->pendingRecords = [];
        $this->pendingLaps    = [];
    }

    /**
     * Records/laps left after the last session message — or in files with no
     * session message at all (interrupted recordings, some devices) — would
     * otherwise be silently dropped. Wrap them in a synthetic session.
     */
    private function flushPendingIntoFallbackSession(): void
    {
        if ($this->pendingRecords === [] && $this->pendingLaps === []) {
            return;
        }

        if ($this->pendingRecords !== []) {
            $startTime = $this->pendingRecords[0]->timestamp;
            $endTime   = $this->pendingRecords[count($this->pendingRecords) - 1]->timestamp;
        } else {
            $startTime = $this->pendingLaps[0]->startTime;
            $endTime   = $this->pendingLaps[count($this->pendingLaps) - 1]->endTime;
        }

        $elapsed = max(0, $endTime->getTimestamp() - $startTime->getTimestamp());

        $distance = 0.0;
        for ($i = count($this->pendingRecords) - 1; $i >= 0; $i--) {
            if ($this->pendingRecords[$i]->distance !== null) {
                $distance = $this->pendingRecords[$i]->distance;
                break;
            }
        }

        $this->sessions[] = new Session(
            sport: Sport::Generic,
            startTime: $startTime,
            totalElapsedTime: $elapsed,
            totalTimerTime: $elapsed,
            totalDistance: $distance,
            totalAscent: null,
            totalDescent: null,
            avgHeartRate: null,
            maxHeartRate: null,
            avgSpeed: null,
            maxSpeed: null,
            avgPower: null,
            maxPower: null,
            avgCadence: null,
            records: $this->pendingRecords,
            laps: $this->pendingLaps,
        );

        $this->pendingRecords = [];
        $this->pendingLaps    = [];
    }

    private function toDateTime(int $fitTimestamp): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@' . (int) ($fitTimestamp + self::FIT_EPOCH_OFFSET));
    }

    private function semicirclesToDeg(int $semicircles): float
    {
        return $semicircles * self::SEMICIRCLE_TO_DEG;
    }
}
