# Region 9 Live Studio 17 Alpha 8 Production Website Integration

## Theme integration audit
The theme previously rendered homepage risk, impacts, forecast graphics, and page discussion boxes from theme settings or editor placeholder content. Public pages covered Daily, Hazards, Temperature Outlook, Agriculture, Travel/Outdoor, Precipitation Outlook, Special, and severe-weather support pages. Existing shortcodes included `[region9_studio_home]`, `[region9_alert_center]`, plugin `[r9ls_product]`, `[r9ls_county_matrix]`, and Alpha 8 `[r9ls_public_product]`. Existing REST calls for current conditions, city forecasts, alerts, alert detail, and status are preserved for their existing systems; canonical publication rendering uses PHP helpers instead of page-level weather scoring.

## Product-to-page map
- Daily: `morning-brief`, `todays-forecast`, `seven-day-forecast`, `headlines`.
- Hazards: `severe-weather-risk`, `threat-breakdown`, `storm-timing`.
- Agriculture: `agriculture`, `fieldwork`, `spraying`, `harvest`, `livestock`.
- Travel/Outdoor: `travel`, `outdoor`, `schools`, `construction`.
- Special: `forecast-confidence`, `decision-support-brief`, `watching`.
- Temperature Outlook: `todays-forecast`, `forecast-confidence` until a dedicated Alpha product is approved.
- Precipitation Outlook: `todays-forecast`, `decision-support-brief` until a dedicated Alpha product is approved.

## Canonical helper and shortcode reference
Theme rendering enters through `inc/live-studio-integration.php`. The canonical access path is `r9ls_theme_products()` / `r9ls_theme_product()`, which reads only approved and published product payloads exposed by plugin helper `r9ls_get_public_products()`. Product cards are rendered by `r9ls_theme_card()` or shortcode `[r9ls_public_product id="travel"]`.

## Configuration reference
Administrator settings include NWS contact email, normal and active validation intervals, source timeouts, cache duration, stale-data threshold, confidence threshold, material-change threshold, automatic publishing, required healthy sources, enabled products, fallback language, display options, and stale banner behavior. Automatic publication remains disabled by default.

## Health dashboard guide
The Studio admin page reports NWS Alerts, NWS Points/Grid/Hourly, SPC, WPC ERO, WPC QPF, scheduler status, last and next validation, generation status, publication version, pending changes, active overrides, cache age, stale sources, recent errors, PHP, WordPress, plugin version, and theme integration status using Healthy, Stale, Degraded, or Unavailable labels where source data provides them.

## County geometry source and license
Alpha 8 stores local county geometry for Kankakee, Iroquois, Ford, Livingston, DeWitt, Piatt, Champaign, Vermilion, and McLean. Source attribution is U.S. Census Bureau TIGER/Line county boundary data (public domain under Census data use policy). The local fixture records GEOID, state, and county identifiers. Geometry supports Polygon and MultiPolygon. The Alpha 8 validation fixture uses rounded county envelopes with 0.001-degree tolerance for server-side intersection tests; no runtime geometry download occurs.

## Caching behavior
Published product payloads are cached as `r9ls_public_product_all` and targeted per-product keys under `r9ls_public_product_`. Generation invalidates changed products and the aggregate cache only. Public rendering does not run validation, ingestion, scheduling, publication, or direct NWS/SPC/WPC calls, and performs no public database writes during ordinary rendering.

## Upgrade, staging, and rollback
1. Install the Alpha 8 plugin ZIP and updated theme ZIP on staging.
2. Activate the plugin and verify the Studio health dashboard.
3. Confirm public pages show approved products or unavailable fallbacks.
4. Keep automatic publication disabled until operational approval.
5. Roll back by restoring the previous plugin/theme ZIPs or publishing a rollback product version from Studio; rollback creates a new public version and does not expose pending changes.

## Known limitations
Temperature and precipitation pages are mapped to existing approved forecast/support products until dedicated canonical products are added. Existing current conditions, radar, alert crawl, outage tracker, and breaking-news systems are preserved and may still use their established endpoints outside canonical product rendering.
