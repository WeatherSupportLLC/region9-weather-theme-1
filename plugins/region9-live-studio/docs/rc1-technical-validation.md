# Region 9 Live Studio 17 RC1 Technical Validation

## Official county geometry

The local county bundle contains exactly Kankakee (17091), Iroquois (17075), Ford (17053), Livingston (17105), DeWitt (17039), Piatt (17147), Champaign (17019), Vermilion (17183), and McLean (17113). The documented production source is U.S. Census Bureau TIGER/Line 2024 county boundary data from `https://www.census.gov/geographies/mapping-files/time-series/geo/tiger-line-file.html` and `https://www2.census.gov/geo/tiger/TIGER2024/COUNTY/`.

The bundled rings are documented simplified operational polygons rather than rectangular envelopes. The simplification target is 0.01 decimal degree for deterministic server-side intersection tests. The RC1 validation suite checks ring closure, geometry type, coordinate order via non-empty signed area, bounds, non-empty area, exact GEOIDs, more than four boundary vertices, and non-rectangular uniqueness.

## Executable suites

* `scripts/validate-region9-live-studio.php` remains the broad regression harness.
* `scripts/rc1-validation-suite.php` adds RC1 geometry, install/upgrade, operational scenarios, scheduler recovery, publication safety, public rendering, accessibility/static, security/static, compatibility, and performance assertions.
* `scripts/rc1-scheduler-soak.php` is a repeatable soak runner. CI uses a short duration; staging should run `R9LS_SOAK_SECONDS=86400 R9LS_SOAK_INTERVAL=300 php scripts/rc1-scheduler-soak.php` for 24 hours or `R9LS_SOAK_SECONDS=172800` for 48 hours.

## Compatibility claims

The executable harness simulates WordPress option/transient storage, WP-Cron enabled/disabled conditions, no persistent object cache, simulated cache transients, plain shortcode rendering, pretty-link-independent REST reads, and WP_DEBUG-style warning escalation. Real multisite support is not claimed by RC1 until a staging matrix explicitly signs it off.

## Performance metrics

The validation suite writes `build/rc1-test-report.json` with public REST generation time, peak memory delta, PHP version, assertion count, and generation time. The soak suite writes `build/rc1-soak-report.json` with validation count, failure count, average duration, maximum duration, lock/stale/retry coverage notes, and scheduler drift.

## Required staging evidence

RC1 still requires real WordPress staging evidence for the 24-48 hour soak, browser accessibility/contrast review, responsive screenshots, system-cron operation, production object-cache behavior, and operator sign-off. Do not mark the launch checklist as passed without that evidence.
