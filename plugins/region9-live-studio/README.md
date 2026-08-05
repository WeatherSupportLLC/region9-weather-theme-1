# Region 9 Live Studio 17 RC1

RC1 hardens the automated Region 9 weather operations plugin for staging validation and production release readiness. Automatic publishing remains disabled by default; source refresh, validation, scoring, product generation, and rollback never publish a change without the existing manual approval path.

## Architecture

* `R9LS_Scheduler` owns WordPress Cron scheduling, duplicate prevention, validation locks, stale lock cleanup, scheduler health, and the shared validation path for manual and scheduled runs.
* `R9LS_National_Guidance` retrieves official national guidance with `wp_remote_get`, a configurable timeout and User-Agent, status-code and JSON validation, retry/backoff, success caching, stale fallback, latency tracking, last-success timestamps, and source-health persistence.
* `R9LS_GIS_Engine` loads local GeoJSON county boundaries for Kankakee, Iroquois, Ford, Livingston, DeWitt, Piatt, Champaign, Vermilion, and McLean, then intersects Polygon and MultiPolygon national guidance with county geometry.
* `R9LS_Rule_Engine` evaluates Region 9 products with deterministic rule weights for SPC category, WPC ERO category, NWS alerts, and WPC QPF factors while retaining county-specific impacts before Region 9 aggregation.
* `R9LS_Material_Change_Engine` queues material rating, score, county, hazard, timing, confidence, alert, and source-health changes for approval.
* `R9LS_Admin` provides the Region 9 Studio Automation workspace with escaped output, nonced admin-post write actions, and administrator-only writes.

## Official endpoints selected

* SPC Day 1 categorical convective outlook GeoJSON: `https://www.spc.noaa.gov/products/outlook/day1otlk_cat.nolyr.geojson`.
* WPC Day 1 excessive rainfall outlook GeoJSON: `https://www.wpc.ncep.noaa.gov/qpf/ero_day1.geojson`.
* WPC Day 1 QPF machine-readable GeoJSON: `https://www.wpc.ncep.noaa.gov/qpf/day1_qpf.geojson`.

QPF values are normalized to inches internally. The parser accepts `qpf_in` directly or converts `qpf_mm` to inches using 25.4 millimeters per inch. Missing QPF values reduce confidence and are never replaced with climatology or fabricated precipitation.

## Source health behavior

National guidance distinguishes these states:

* `healthy`: the source was fetched or cached successfully. A healthy zero-impact/no-intersection result remains available data, not a failure.
* `stale_cached_result`: live refresh failed or official timestamps are stale, but a prior successful payload is still inside the stale-fallback window.
* `unavailable_source`: HTTP, status-code, or payload retrieval failed and no stale payload is usable.
* `malformed_geometry`: the source returned unsupported or invalid geometry.

Degradation and recovery are logged when persisted source-health status changes. Validation duration and health are stored by the scheduler.

## Scheduler behavior

Activation schedules one `r9ls_validate_weather_operations` cron event. Deactivation clears it. Manual and cron validation both collect SPC Day 1, WPC Day 1 ERO, WPC Day 1 QPF, and NWS alert sources before rule evaluation. A source refresh success only updates inputs; publishing still requires manual approval.

## Release validation

Run `php scripts/validate-region9-live-studio.php`, PHP lint checks, `scripts/build-region9-live-studio-zip.sh`, and a plugin activation smoke test before release. Complete the RC1 evidence gates in `docs/rc1-production-hardening.md`, including clean install/upgrade validation, 24-48 hour scheduler soak testing, operational scenarios, publication workflow validation, public rendering checks, accessibility and responsive review, security review, compatibility matrix, performance measurements, final ZIP checksums, launch checklist sign-off, and rollback rehearsal.

## Known limitations

* RC1 requires launch evidence that bundled county boundaries have been reviewed against the selected official U.S. Census Bureau TIGER/Line vintage, with checksum and reviewer recorded in `docs/rc1-launch-checklist.md`.
* The official WPC QPF endpoint is consumed as machine-readable GeoJSON; if NOAA changes the schema, the parser will mark the source degraded rather than invent values.
* Natural-language timing normalization remains conservative; timestamps are carried through from official machine-readable metadata when present.

## RC1 Forecast Production Engine

See `docs-alpha7.md` for product schema, timing normalization, county aggregation, REST endpoints, theme helpers, security behavior, known limitations, and staging installation instructions.

## RC1 production hardening

The complete RC1 staging and launch checklist lives in `docs/rc1-production-hardening.md`. Release evidence templates are available in `docs/rc1-release-notes.md`, `docs/rc1-launch-checklist.md`, and `docs/rc1-rollback-procedure.md`.
