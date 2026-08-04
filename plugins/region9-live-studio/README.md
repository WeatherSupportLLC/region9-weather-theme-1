# Region 9 Live Studio 17 Alpha 3

Region 9 Live Studio 17 is a WordPress plugin for live weather ingestion, decision scoring, automation review, publishing workflow, editor overrides, and audit history.

## Requirements

- WordPress 6.3 or newer.
- PHP 8.1 or newer.
- Outbound HTTPS access to weather.gov, spc.noaa.gov, and wpc.ncep.noaa.gov.

## Installation

1. Build the plugin ZIP from the repository root:
   ```bash
   scripts/build-region9-live-studio.sh
   ```
2. Upload `dist/region9-live-studio.zip` in WordPress Admin > Plugins > Add New > Upload Plugin.
3. Activate **Region 9 Live Studio 17**.
4. Set `r9ls_contact_email` to the operations contact address used in the NWS User-Agent header, or allow activation to seed it from the site admin email.

## Region 9 counties

The county matrix is initialized with exactly: Kankakee, Iroquois, Ford, Livingston, DeWitt, Piatt, Champaign, Vermilion, McLean.

## Data sources

- NWS Alerts API: active Illinois alerts.
- NWS Points API: point metadata and hourly forecast discovery.
- NWS Hourly Forecast API: hourly weather periods from the Points response.
- SPC Day 1 categorical outlook GeoJSON.
- WPC Day 1 Excessive Rainfall Outlook GeoJSON.

All external requests use `wp_remote_get`, a timeout, a Region 9 Weather User-Agent with configured contact address, HTTP status validation, JSON validation, retry handling, and successful-response caching. Source health separates healthy zero-hazard responses from unavailable and stale cached sources.

## Decision outputs

The Decision Engine evaluates Travel, Agriculture, Fieldwork, Livestock, Construction, Outdoor, Schools, Forecast Confidence, Utilities, and Emergency Operations. Each decision includes `score`, `rating`, `confidence`, `primary_drivers`, `secondary_drivers`, and `summary`.

## REST API

Base namespace: `/wp-json/region9-live-studio/v1`

- `GET /weather?lat=40.6331&lon=-89.3985` collects live weather source payloads and health.
- `GET /decisions` returns decision-engine scoring for all Alpha 3 categories.
- `GET /automation` returns source health, validation results, county matrix, decision history, pending changes, overrides, and audit log.
- `PUT/PATCH /pending` stores pending changes for review.
- `POST /automation/approve` approves validation results without publishing.
- `POST /automation/reject` rejects and clears pending changes.
- `POST /automation/publish` publishes only after explicit approval.
- `POST /automation/rollback` restores the last published change set to pending state.
- `PUT/PATCH /overrides` saves editor overrides with automatic expiration.

## Security behavior

Public read routes expose weather and decisions. Automation, pending-change, publishing, rollback, and override routes require an authenticated administrator (`manage_options`). Cookie-authenticated REST requests are protected by WordPress REST nonce validation through the standard `X-WP-Nonce` flow; application-password or other authenticated REST flows must map to an administrator account.

## Uninstall behavior

Operational data is retained by default on uninstall. Define `R9LS_DELETE_DATA_ON_UNINSTALL` as `true` before uninstalling only when intentional data deletion is required.

## Known Alpha 3 limitations

- Spatial intersection of SPC/WPC GeoJSON polygons with county boundaries is not yet implemented.
- Decision thresholds are first-pass operational heuristics and need field calibration.
- Admin UI screens are module placeholders; the current control surface is REST-first.
- Health monitoring runs when sources are collected or the refresh hook is invoked; no recurring scheduler is installed yet.
