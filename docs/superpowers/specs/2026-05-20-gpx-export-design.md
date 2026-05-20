# GPX Export — Design Spec

**Date:** 2026-05-20
**Status:** Approved

## Context

`GpxExporter` already exists in `src/Export/GpxExporter.php` and is tested in `tests/Unit/GpxExporterTest.php`. The CLI exposes `fit gpx <file.fit>` → stdout.

**What's missing:**
- Route-only mode (no HR/cadence extensions) — primary use case is route sharing
- `exportToFile()` method
- CLI flags: `--route-only`, `--out=`
- Bulk conversion: entire directory of `.fit` files → directory of `.gpx` files

## Scope

### In scope

1. `ExportOptions` value object — `src/Export/ExportOptions.php`
2. `GpxExporter::export()` — accept optional `ExportOptions` (backwards compatible)
3. `GpxExporter::exportToFile()` — new method
4. CLI `fit gpx` — add `--route-only` and `--out=` flags
5. CLI `fit bulk` — new subcommand for directory-to-directory conversion

### Out of scope

- Lap markers as waypoints (explicit decision)
- Recursive directory scanning in bulk
- Progress in PHP API (CLI only)
- TCX export
- Streaming / chunked output for large files

## New file: `src/Export/ExportOptions.php`

```php
readonly class ExportOptions
{
    public function __construct(
        public bool $routeOnly = false,
    ) {}
}
```

`routeOnly = true` suppresses the `<extensions>` block on every `<trkpt>`. Output is geometry only: lat, lon, ele, time.

## Changes to `src/Export/GpxExporter.php`

### `export()` — updated signature

```php
public function export(Activity $activity, ExportOptions $options = new ExportOptions()): string
```

Backwards compatible — existing call sites with no second argument keep working.

Behaviour change: when `$options->routeOnly === true`, `buildTrackPoint()` skips the `<extensions>` block entirely (no `gpxtpx:TrackPointExtension`, no `gpxtpx:hr`, no `gpxtpx:cad`).

### `exportToFile()` — new method

```php
public function exportToFile(Activity $activity, string $path, ExportOptions $options = new ExportOptions()): void
```

- Creates parent directory with `mkdir(recursive: true)` if it does not exist
- Writes via `file_put_contents`
- Throws `\RuntimeException` if write fails (not a domain exception — this is an I/O concern)

## Changes to `bin/fit`

### `fit gpx` — new flags

```
fit gpx <file.fit> [--route-only] [--out=<output.gpx>]
```

- `--route-only` → passes `new ExportOptions(routeOnly: true)`
- `--out=<path>` → calls `exportToFile()` instead of echoing to stdout
- Without `--out`: output to stdout (existing behaviour)

### `fit bulk` — new subcommand

```
fit bulk <input-dir> --out=<output-dir> [--with-sensors]
```

- `--out=` is required; exits with error if missing
- Scans `<input-dir>/*.fit` (non-recursive, `glob()`)
- Default: `routeOnly = true` (sharing use case)
- `--with-sensors`: disables route-only, exports full sensor data
- Output filename: same stem, extension changed to `.gpx`
  - `trail-run-2026-05-01.fit` → `trail-run-2026-05-01.gpx`
- Creates output dir if it does not exist
- Progress output to stdout: `[3/47] trail-run-2026-05-01.fit → OK`
- On parse/write error: prints error line, continues to next file
- Final summary: `Done. 45 exported, 2 failed.`
- Exit code: `0` if all succeeded, `1` if any failed

## Tests

All tests use `StringSource` / in-memory fixtures. No real `.fit` files.

### New tests in `GpxExporterTest`

| Test | Assertion |
|---|---|
| `testRouteOnlyExcludesExtensions` | No `gpxtpx:hr` or `gpxtpx:cad` in output |
| `testRouteOnlyPreservesGeometry` | lat, lon, ele, time still present |
| `testFullExportPreservesExtensions` | Default options still include HR/cadence |
| `testExportToFileWritesFile` | File exists and contains valid XML after call |
| `testExportToFileCreatesDirectory` | Creates missing output directory |
| `testExportToFileThrowsOnUnwritablePath` | `RuntimeException` on bad path |

### No new test class for CLI

Bulk CLI logic is thin (loop + error handling). Integration tested manually against `tmp/` files.

## Public API impact

`GpxExporter::export()` signature changes from:
```php
export(Activity $activity): string
```
to:
```php
export(Activity $activity, ExportOptions $options = new ExportOptions()): string
```

**No breaking change** — default value means all existing callers work without modification.

`ExportOptions` is a new public class in `Beljic\FitSdk\Export\`.

## File layout after implementation

```
src/Export/
    ExportOptions.php   ← new
    GpxExporter.php     ← modified

bin/fit                 ← modified (gpx flags + bulk command)

tests/Unit/
    GpxExporterTest.php ← extended with new test methods
```
