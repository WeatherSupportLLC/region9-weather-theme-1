# Sidebar and Widget Inventory

| Widget Area ID | Name | Registered | Conditional Use | Primary Files | Notes / Missing Widgets |
|---|---|---:|---|---|---|
| `r9-live-sidebar` | Live Studio Sidebar | Yes | Available to theme widgets | `functions.php` | No default widget instances are created in code. |
| `r9-forecast-sidebar` | Forecast Page Sidebar | Yes | Used by `page.php`; fallback coverage panel if inactive | `functions.php`, `page.php` | Required for forecast-style pages. |
| `r9-alert-sidebar` | Alert & Safety Sidebar | Yes | Available to alert/safety pages | `functions.php` | No explicit conditional render found in current page template. |
| `r9-footer-one` | Footer Column One | Yes | Footer widget area registration | `functions.php` | Footer currently uses hard-coded footer columns, not dynamic sidebars. |
| `r9-footer-two` | Footer Column Two | Yes | Footer widget area registration | `functions.php` | Footer currently uses hard-coded footer columns, not dynamic sidebars. |

## Homepage widgets

The homepage currently uses template panels and helper-rendered sections rather than registered widget areas. Verify against v15/v15.1 when reference artifacts are available.

## Missing-widget follow-up

No v15/v15.1 baseline is available to determine whether partner/client/outage tracker widgets existed and need restoration.
