# Region 9 Live Studio 17 Alpha 4

Alpha 4 converts the REST-first Alpha 3 foundation into an automated weather operations plugin. Automatic publishing is disabled by default; material changes require administrator approval before publish.

## Architecture

* `R9LS_Scheduler` owns WordPress Cron scheduling, duplicate prevention, validation locks, stale lock cleanup, scheduler health, and the shared validation path for manual and scheduled runs.
* `R9LS_GIS_Engine` loads local GeoJSON county boundaries for the exact nine Region 9 counties: Adair, Audrain, Boone, Callaway, Chariton, Cole, Cooper, Howard, and Monroe. Runtime geocoding is not used.
* `R9LS_Rule_Engine` centrally evaluates Travel, Agriculture, Fieldwork, Spraying, Harvest, Livestock, Outdoor Events, School Activities, Construction, Utilities, Emergency Operations, Forecast Confidence, and Severe Weather Risk.
* `R9LS_Material_Change_Engine` queues material rating, score, county, hazard, timing, confidence, alert, and source-health changes for approval.
* `R9LS_Admin` provides the Region 9 Studio Automation workspace with escaped output, nonced admin-post write actions, and administrator-only writes.

## Scheduler behavior

Activation schedules one `r9ls_validate_weather_operations` cron event. Deactivation clears it. The default interval is hourly, the active-weather interval is configurable, and the minimum accepted interval is 15 minutes. Manual and cron validation both call the scheduler validation service. A 20-minute transient lock prevents overlap and stale locks are cleared safely.

## GIS matching

SPC Day 1, WPC Day 1 ERO, and NWS alert geometries are matched to county boundaries with longitude/latitude GeoJSON coordinate order, Polygon and MultiPolygon support, bounding-box rejection, point-in-polygon tests, and segment intersection checks. Results distinguish healthy no-hazard/no-intersection outcomes from source failures.

## Scoring rules

All products use shared central scoring. Rules expose `r9ls_product_rules`, `r9ls_product_score`, and `r9ls_product_confidence` filters. Scores are clamped to 0-100. Missing or degraded data lowers confidence rather than silently producing normal confidence. County scores aggregate to the Region 9 score by using the maximum affected county score.

Travel ratings: 0-24 Good, 25-49 Caution, 50-74 Difficult, 75-100 Dangerous.
Region 9 risk: 0 None, 1 Low, 2 Limited, 3 Elevated, 4 Significant.

## REST endpoints

Alpha 4 keeps operations inside authenticated WordPress admin actions and does not add direct public write endpoints. Future REST endpoints must require authentication, capability checks, nonces, sanitization, and audit logging.

## Known limitations

* Bundled county boundaries are simplified local operational fixtures and should be replaced with official production-grade county GeoJSON before public launch.
* Weather source ingestion is filter-driven in Alpha 4; production API adapters remain a later milestone.
* Timing change tolerance is represented in settings but natural-language timing normalization is intentionally conservative.

## Release validation

Run `php scripts/region9-alpha4-validate.php`, PHP lint checks, `scripts/build-region9-live-studio-zip.sh`, and review all admin-post write actions before release.
