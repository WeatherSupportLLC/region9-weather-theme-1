# Theme Audit

| Item | Status | Files | Notes |
|---|---|---|---|
| GeneratePress child theme | Present | `style.css` | `Template: generatepress`. |
| Theme name | Present | `style.css` | Region 9 Weather Studio - GeneratePress Child. |
| Theme version constants | Present | `functions.php` | `R9_STUDIO_VERSION`, `R9WS_VERSION`, `R9WS_THEME_VERSION`. |
| Theme folder | Present | packaging script | ZIP uses current folder slug by default. |
| GP Premium compatibility | Preserved by child-theme architecture | `style.css`, `functions.php` | No parent replacement introduced. |
| Customizer | Present | `inc/customizer.php`, `inc/admin-studio.php` | Live Controls link routes to `r9_studio` customizer section. |
| Menus | Present | `functions.php`, `header.php` | Primary/footer registered; fallback menu exists. |
| Widgets/sidebars | Present | `functions.php`, `page.php` | Five widget areas registered. |
| CSS | Present | `style.css`, `assets/css/*` | Existing Region 9 styling retained; Live Studio CSS is additive. |
| Templates | Present | `front-page.php`, `page.php`, `header.php`, `footer.php`, `template-parts-studio-home.php` | Public layout remains theme-driven. |
| JavaScript | Present | `assets/js/studio.js`, `assets/js/v52.js` | No MutationObserver found by validator. |
| Reference comparison | Blocked | N/A | v15/v15.1 artifacts absent from checkout. |
