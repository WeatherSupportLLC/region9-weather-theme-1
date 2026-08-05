# Image Placeholder Inventory

| Page / Product Area | Graphic Placeholder | Featured Image | Fallback Placeholder | Aspect Ratio | Responsive Behavior | Discussion Panel | Files |
|---|---|---|---|---|---|---|---|
| Forecast/Daily | `r9_media_placeholder()` fallback | Page/editor supported by WordPress but not forced | Yes | CSS-driven media placeholder | Responsive via `.r9-content-pair` and media CSS | Yes | `page.php`, `functions.php`, `style.css` |
| Severe Weather | `r9_media_placeholder('Severe Weather Graphic or Photo')` | Optional | Yes | CSS-driven | Responsive content pair | Yes | `page.php` |
| Hazards | Product card graphic or fallback media/discussion | Optional | Yes | CSS-driven | Responsive grid/content pair | Yes | `page.php`, `inc/live-studio-integration.php` |
| Temperature Outlook | Product card graphic or fallback | Optional | Yes | CSS-driven | Responsive | Yes | `page.php` |
| Precipitation Outlook | Product card graphic or fallback | Optional | Yes | CSS-driven | Responsive | Yes | `page.php` |
| Travel & Outdoor | Product card graphic or fallback | Optional | Yes | CSS-driven | Responsive | Yes | `page.php` |
| Agriculture | Product card graphic or fallback | Optional | Yes | CSS-driven | Responsive | Yes | `page.php` |
| Special Briefs | Product card graphic or fallback | Optional | Yes | CSS-driven | Responsive | Yes | `page.php` |
| About | Portrait placeholder | Optional page media | Yes | Portrait card CSS | Responsive about layout | Bio discussion panel | `page.php` |
| Radar | Radar iframe, no graphic placeholder | N/A | N/A | iframe height CSS | Responsive iframe | N/A | `page.php`, `style.css` |
| Alert Center | Alert cards/detail panels, no graphic placeholder | N/A | Loading/fallback alert card | Card CSS | Responsive alert cards | Official bulletin detail | `functions.php`, `assets/js/studio.js`, `page.php` |


## RC1 Step 4 completion note

Public forecast and operational pages now use polished Region 9 branded empty states, graphic placeholders, discussion panels, status pills, and responsive sidebar fallbacks so unavailable optional data does not create unfinished pages. See `docs/public-surface-completion.md`.
