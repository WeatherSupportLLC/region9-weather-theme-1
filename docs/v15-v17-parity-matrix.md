# v15/v16 to v17 RC1 Parity Matrix

## Baseline availability

The authoritative v15 plugin and v15.1 GeneratePress child-theme ZIPs are not present in this checkout, and no local tags, release branches, or remotes are available. This matrix therefore inventories the current repository implementation and marks features that require direct v15/v15.1 verification as `Needs Reference Comparison` rather than inventing missing behavior.

| Feature | Category | Present in v15 | Present in RC1 | Status | Preserved | Backend Replaced | Needs Restoration | Deprecated | Reason | Owner | Files Responsible |
|---|---|---:|---:|---|---:|---:|---:|---:|---|---|---|
| GeneratePress child theme | Theme | Unknown | Yes | Preserved | Yes | No | No | No | `Template: generatepress` remains in `style.css`. | Theme | `style.css` |
| Region 9 Weather branding | Public | Unknown | Yes | Preserved | Yes | No | No | No | Header, logo, footer, and Region 9 Weather copy remain. | Theme | `header.php`, `footer.php`, `style.css` |
| Homepage | Public page | Unknown | Yes | Preserved | Yes | No | No | No | Front page loads the studio home template. | Theme | `front-page.php`, `template-parts-studio-home.php` |
| Primary navigation | Navigation | Unknown | Yes | Preserved | Yes | No | No | No | `r9_studio_menu` is registered and rendered in the header. | Theme | `functions.php`, `header.php` |
| Footer navigation | Navigation | Unknown | Yes | Preserved | Yes | No | No | No | `r9_footer_menu` remains registered. | Theme | `functions.php`, `footer.php` |
| Forecast page layout | Public page | Unknown | Yes | Preserved | Yes | Partial | No | No | Existing media/discussion fallback remains; canonical products may fill content when approved. | Theme/Plugin | `page.php`, `inc/live-studio-integration.php` |
| Forecast graphic placeholders | Public page | Unknown | Yes | Preserved | Yes | No | No | No | `r9_media_placeholder()` remains available for forecast-style pages. | Theme | `functions.php`, `page.php` |
| Forecast discussion placeholders | Public page | Unknown | Yes | Preserved | Yes | No | No | No | Page fallback still displays Forecast Discussion panels. | Theme | `page.php` |
| Alert Center | Public feature | Unknown | Yes | Preserved | Yes | Partial | No | No | Theme alert center and plugin NWS alert adapter both exist. | Theme/Plugin | `functions.php`, `assets/js/studio.js`, `class-national-guidance.php` |
| Radar | Public feature | Unknown | Yes | Preserved | Yes | No | No | No | Radar page fallback embeds configured WeatherFront radar URL. | Theme | `page.php`, `inc/customizer.php` |
| Current Conditions | Public feature | Unknown | Yes | Preserved | Yes | No | No | No | Theme REST/current-condition JavaScript remains. | Theme | `functions.php`, `assets/js/studio.js` |
| Breaking News | Public feature | Unknown | Yes | Preserved | Yes | No | No | No | Header breaking-news bar remains driven by Customizer settings. | Theme | `header.php`, `inc/customizer.php` |
| Outage Tracker | Public feature | Unknown | Restored placeholder | Intentionally Deferred | Yes | No | Deferred | No | Public page and shortcode placeholder restored; live feed behavior deferred until verified v15/v16 baseline or approved outage source is available. | Theme | `functions.php`, `page.php` |
| Partners | Public/admin feature | Unknown | Restored entry point | Intentionally Deferred | Yes | No | Deferred | No | Public page/admin entry restored; data model/workflow deferred pending v15/v15.1 artifact. | Theme | `functions.php`, `inc/admin-studio.php` |
| Clients | Public/admin feature | Unknown | Restored entry point | Intentionally Deferred | Yes | No | Deferred | No | Public page/admin entry restored; data model/workflow deferred pending v15/v15.1 artifact. | Theme | `functions.php`, `inc/admin-studio.php` |
| Production | Admin/ops | Unknown | Partial | Backend Replaced | Partial | Yes | No | No | Forecast production workspace exists in RC1 plugin admin. | Plugin | `class-admin.php`, `class-product-generator.php` |
| Rural Operations | Admin/navigation | Unknown | Restored entry point | Intentionally Deferred | Yes | No | Deferred | No | Public/admin entry points restored; rural workflow details deferred until verified baseline is available. | Theme | `functions.php`, `inc/admin-studio.php` |
| Agriculture | Forecast page/product | Unknown | Yes | Preserved | Yes | Yes | No | No | Page slug and canonical products exist. | Theme/Plugin | `functions.php`, `class-product-generator.php` |
| Travel | Forecast page/product | Unknown | Yes | Preserved | Yes | Yes | No | No | Page slug and canonical products exist. | Theme/Plugin | `functions.php`, `class-product-generator.php` |
| Outdoor | Forecast product | Unknown | Yes | Backend Replaced | Partial | Yes | No | No | Canonical outdoor product exists under Travel & Outdoor mapping. | Plugin | `class-product-generator.php`, `inc/live-studio-integration.php` |
| Schools | Forecast product | Unknown | Yes | Backend Replaced | Partial | Yes | No | No | Canonical schools product exists under Travel & Outdoor mapping. | Plugin | `class-product-generator.php` |
| Construction | Forecast product | Unknown | Yes | Backend Replaced | Partial | Yes | No | No | Canonical construction product exists under Travel & Outdoor mapping. | Plugin | `class-product-generator.php` |
| Temperature Outlook | Public page/product | Unknown | Yes | Preserved | Yes | Yes | No | No | Page slug maps to canonical products. | Theme/Plugin | `functions.php`, `inc/live-studio-integration.php` |
| Precipitation Outlook | Public page/product | Unknown | Yes | Preserved | Yes | Yes | No | No | Page slug maps to canonical products. | Theme/Plugin | `functions.php`, `inc/live-studio-integration.php` |
| Forecast Confidence | Product | Unknown | Yes | Backend Replaced | Partial | Yes | No | No | Canonical product exists. | Plugin | `class-product-generator.php` |
| Morning Brief | Product | Unknown | Yes | Backend Replaced | Partial | Yes | No | No | Canonical product exists. | Plugin | `class-product-generator.php` |
| Decision Support | Product | Unknown | Yes | Backend Replaced | Partial | Yes | No | No | Decision support product and special page mapping exist. | Plugin/Theme | `class-product-generator.php`, `inc/live-studio-integration.php` |
| Watching | Product | Unknown | Yes | Backend Replaced | Partial | Yes | No | No | Canonical “What We’re Watching” product exists. | Plugin | `class-product-generator.php` |
| Approval Queue | Admin | Unknown | Yes | Backend Replaced | No | Yes | No | No | Pending material changes table supports approve/reject/publish/rollback. | Plugin | `class-admin.php`, `class-material-change-engine.php` |
| Publication History | Admin/API | Unknown | Yes | Backend Replaced | No | Yes | No | No | Product history is stored and exposed in REST history. | Plugin | `class-product-generator.php`, `class-rest-api.php` |
| Audit Log | Admin | Unknown | Yes | Backend Replaced | No | Yes | No | No | Audit log renders in admin workspace. | Plugin | `class-admin.php`, `class-audit-log.php` |
| Scheduler | Admin/backend | Unknown | Yes | Backend Replaced | No | Yes | No | No | Scheduler, cron, lock, next validation, and soak coverage exist. | Plugin | `class-scheduler.php`, `rc1-scheduler-soak.php` |
| Source Health | Admin/backend | Unknown | Yes | Backend Replaced | No | Yes | No | No | Admin reports NWS/SPC/WPC health states. | Plugin | `class-admin.php`, `class-national-guidance.php` |
| Theme Site Setup | Admin | Unknown | Yes | Preserved | Yes | No | No | No | Legacy setup remains, routed under RC1 plugin menu when active. | Theme | `inc/admin-studio.php` |
| Theme System Health | Admin | Unknown | Yes | Preserved | Yes | No | No | No | Legacy health page remains, routed under RC1 plugin menu when active. | Theme | `inc/admin-studio.php` |
| Theme Backup | Admin | Unknown | Yes | Preserved | Yes | No | No | No | Backup/restore remains in theme admin. | Theme | `inc/admin-studio.php` |
| Theme Live Controls | Admin | Unknown | Yes | Preserved | Yes | No | No | No | Customizer link remains in legacy/RC1-routed admin menus. | Theme | `inc/admin-studio.php`, `inc/customizer.php` |
