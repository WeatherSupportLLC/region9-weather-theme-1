# Public Page Inventory

Baseline v15/v15.1 artifacts are unavailable in this checkout. This inventory covers the current RC1 repository and identifies canonical products or REST dependencies where present.

| Title | Slug | Template | Sidebar | Widgets | Forecast Layout | Graphic Placeholder | Discussion Placeholder | Shortcodes | REST Dependencies | Canonical Products |
|---|---|---|---|---|---|---|---|---|---|---|
| Home | `/` | `front-page.php` + `template-parts-studio-home.php` | Theme home sections | Theme panels | Studio homepage cards/sections | Media placeholders in template/theme helpers | Template copy blocks | `[region9_studio_home]` available | `r9/v2/conditions`, `r9/v2/alerts`, `r9/v4/status` | `severe-weather-risk`, `travel`, `agriculture`, mapped home values |
| Forecast | `/daily/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar widgets | Product grid if approved, fallback media/discussion pair | Yes | Yes | None required | Public product helpers/REST | `morning-brief`, `todays-forecast`, `seven-day-forecast`, `headlines` |
| About | `/about/` | `page.php` | `r9-forecast-sidebar` fallback coverage | Forecast sidebar | About portrait/bio layout | Portrait placeholder | Bio content fallback | None | None | None |
| Severe Weather | `/severe-weather/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Severe hub + media/discussion | Yes | Yes | None | Alert Center links | `severe-weather-risk`, `threat-breakdown`, `storm-timing` |
| Hazards | `/hazards/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Product grid/fallback | Yes | Yes | None | Public product helpers/REST | `severe-weather-risk`, `threat-breakdown`, `storm-timing` |
| Temperature | `/temperature-outlook/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Product grid/fallback | Yes | Yes | None | Public product helpers/REST | `todays-forecast`, `forecast-confidence` |
| Precipitation | `/precipitation-outlook/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Product grid/fallback | Yes | Yes | None | Public product helpers/REST | `todays-forecast`, `decision-support-brief` |
| Travel | `/travel-outdoor/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Product grid/fallback | Yes | Yes | None | Public product helpers/REST | `travel`, `outdoor`, `schools`, `construction` |
| Agriculture | `/agriculture/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Product grid/fallback | Yes | Yes | None | Public product helpers/REST | `agriculture`, `fieldwork`, `spraying`, `harvest`, `livestock` |
| Anxiety | `/anxiety/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Fallback media/discussion unless mapped later | Yes | Yes | None | None | None currently mapped |
| Radar | `/radar/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Radar iframe | Not applicable | Not applicable | None | WeatherFront iframe URL setting | None |
| Alerts | `/alerts/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Alert Center shell/detail view | Not applicable | Official bulletin/detail panels | `[region9_alert_center]` | `r9/v2/alerts`, `r9/v2/alert-detail` | NWS alerts adapter |
| Storm Timing | `/storm-timing/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Product grid/fallback | Yes | Yes | Setup placeholder content | Public product helpers/REST | `storm-timing` |
| Threat Breakdown | `/threat-breakdown/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Product grid/fallback | Yes | Yes | Setup placeholder content | Public product helpers/REST | `threat-breakdown` |
| Watches & Warnings | `/watches-warnings/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Fallback media/discussion | Yes | Yes | Setup placeholder content | Alert Center links | NWS alerts adapter |
| Special Briefs | `/special/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Product grid/fallback | Yes | Yes | None | Public product helpers/REST | `forecast-confidence`, `decision-support-brief`, `watching` |
| Contact | `/contact/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Fallback content layout | Yes | Yes | None | None | None currently mapped |
| City Forecast | `/city-forecast/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | City forecast shell | Not applicable | NWS forecast detail | None | `r9/v2/city-forecast` | NWS grid forecast |

| Outage Tracker | `/outage-tracker/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Preserved public placeholder | Not applicable | Professional placeholder | `[region9_outage_tracker]` | None; no render-time HTTP | Deferred pending verified outage source |
| Partners | `/partners/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Preserved content placeholder | Yes | Yes | None | None | Deferred pending v15/v15.1 baseline |
| Clients | `/clients/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Preserved content placeholder | Yes | Yes | None | None | Deferred pending v15/v15.1 baseline |
| Production | `/production/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Preserved content placeholder | Yes | Yes | None | None | RC1 admin workspace authoritative |
| Rural Operations | `/rural-operations/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Preserved content placeholder | Yes | Yes | None | None | Deferred pending v15/v15.1 baseline |
| Rural Reports | `/rural-reports/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Preserved content placeholder | Yes | Yes | None | None | Deferred pending v15/v15.1 baseline |
| Protection | `/protection/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Preserved content placeholder | Yes | Yes | None | None | Deferred pending v15/v15.1 baseline |
| Backup | `/backup/` | `page.php` | `r9-forecast-sidebar` | Forecast sidebar | Preserved content placeholder | Yes | Yes | None | None | Theme backup admin remains authoritative |


## RC1 Step 4 completion note

Public forecast and operational pages now use polished Region 9 branded empty states, graphic placeholders, discussion panels, status pills, and responsive sidebar fallbacks so unavailable optional data does not create unfinished pages. See `docs/public-surface-completion.md`.
