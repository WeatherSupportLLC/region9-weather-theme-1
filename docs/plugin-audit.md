# Plugin Audit

| Area | Status | Files | Notes |
|---|---|---|---|
| REST endpoints | Present | `class-rest-api.php`, `region9-live-studio.php` | Public products/history endpoints filter private fields. |
| Scheduler/Cron | Present | `class-scheduler.php` | Activation schedules validation; next validation self-heals missing event. |
| Source adapters | Present | `class-national-guidance.php` | SPC, WPC ERO, WPC QPF, NWS alerts, NWS grid/hourly. |
| Publication engine | Present | `class-product-generator.php`, `class-material-change-engine.php` | Approved state generates products; publishing requires approval. |
| Approval engine | Present | `class-material-change-engine.php`, `class-admin.php` | Queue, decide, publish, rollback actions. |
| Forecast workspace | Present | `class-product-generator.php`, `class-admin.php` | `WORKSPACE` option populated on generation. |
| History | Present | `class-product-generator.php`, `class-material-change-engine.php` | Product history and decision history stored. |
| Caching | Present | `class-rest-api.php`, `class-product-generator.php`, `class-national-guidance.php` | Public product and guidance transients. |
| Risk engine | Present | `class-rule-engine.php` | Deterministic score/risk/confidence evaluation. |
| County engine | Present | `class-gis-engine.php`, `data/region9-counties.geojson` | TIGER/Line-based simplified local geometry. |
| Alert engine | Present | `class-national-guidance.php`, theme alert REST | Plugin adapter and legacy public Alert Center both exist. |
| Admin dashboard | Present | `class-admin.php` | Operational status, cards, county matrix, queue, overrides, workspace, logs, settings. |
| Reference comparison | Blocked | N/A | v15 plugin artifact absent from checkout. |
