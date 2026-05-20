<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Tests\Unit;

use Beljic\FitSdk\Data\Activity;
use Beljic\FitSdk\Data\Record;
use Beljic\FitSdk\Data\Session;
use Beljic\FitSdk\Export\GpxExporter;
use Beljic\FitSdk\Profile\Sport;
use PHPUnit\Framework\TestCase;

final class GpxExporterTest extends TestCase
{
    private GpxExporter $exporter;

    protected function setUp(): void
    {
        $this->exporter = new GpxExporter();
    }

    public function testExportProducesValidGpxStructure(): void
    {
        $activity = $this->makeActivity();
        $gpx      = $this->exporter->export($activity);

        $doc = new \DOMDocument();
        self::assertTrue($doc->loadXML($gpx), 'Output must be valid XML');

        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('g', 'http://www.topografix.com/GPX/1/1');

        self::assertSame('1.1', $doc->documentElement->getAttribute('version'));
        self::assertGreaterThan(0, $xpath->query('//g:gpx/g:trk')->length);
        self::assertGreaterThan(0, $xpath->query('//g:trkpt')->length);
    }

    public function testMetadataTimeMatchesActivityCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2024-06-15T08:00:00+00:00');
        $activity  = $this->makeActivity(createdAt: $createdAt);
        $gpx       = $this->exporter->export($activity);

        self::assertStringContainsString('2024-06-15T08:00:00', $gpx);
    }

    public function testTrackNameIncludesSportAndDate(): void
    {
        $start    = new \DateTimeImmutable('2024-06-15T08:00:00+00:00');
        $activity = $this->makeActivity(startTime: $start);
        $gpx      = $this->exporter->export($activity);

        self::assertStringContainsString('Running', $gpx);
        self::assertStringContainsString('2024-06-15', $gpx);
    }

    public function testTrackPointContainsCoordinates(): void
    {
        $activity = $this->makeActivity();
        $doc      = $this->loadXml($this->exporter->export($activity));
        $xpath    = new \DOMXPath($doc);
        $xpath->registerNamespace('g', 'http://www.topografix.com/GPX/1/1');

        /** @var \DOMElement $pt */
        $pt = $xpath->query('//g:trkpt')->item(0);
        self::assertNotNull($pt);
        self::assertEqualsWithDelta(44.81250, (float) $pt->getAttribute('lat'), 0.00001);
        self::assertEqualsWithDelta(20.46120, (float) $pt->getAttribute('lon'), 0.00001);
    }

    public function testTrackPointContainsElevation(): void
    {
        $activity = $this->makeActivity();
        $gpx      = $this->exporter->export($activity);

        self::assertStringContainsString('<ele>312.0</ele>', $gpx);
    }

    public function testTrackPointContainsHeartRateExtension(): void
    {
        $activity = $this->makeActivity();
        $gpx      = $this->exporter->export($activity);

        self::assertStringContainsString('gpxtpx:hr', $gpx);
        self::assertStringContainsString('>155<', $gpx);
    }

    public function testTrackPointContainsCadenceExtension(): void
    {
        $activity = $this->makeActivity();
        $gpx      = $this->exporter->export($activity);

        self::assertStringContainsString('gpxtpx:cad', $gpx);
        self::assertStringContainsString('>82<', $gpx);
    }

    public function testRecordWithoutCoordinatesIsSkipped(): void
    {
        $ts      = new \DateTimeImmutable('2024-06-15T08:00:00+00:00');
        $session = $this->makeSession(startTime: $ts, records: [
            new Record(timestamp: $ts, lat: null, lon: null, altitude: 312.0, heartRate: 155, cadence: 82,
                speed: null, power: null, distance: null, temperature: null),
            new Record(timestamp: $ts, lat: 44.8125, lon: 20.4612, altitude: 312.0, heartRate: 155, cadence: 82,
                speed: null, power: null, distance: null, temperature: null),
        ]);

        $activity = new Activity(
            createdAt: new \DateTimeImmutable('2024-06-15T08:00:00+00:00'),
            device: null,
            sessions: [$session],
        );

        $doc   = $this->loadXml($this->exporter->export($activity));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('g', 'http://www.topografix.com/GPX/1/1');

        self::assertSame(1, $xpath->query('//g:trkpt')->length, 'Record with null lat/lon must be excluded');
    }

    public function testEmptyActivityProducesGpxWithNoTracks(): void
    {
        $activity = new Activity(
            createdAt: new \DateTimeImmutable('2024-06-15T08:00:00+00:00'),
            device: null,
            sessions: [],
        );

        $doc   = $this->loadXml($this->exporter->export($activity));
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('g', 'http://www.topografix.com/GPX/1/1');

        self::assertSame(0, $xpath->query('//g:trk')->length);
    }

    public function testRouteOnlyExcludesExtensions(): void
    {
        $options  = new \Beljic\FitSdk\Export\ExportOptions(routeOnly: true);
        $activity = $this->makeActivity();
        $gpx      = $this->exporter->export($activity, $options);

        self::assertStringNotContainsString('gpxtpx:hr', $gpx);
        self::assertStringNotContainsString('gpxtpx:cad', $gpx);
        self::assertStringNotContainsString('extensions', $gpx);
    }

    public function testRouteOnlyPreservesGeometry(): void
    {
        $options  = new \Beljic\FitSdk\Export\ExportOptions(routeOnly: true);
        $activity = $this->makeActivity();
        $doc      = $this->loadXml($this->exporter->export($activity, $options));
        $xpath    = new \DOMXPath($doc);
        $xpath->registerNamespace('g', 'http://www.topografix.com/GPX/1/1');

        $pt = $xpath->query('//g:trkpt')->item(0);
        self::assertNotNull($pt);
        self::assertNotEmpty($pt->getAttribute('lat'));
        self::assertNotEmpty($pt->getAttribute('lon'));
        self::assertGreaterThan(0, $xpath->query('//g:ele')->length);
        self::assertGreaterThan(0, $xpath->query('//g:time')->length);
    }

    public function testDefaultOptionsPreservesExtensions(): void
    {
        // Calling export() without options must still include HR/cadence (backwards compat)
        $gpx = $this->exporter->export($this->makeActivity());

        self::assertStringContainsString('gpxtpx:hr', $gpx);
        self::assertStringContainsString('gpxtpx:cad', $gpx);
    }

    // --- Helpers ---

    private function makeActivity(
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $startTime = null,
    ): Activity {
        $ts = new \DateTimeImmutable('2024-06-15T08:00:00+00:00');

        return new Activity(
            createdAt: $createdAt ?? $ts,
            device: null,
            sessions: [$this->makeSession(startTime: $startTime ?? $ts)],
        );
    }

    /** @param Record[] $records */
    private function makeSession(
        \DateTimeImmutable $startTime,
        array $records = [],
    ): Session {
        if ($records === []) {
            $records = [
                new Record(
                    timestamp:  $startTime,
                    lat:        44.8125,
                    lon:        20.4612,
                    altitude:   312.0,
                    heartRate:  155,
                    cadence:    82,
                    speed:      null,
                    power:      null,
                    distance:   null,
                    temperature: null,
                ),
            ];
        }

        return new Session(
            sport:            Sport::Running,
            startTime:        $startTime,
            totalElapsedTime: 3600,
            totalTimerTime:   3600,
            totalDistance:    10000.0,
            totalAscent:      null,
            totalDescent:     null,
            avgHeartRate:     null,
            maxHeartRate:     null,
            avgSpeed:         null,
            maxSpeed:         null,
            avgPower:         null,
            maxPower:         null,
            avgCadence:       null,
            records:          $records,
        );
    }

    private function loadXml(string $xml): \DOMDocument
    {
        $doc = new \DOMDocument();
        $doc->loadXML($xml);
        return $doc;
    }
}
