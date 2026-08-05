# Region 9 Live Studio 17 RC1 Production Hardening and Staging Validation

This checklist is the release-candidate gate for Region 9 Live Studio 17. RC1 does not enable automatic publishing by default; the manual approval, immutable history, and rollback paths remain required before public content changes.

## Geometry replacement

* `data/region9-counties.geojson` is the canonical local Region 9 county geometry bundle for Kankakee, Iroquois, Ford, Livingston, DeWitt, Piatt, Champaign, Vermilion, and McLean.
* The fixture metadata identifies U.S. Census Bureau TIGER/Line 2024 county boundary data as the production source, records each county GEOID, documents the simplified operational-ring tolerance, and rejects runtime geometry downloads.
* Before launch, compare the bundled rings against the selected TIGER/Line release in GIS tooling and record the reviewer, TIGER/Line vintage, date, source URL, simplification tolerance, and checksum in the launch checklist. `scripts/rc1-validation-suite.php` validates closure, non-empty area, bounds, vertex count, and non-rectangular geometry.

## Clean install and upgrade validation

1. Install the RC1 ZIP on a clean staging WordPress site with no prior `r9ls_*` options.
2. Activate the plugin and confirm one `r9ls_validate_weather_operations` cron event is scheduled.
3. Confirm public pages render quiet-weather fallbacks before any publication state exists.
4. Upgrade an Alpha 8 staging site to RC1 and confirm existing options, approved products, history entries, rollback references, and editor overrides remain readable.
5. Deactivate and reactivate the plugin; confirm duplicate cron events are not created.

## Scheduler soak test

Run a 24-hour minimum soak, and 48 hours when staging is available, with WP-Cron or a real server cron invoking WordPress cron at production cadence.

Record every validation cycle with:

* start and end timestamp,
* duration,
* source-health status for SPC, WPC ERO, WPC QPF, and NWS alerts,
* material-change count,
* lock state,
* memory usage,
* publication state before and after the run.

Pass criteria: no duplicate validation locks, no unbounded history growth, no automatic publication, stale fallback only inside the configured window, and recovery logged when a source returns healthy.

## Operational scenario testing

Validate each scenario on staging and capture screenshots or logs where applicable:

* quiet weather with no Region 9 intersections,
* severe weather outlook intersecting one county,
* severe weather outlook intersecting multiple counties,
* excessive rainfall/flooding risk intersecting at least one county,
* QPF-only precipitation signal,
* malformed geometry from one source,
* HTTP failures from one source,
* all national sources unavailable with no stale cache,
* all national sources unavailable with stale cache available,
* editor override creation, expiration, approval, publication, and rollback.

## Publication workflow validation

* Confirm material changes are queued, not published.
* Confirm only administrators can approve, reject, publish, and roll back.
* Confirm an unapproved change cannot be published.
* Confirm each approved publication creates deterministic public products, content hashes, product versions, publication versions, history IDs, and rollback references.
* Confirm rollback creates a new publication version rather than mutating prior history.

## Public rendering verification

* Verify `[r9ls_public_product]`, `[r9ls_product]`, `[r9ls_county_matrix]`, and theme helper rendering for all supported products.
* Confirm public rendering uses cached approved state and does not trigger external source requests.
* Confirm public REST output excludes rule traces, raw source payloads, admin-only decisions, and internal change records.
* Confirm cache invalidation after new publication and rollback.

## Accessibility and responsive review

* Keyboard-test the admin workflow from validation through approval and rollback.
* Confirm focus states are visible and dialogs or status panels have readable labels.
* Run automated checks with axe or equivalent on public product pages and the admin workspace.
* Review public product pages at 320px, 768px, 1024px, and desktop widths.
* Confirm county matrices remain readable without horizontal content loss.

## Security review

* Re-run PHP lint and the repository validator.
* Verify all write actions require administrator capability and nonces.
* Verify public REST endpoints expose only public fields.
* Verify remote requests use official endpoints, bounded timeouts, User-Agent, JSON validation, retry/backoff, and stale-cache limits.
* Verify rendered output is escaped and imports do not use try/catch wrappers.

## Compatibility matrix

Validate RC1 on the supported matrix and record results in the launch checklist:

| Component | Minimum | Target |
| --- | --- | --- |
| WordPress | 6.5 | latest staging version |
| PHP | 8.0 | 8.2 or 8.3 |
| Browser | current Chrome, Edge, Firefox, Safari | current stable |
| Cron | WP-Cron | server cron invoking WP-Cron |
| Theme | bundled Region 9 theme | production child/customized theme |

## Performance measurements

Record median and p95 values for:

* scheduled validation duration,
* official-source fetch latency by source,
* product generation duration,
* public REST response time,
* public page render time,
* peak PHP memory during validation,
* ZIP size and checksum.

## Final ZIP packaging and checksums

Run `scripts/build-region9-live-studio-zip.sh`. The script writes the RC1 ZIP and SHA-256 checksum to `build/`. Attach both files to the release candidate evidence package.

## Release notes, launch checklist, and rollback procedures

Use `docs/rc1-release-notes.md`, `docs/rc1-launch-checklist.md`, and `docs/rc1-rollback-procedure.md` as the release evidence templates. RC1 is not cleared for production until every mandatory item is marked pass or explicitly waived by the release owner.
