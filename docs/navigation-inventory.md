# Navigation Inventory

## Public menus

| Menu | Location/Slug | Present | Files | Ordering / Items |
|---|---|---:|---|---|
| Primary menu | `r9_studio_menu` | Yes | `functions.php`, `header.php`, `inc/admin-studio.php` | Setup order: Home, About, Severe Weather, Hazards, Temperature, Precipitation, Travel, Agriculture, Anxiety, Radar. Severe Weather submenu: Alert Center, Storm Timing, Threat Breakdown, Watches & Warnings. |
| Footer menu | `r9_footer_menu` | Yes | `functions.php` | Registered; footer markup currently uses hard-coded links. |
| Fallback menu | `r9_menu_fallback()` | Yes | `functions.php`, `header.php` | Home, About, Severe Weather, Hazards, Temperature, Precipitation, Travel, Agriculture, Anxiety, Radar. |
| Secondary menu | Unknown | No explicit current registration | N/A | Requires v15/v15.1 reference comparison. |
| Utility menu | Unknown | No explicit current registration | N/A | Requires v15/v15.1 reference comparison. |

## Admin menu / legacy redirects

See `docs/admin-menu-inventory.md` for admin menu slugs, capabilities, and RC1 routing.
