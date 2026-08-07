# Region 9 Automated Weather Hub — Production Contract

## Mission
Region9Weather.com is the public, easy-to-understand weather hub for east-central Illinois. The system must automate forecast ingestion, threat assessment, graphics production, publication, and public presentation while retaining safe stale/error behavior and operator control.

## Canonical Region 9 geography
Region 9 is exactly these nine Illinois counties: Kankakee, Iroquois, Ford, Livingston, McLean, Piatt, DeWitt, Champaign, and Vermilion.

Use one canonical geography service/data definition for county FIPS/zone IDs, authoritative GIS geometry, alert filtering, graphics, map layers, city/county pages, and the surrounding-alert buffer. Do not duplicate county lists across modules.

## Region 9 risk scale
All Region 9 decision-support surfaces use: None, Low, Limited, Elevated, Significant. This is a Region 9 communication/impact scale and must not be represented as an official NWS/SPC category.

Maintain per-hazard levels and an overall Region 9 level. Hazards include severe thunderstorms, tornado, damaging wind, hail, lightning, flooding/heavy rain, winter weather, heat, cold, fog, travel, agriculture/fieldwork, livestock, outdoor events, drought/water and other supported operational threats.

## Data and forecast state
Create a normalized canonical forecast/threat state from trusted public meteorological inputs. Preserve source, retrieval time, valid time, issued time, expiration, confidence, geography, and freshness metadata. Never fabricate missing weather data. Expose explicit fresh/stale/unavailable states.

## Automation
Run a complete production cycle every six hours. Also evaluate new observations/forecast/alert state for material change and trigger an immediate production cycle when thresholds are met.

Material changes include risk-level changes, new/cancelled/expired warnings or watches, meaningful hazard timing shifts, threshold crossings for precipitation/snow/ice/temperature/wind, meaningful travel/agriculture/outdoor impact changes, or substantial confidence changes.

Production must be idempotent, lock against overlapping runs, retain the last-known-good publication, log failures, and expose last success/next run/failure state in Operations.

## 28 branded products
The canonical product library contains 28 Region 9 branded forecast/decision-support products across Daily Core, Hazards, Temperature/Health, Agriculture, Travel/Outdoor, Rain/Drought/Water and Specialty categories. Each is a deterministic branded template populated from structured forecast state, with valid time, generated time, source/QC metadata, accessible alt text, archive history and publication state.

The library includes the established products such as Morning Weather Brief, Today's Forecast, Seven-Day Forecast, Evening Weather Update, Weekly Weather Hazards, Severe Weather Outlook, Storm Timing, Threat Breakdown, Watch/Warning Explainer, heat/cold/frost products, Agriculture, Spray Window, Fieldwork, Livestock, Rural Travel, Commute, Outdoor Event Planner, Lightning Risk, rainfall/drought products, Storm Anxiety Outlook, What We're Watching, Forecast Confidence Meter and Decision Support Brief.

## Homepage/public hub
The homepage must immediately answer: what is happening, what is the Region 9 risk, where, when, and what should residents do?

Restore/preserve: live Region 9 alert bar; Latest Weather Update crawl; global Region 9 risk; Decision Impact Dashboard; nine-city Current Conditions; primary forecast graphic; threat matrix when applicable; interactive county alert map; radar; seven-day outlook; weather discussion/latest update; power outage iframe; agriculture/rural/travel/outdoor decision support; graphics gallery; county/city links; signup/report links; freshness timestamp; and accessible stale/error states.

## Alert scopes
LIVE ALERTS are strictly limited to alerts intersecting the canonical nine Region 9 counties.

LATEST WEATHER UPDATE CRAWL includes active alerts intersecting Region 9 plus active alerts whose affected geometry is within 50 miles of the Region 9 boundary. An alert outside that buffer is excluded. Deduplicate by stable NWS alert identifier.

Automated tests must prove: inside Region 9 => Live Alerts + crawl; outside Region 9 but <=50 miles => crawl only; >50 miles => neither.

## Interactive GIS map
Restore the interactive county map with authoritative published county GIS boundaries. Highlight watches, warnings, advisories, statements and other supported NWS alerts. Prefer official alert polygon geometry; where absent, fall back only to authoritative county/zone geometry. Never hand-draw or approximate county boundaries.

Map requirements: individual Region 9 county interaction, event/severity labels, legend, issued/expires timestamps, mobile usability, keyboard/accessibility support, text fallback, stale/error state and safe rendering when alert geometry is missing.

## Power outage widget
Restore the established public power-outage iframe. Keep the embed source allowlisted/configurable, responsive, appropriately lazy-loaded, and provide a visible fallback link/message when embedding fails or is blocked.

## Publication/QC
Routine deterministic products may auto-publish after validation. Elevated/Significant workflows must support configurable review or emergency auto-publish policy. QC validates geography, risk wording, timing, forecast facts, template completeness, source freshness and public-facing language before publication.

## Archive and rollback
Every publication stores a versioned record sufficient to show history and restore the last-known-good public product. City forecasts, graphics and forecast updates must expose appropriate archive views without leaking internal/private workspace data.

## Operations/Admin
Provide production health for source freshness, cron/queue health, last and next cycle, material-change trigger, product generation status, publication failures, cache state, GIS/map status, alert scope counts, outage embed health and last-known-good state. Admin actions must use WordPress capabilities, nonces, sanitization and allowlisted writes.

## Acceptance gate
Do not promote to stable until automated validation and staging verification cover: nine-county geography, 50-mile crawl buffer, five-level risk logic, six-hour scheduling, material-change triggers, all 28 products, homepage modules, GIS map, alert geometry fallback, outage iframe, radar, Current Conditions, city/county pages, archives, responsive/mobile layouts, accessibility, logged-out behavior, caching, stale data, provider failure and last-known-good rollback.