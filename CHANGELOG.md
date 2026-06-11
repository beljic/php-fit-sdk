# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- FIT "invalid" sentinel values (e.g. `0xFFFF` for uint16, `0x7FFFFFFF` for sint32)
  are now normalized to `null` for all fields instead of leaking through as bogus
  values (lat/lon as ~180°, speed as 65 m/s, …). Records with an invalid timestamp
  are skipped. Z-types (`uint8z`/`uint16z`/`uint32z`) use `0` as the invalid value
  per the FIT spec.
- `Manufacturer` ids corrected against the official FIT profile: Wahoo is `32`
  (was `32896`), Polar is `123` (was `32`), Decathlon is `310` (was `2069`).
  Added `Suunto` (23) and `Coros` (294).
- Multi-session averages (HR, speed, power, cadence) in `ActivityAnalyzer` are
  now weighted by each session's moving time.
- `device_info` parsing prefers the creator device (`device_index` 0) over paired
  sensors; `software_version` is scaled per the FIT profile (`950` → `"9.50"`).
- `bin/fit` resolves the Composer autoloader when installed as a dependency.
- Test suite passes on case-sensitive filesystems (`tests/Fixtures` PSR-4 path).

### Added

- `FitSdkException` marker interface implemented by every exception the library
  throws; `GpxExporter` throws `GpxExportException` instead of bare
  `\RuntimeException` (still a `\RuntimeException` subclass).
- Fallback session synthesis for records/laps without a session message
  (interrupted recordings) instead of silently dropping them.
- PHPStan (level 8), Composer scripts (`test`, `analyse`, `check`), GitHub
  Actions CI on PHP 8.3/8.4.

### Changed

- **Breaking:** enum case `Manufacturer::SparkHlt` renamed to
  `Manufacturer::SparkHk` to match the FIT profile name `spark_hk`
  (backing value `10` unchanged).
