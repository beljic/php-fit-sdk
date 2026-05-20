# GPX Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing `GpxExporter` with route-only mode and file output, and add a `fit bulk` CLI command for batch FIT → GPX conversion.

**Architecture:** `ExportOptions` is a new readonly value object that controls export behaviour. `GpxExporter` gains an `ExportOptions` parameter (default: full export, backwards compatible) and a new `exportToFile()` method. The CLI is refactored to use flag parsing and gains `--route-only`/`--out=` on `fit gpx` and a new `fit bulk` subcommand.

**Tech Stack:** PHP 8.3, DOMDocument, PHPUnit, `bin/fit` CLI (plain PHP, no framework)

---

## File Map

| Action | File | What changes |
|---|---|---|
| Create | `src/Export/ExportOptions.php` | New readonly value object |
| Modify | `src/Export/GpxExporter.php` | Options param on `export()`, new `exportToFile()`, thread options through private methods |
| Modify | `tests/Unit/GpxExporterTest.php` | 6 new test methods |
| Modify | `bin/fit` | Flag parsing refactor, gpx flags, bulk subcommand |

---

## Task 1: ExportOptions value object

**Files:**
- Create: `src/Export/ExportOptions.php`

- [ ] **Step 1: Create the file**

```php
<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Export;

final readonly class ExportOptions
{
    public function __construct(
        public bool $routeOnly = false,
    ) {}
}
```

- [ ] **Step 2: Verify autoload resolves it**

```bash
php -r "require 'vendor/autoload.php'; new \Beljic\FitSdk\Export\ExportOptions();"
```

Expected: no output, no error.

- [ ] **Step 3: Commit**

```bash
git add src/Export/ExportOptions.php
git commit -m "feat: add ExportOptions value object"
```

---

## Task 2: Route-only mode in GpxExporter

**Files:**
- Modify: `src/Export/GpxExporter.php`
- Modify: `tests/Unit/GpxExporterTest.php`

`export()` gains an optional `ExportOptions` parameter. `buildTrack()` and `buildTrackPoint()` each gain the same parameter. When `routeOnly === true`, `buildTrackPoint()` skips the `<extensions>` block.

- [ ] **Step 1: Add 3 failing tests to `GpxExporterTest`**

Add these methods to the existing `GpxExporterTest` class (after the existing tests, before the `// --- Helpers ---` comment):

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Unit/GpxExporterTest.php --filter "testRouteOnly|testDefaultOptions"
```

Expected: 3 failures — `export()` does not yet accept a second argument.

- [ ] **Step 3: Update `GpxExporter` to accept and thread `ExportOptions`**

Replace the three method signatures and the `buildTrackPoint` extension logic. Full updated class:

```php
<?php

declare(strict_types=1);

namespace Beljic\FitSdk\Export;

use Beljic\FitSdk\Data\Activity;
use Beljic\FitSdk\Data\Record;
use Beljic\FitSdk\Data\Session;

final class GpxExporter
{
    public function export(Activity $activity, ExportOptions $options = new ExportOptions()): string
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
            $gpx->appendChild($this->buildTrack($dom, $session, $options));
        }

        return $dom->saveXML() ?: '';
    }

    private function buildTrack(\DOMDocument $dom, Session $session, ExportOptions $options): \DOMElement
    {
        $trk = $dom->createElement('trk');
        $trk->appendChild($dom->createElement('name', $session->sport->name . ' ' . $session->startTime->format('Y-m-d H:i')));
        $trk->appendChild($dom->createElement('type', $session->sport->name));

        $seg = $dom->createElement('trkseg');

        foreach ($session->records as $record) {
            if ($record->lat === null || $record->lon === null) {
                continue;
            }
            $seg->appendChild($this->buildTrackPoint($dom, $record, $options));
        }

        $trk->appendChild($seg);

        return $trk;
    }

    private function buildTrackPoint(\DOMDocument $dom, Record $record, ExportOptions $options): \DOMElement
    {
        $pt = $dom->createElement('trkpt');
        $pt->setAttribute('lat', number_format($record->lat, 7, '.', ''));
        $pt->setAttribute('lon', number_format($record->lon, 7, '.', ''));

        if ($record->altitude !== null) {
            $pt->appendChild($dom->createElement('ele', number_format($record->altitude, 1, '.', '')));
        }

        $pt->appendChild($dom->createElement('time', $record->timestamp->format(\DateTimeInterface::ATOM)));

        $hasExtension = !$options->routeOnly && ($record->heartRate !== null || $record->cadence !== null);
        if ($hasExtension) {
            $extensions = $dom->createElement('extensions');
            $tpx        = $dom->createElementNS(
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
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
./vendor/bin/phpunit tests/Unit/GpxExporterTest.php
```

Expected: all tests pass (including all pre-existing ones).

- [ ] **Step 5: Commit**

```bash
git add src/Export/GpxExporter.php tests/Unit/GpxExporterTest.php
git commit -m "feat: add route-only export mode via ExportOptions"
```

---

## Task 3: exportToFile() method

**Files:**
- Modify: `src/Export/GpxExporter.php`
- Modify: `tests/Unit/GpxExporterTest.php`

- [ ] **Step 1: Add 3 failing tests**

Add these methods to `GpxExporterTest` (after the route-only tests):

```php
public function testExportToFileWritesValidGpx(): void
{
    $path = sys_get_temp_dir() . '/fit-sdk-test-' . uniqid() . '.gpx';

    $this->exporter->exportToFile($this->makeActivity(), $path);

    self::assertFileExists($path);
    $doc = new \DOMDocument();
    self::assertTrue($doc->loadXML((string) file_get_contents($path)));

    unlink($path);
}

public function testExportToFileCreatesDirectory(): void
{
    $dir  = sys_get_temp_dir() . '/fit-sdk-test-' . uniqid();
    $path = $dir . '/output.gpx';

    $this->exporter->exportToFile($this->makeActivity(), $path);

    self::assertFileExists($path);

    unlink($path);
    rmdir($dir);
}

public function testExportToFileThrowsOnUnwritablePath(): void
{
    $this->expectException(\RuntimeException::class);

    // /nonexistent root means mkdir will fail
    $this->exporter->exportToFile($this->makeActivity(), '/nonexistent/deep/path/out.gpx');
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Unit/GpxExporterTest.php --filter "testExportToFile"
```

Expected: 3 failures — method does not exist yet.

- [ ] **Step 3: Add `exportToFile()` to `GpxExporter`**

Add this method after `export()`:

```php
public function exportToFile(Activity $activity, string $path, ExportOptions $options = new ExportOptions()): void
{
    $dir = dirname($path);

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new \RuntimeException("Cannot create directory: {$dir}");
    }

    if (file_put_contents($path, $this->export($activity, $options)) === false) {
        throw new \RuntimeException("Failed to write GPX file: {$path}");
    }
}
```

- [ ] **Step 4: Run full test suite**

```bash
./vendor/bin/phpunit tests/Unit/GpxExporterTest.php
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Export/GpxExporter.php tests/Unit/GpxExporterTest.php
git commit -m "feat: add GpxExporter::exportToFile() with auto directory creation"
```

---

## Task 4: CLI — `fit gpx` flags

**Files:**
- Modify: `bin/fit`

Refactor argument parsing from positional-only to positional + `--flag` / `--key=value`. Add `--route-only` and `--out=` to the `gpx` case.

- [ ] **Step 1: Replace the argument parsing block at the top of `bin/fit`**

Find this section (roughly lines 8–11):

```php
$command = $argv[1] ?? 'help';
$file    = $argv[2] ?? null;
```

Replace with:

```php
$command = $argv[1] ?? 'help';

// Parse positional arg (file or dir) and --flags from argv[2..]
$file  = null;
$flags = [];
foreach (array_slice($argv, 2) as $arg) {
    if (str_starts_with($arg, '--')) {
        [$key, $value] = array_pad(explode('=', ltrim($arg, '-'), 2), 2, true);
        $flags[$key]   = $value;
    } elseif ($file === null) {
        $file = $arg;
    }
}
```

- [ ] **Step 2: Update the help text and the file-check guard**

Find the existing help block and file-not-found guard:

```php
if ($command === 'help' || ($command !== 'help' && $file === null)) {
    echo <<<HELP
    php-fit-sdk CLI

    Usage:
      fit parse  <file.fit>   Parse FIT file and dump activity summary
      fit info   <file.fit>   Show device info and session metadata
      fit gpx    <file.fit>   Export activity to GPX (stdout)
      fit help                Show this help

    HELP;
    exit(0);
}

if (!is_file($file)) {
    fwrite(STDERR, "Error: file not found: {$file}\n");
    exit(1);
}

$parser   = new FitParser();
$activity = $parser->parseFile($file);
```

Replace with:

```php
if ($command === 'help' || ($command !== 'help' && $command !== 'bulk' && $file === null)) {
    echo <<<HELP
    php-fit-sdk CLI

    Usage:
      fit parse  <file.fit>                         Parse FIT file and dump activity summary
      fit info   <file.fit>                         Show device info and session metadata
      fit gpx    <file.fit> [--route-only] [--out=<output.gpx>]
                                                    Export to GPX (stdout or file)
      fit bulk   <input-dir> --out=<output-dir> [--with-sensors]
                                                    Batch convert *.fit in a directory
      fit help                                      Show this help

    HELP;
    exit(0);
}

if ($command !== 'bulk') {
    if ($file === null || !is_file($file)) {
        fwrite(STDERR, 'Error: file not found: ' . ($file ?? '(none)') . "\n");
        exit(1);
    }
    $parser   = new FitParser();
    $activity = $parser->parseFile($file);
}
```

- [ ] **Step 3: Add the `use` statement for `ExportOptions` at the top of `bin/fit`**

Find the existing use statements:

```php
use Beljic\FitSdk\Export\GpxExporter;
use Beljic\FitSdk\Parser\FitParser;
```

Add `ExportOptions`:

```php
use Beljic\FitSdk\Export\ExportOptions;
use Beljic\FitSdk\Export\GpxExporter;
use Beljic\FitSdk\Parser\FitParser;
```

- [ ] **Step 4: Update the `'gpx'` case in the `match` block**

Find:

```php
'gpx' => (function () use ($activity): void {
    echo (new GpxExporter())->export($activity);
})(),
```

Replace with:

```php
'gpx' => (function () use ($activity, $flags): void {
    $options  = new ExportOptions(routeOnly: isset($flags['route-only']));
    $exporter = new GpxExporter();

    if (isset($flags['out'])) {
        $exporter->exportToFile($activity, $flags['out'], $options);
        fwrite(STDERR, "Saved to {$flags['out']}\n");
    } else {
        echo $exporter->export($activity, $options);
    }
})(),
```

- [ ] **Step 5: Verify existing behaviour still works**

```bash
# Should print GPX to stdout (no change)
php bin/fit gpx tmp/some-activity.fit 2>/dev/null | head -5
```

Expected: `<?xml version="1.0"...` and `<gpx version="1.1"...`

If you have no file in `tmp/`, verify the file-not-found error path instead:

```bash
php bin/fit gpx nonexistent.fit
```

Expected: `Error: file not found: nonexistent.fit`

- [ ] **Step 6: Commit**

```bash
git add bin/fit
git commit -m "feat: add --route-only and --out flags to fit gpx CLI"
```

---

## Task 5: CLI — `fit bulk` subcommand

**Files:**
- Modify: `bin/fit`

- [ ] **Step 1: Add the `'bulk'` case to the `match` block in `bin/fit`**

Add before the `default` case:

```php
'bulk' => (function () use ($file, $flags): void {
    if ($file === null || !isset($flags['out'])) {
        fwrite(STDERR, "Usage: fit bulk <input-dir> --out=<output-dir> [--with-sensors]\n");
        exit(1);
    }

    if (!is_dir($file)) {
        fwrite(STDERR, "Error: not a directory: {$file}\n");
        exit(1);
    }

    $inputDir  = rtrim($file, '/');
    $outputDir = rtrim($flags['out'], '/');
    $options   = new ExportOptions(routeOnly: !isset($flags['with-sensors']));
    $fitFiles  = glob($inputDir . '/*.fit') ?: [];

    if ($fitFiles === []) {
        echo "No .fit files found in {$inputDir}\n";
        exit(0);
    }

    $total    = count($fitFiles);
    $failed   = 0;
    $parser   = new FitParser();
    $exporter = new GpxExporter();

    foreach ($fitFiles as $i => $fitFile) {
        $basename = basename($fitFile, '.fit');
        $outPath  = $outputDir . '/' . $basename . '.gpx';
        $label    = '[' . ($i + 1) . "/{$total}] " . basename($fitFile);

        try {
            $act = $parser->parseFile($fitFile);
            $exporter->exportToFile($act, $outPath, $options);
            echo "{$label} → OK\n";
        } catch (\Throwable $e) {
            echo "{$label} → FAILED: {$e->getMessage()}\n";
            $failed++;
        }
    }

    $exported = $total - $failed;
    echo "\nDone. {$exported} exported, {$failed} failed.\n";
    exit($failed > 0 ? 1 : 0);
})(),
```

- [ ] **Step 2: Verify help text shows bulk**

```bash
php bin/fit help
```

Expected output includes:

```
fit bulk   <input-dir> --out=<output-dir> [--with-sensors]
```

- [ ] **Step 3: Test bulk against tmp/ (manual)**

If you have `.fit` files in `tmp/`:

```bash
mkdir -p /tmp/gpx-out
php bin/fit bulk tmp/ --out=/tmp/gpx-out
ls /tmp/gpx-out
```

Expected:
```
[1/N] activity-name.fit → OK
...
Done. N exported, 0 failed.
```

Verify a GPX file is valid:
```bash
head -3 /tmp/gpx-out/activity-name.gpx
```

Expected: `<?xml ...` and `<gpx version="1.1" ...`

- [ ] **Step 4: Test --with-sensors produces extensions**

```bash
php bin/fit bulk tmp/ --out=/tmp/gpx-sensors --with-sensors
grep -l "gpxtpx:hr" /tmp/gpx-sensors/*.gpx | wc -l
```

Expected: same count as number of FIT files that have HR data.

- [ ] **Step 5: Test missing --out produces error**

```bash
php bin/fit bulk tmp/
```

Expected: `Usage: fit bulk <input-dir> --out=<output-dir> [--with-sensors]`

- [ ] **Step 6: Run full test suite to confirm nothing is broken**

```bash
./vendor/bin/phpunit
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add bin/fit
git commit -m "feat: add fit bulk CLI command for batch FIT to GPX conversion"
```
