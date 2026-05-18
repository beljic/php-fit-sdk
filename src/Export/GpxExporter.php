<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Export;

use Beljic\FitSdk\Data\Activity;
use Beljic\FitSdk\Data\Record;
use Beljic\FitSdk\Data\Session;

final class GpxExporter
{
    public function export(Activity $activity): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $gpx = $dom->createElementNS('http://www.topografix.com/GPX/1/1', 'gpx');
        $gpx->setAttribute('version', '1.1');
        $gpx->setAttribute('creator', 'beljic/php-fit-sdk');
        $gpx->setAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'xsi:schemaLocation',
            'http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd ' .
            'http://www.garmin.com/xmlschemas/TrackPointExtension/v1 ' .
            'http://www.garmin.com/xmlschemas/TrackPointExtensionv1.xsd',
        );
        $gpx->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:gpxtpx',
            'http://www.garmin.com/xmlschemas/TrackPointExtension/v1',
        );
        $dom->appendChild($gpx);

        $meta = $dom->createElement('metadata');
        $meta->appendChild($dom->createElement('time', $activity->createdAt->format(\DateTimeInterface::ATOM)));
        $gpx->appendChild($meta);

        foreach ($activity->sessions as $session) {
            $gpx->appendChild($this->buildTrack($dom, $session));
        }

        return $dom->saveXML() ?: '';
    }

    private function buildTrack(\DOMDocument $dom, Session $session): \DOMElement
    {
        $trk = $dom->createElement('trk');
        $trk->appendChild($dom->createElement('name', $session->sport->name . ' ' . $session->startTime->format('Y-m-d H:i')));
        $trk->appendChild($dom->createElement('type', $session->sport->name));

        $seg = $dom->createElement('trkseg');

        foreach ($session->records as $record) {
            if ($record->lat === null || $record->lon === null) {
                continue;
            }
            $seg->appendChild($this->buildTrackPoint($dom, $record));
        }

        $trk->appendChild($seg);

        return $trk;
    }

    private function buildTrackPoint(\DOMDocument $dom, Record $record): \DOMElement
    {
        $pt = $dom->createElement('trkpt');
        $pt->setAttribute('lat', number_format($record->lat, 7, '.', ''));
        $pt->setAttribute('lon', number_format($record->lon, 7, '.', ''));

        if ($record->altitude !== null) {
            $pt->appendChild($dom->createElement('ele', number_format($record->altitude, 1, '.', '')));
        }

        $pt->appendChild($dom->createElement('time', $record->timestamp->format(\DateTimeInterface::ATOM)));

        $hasExtension = $record->heartRate !== null || $record->cadence !== null;
        if ($hasExtension) {
            $extensions = $dom->createElement('extensions');
            $tpx = $dom->createElementNS(
                'http://www.garmin.com/xmlschemas/TrackPointExtension/v1',
                'gpxtpx:TrackPointExtension',
            );

            if ($record->heartRate !== null) {
                $tpx->appendChild($dom->createElement('gpxtpx:hr', (string) $record->heartRate));
            }
            if ($record->cadence !== null) {
                $tpx->appendChild($dom->createElement('gpxtpx:cad', (string) $record->cadence));
            }

            $extensions->appendChild($tpx);
            $pt->appendChild($extensions);
        }

        return $pt;
    }
}