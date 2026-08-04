# Region 9 Live Studio 17 Alpha 6

Alpha 5 adds live national guidance ingestion to the automated Region 9 weather operations plugin. Automatic publishing remains disabled by default; source refresh, validation, and scoring never publish a change without the existing manual approval path.

## Architecture

* `R9LS_Scheduler` owns WordPress Cron scheduling, duplicate prevention, validation locks, stale lock cleanup, scheduler health, and the shared validation path for manual and scheduled runs.
* `R9LS_National_Guidance` retrieves official national guidance with `wp_remote_get`, a configurable timeout and User-Agent, status-code and JSON validation, retry/backoff, success caching, stale fallback, latency tracking, last-success timestamps, and source-health persistence.
* `R9LS_GIS_Engine` loads local GeoJSON county boundaries for Adair, Audrain, Boone, Callaway, Chariton, Cole, Cooper, Howard, and Monroe, then intersects Polygon and MultiPolygon national guidance with county geometry.
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

Run `php scripts/validate-region9-live-studio.php`, PHP lint checks, `scripts/build-region9-live-studio-zip.sh`, and a plugin activation smoke test before release.

## Known limitations

* Bundled county boundaries are simplified local operational fixtures and should be replaced with official production-grade county GeoJSON before public launch.
* The official WPC QPF endpoint is consumed as machine-readable GeoJSON; if NOAA changes the schema, the parser will mark the source degraded rather than invent values.
* Natural-language timing normalization remains conservative; timestamps are carried through from official machine-readable metadata when present.

## Alpha 6 publishing and website integration

Alpha 6 introduces a single canonical publication state stored in the versioned `r9ls_publication_state_v1` option. Validation and ingestion still update the internal operational cache only; manual approval remains the default, and public rendering reads the canonical publication state instead of calling external weather APIs.

### Public read-only REST endpoints

* `GET /wp-json/region9-live-studio/v1/publication` returns publication metadata, version, products, and active non-expired overrides.
* `GET /wp-json/region9-live-studio/v1/products` returns the published product map.
* `GET /wp-json/region9-live-studio/v1/products/{product}` returns one published product by name.

### Administrator write endpoints and actions

All write endpoints require `manage_options`:

* `POST /wp-json/region9-live-studio/v1/publish` publishes the current validated cache into the canonical state. If `change_id` is supplied, that material change must already be approved.
* `POST /wp-json/region9-live-studio/v1/rollback` with `version` creates a new publication version from an immutable history version.
* `POST /wp-json/region9-live-studio/v1/overrides` with `product`, `summary`, and `expires` creates a temporary expiring editor override.

The admin screen also exposes manual publish, rollback, and override controls through nonced `admin-post.php` actions.

### Theme integration

Use the non-invasive shortcode `[region9_live_studio product="Severe Weather Risk"]` to render a REST-compatible public card from the local canonical publication state. Themes can also fetch the public REST endpoints client-side; those responses are read-only and do not call SPC, WPC, NWS, or other external weather APIs during page rendering.

### Publishing guarantees

* Publication history is immutable and append-only in `r9ls_publication_history_v1`.
* Duplicate publishes are prevented with a SHA-256 content hash.
* Rollback creates a new publication version rather than mutating old history.
* Cache invalidation runs after publish, rollback, override creation, and override expiration via `r9ls_publication_cache_invalidated`.
* Overrides expire automatically when the canonical state is read by validation tooling or via explicit expiration.

### Alpha 6 pre-merge verification

* Homepage and product-page integrations must follow `Scheduler → Decision Engine → Publication State → REST → Page`; public pages should never request NOAA/SPC/WPC/NWS endpoints directly.
* Homepage, Daily Forecast, Travel, Agriculture, Outdoor, Construction, Livestock, and Forecast Confidence views should all read the same canonical `r9ls_publication_state_v1` data through the shortcode or public REST endpoints.
* Scheduled validation writes only to the operational cache and pending-change queue; it does not overwrite the canonical publication state.
* Public REST responses expose approved publication metadata and product content only. They intentionally omit pending decisions, override records, audit history, source payloads, and rule traces.
* Cache invalidation is targeted to the changed product keys plus the publication-state key. The plugin does not perform full-site cache flushes.
