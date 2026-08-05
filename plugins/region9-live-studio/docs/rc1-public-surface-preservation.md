# RC1 Public Surface Preservation

Region 9 Live Studio v17 RC1 must preserve the public Region 9 Weather visitor experience while replacing backend processing with canonical Live Studio publication, validation, and approval systems.

## Guardrails

The RC1 certification suite treats the current GeneratePress child theme surface as launch-critical unless a later staging step provides the authoritative v15/v15.1 reference ZIPs for deeper comparison.

The public surface must preserve:

- GeneratePress child-theme declaration and GP Premium compatibility.
- Region 9 Weather branding, logo placement, header, footer, and navigation hooks.
- Primary and footer menu registrations.
- Existing widget/sidebar areas.
- Existing page inventory and URL slugs wherever possible.
- Homepage template structure.
- Forecast page media/discussion fallback layout.
- Sidebar layout on forecast pages.
- Theme Customizer controls and legacy theme admin utilities when RC1 is inactive.
- RC1 admin routing only when the Live Studio RC1 plugin is active.

## Certification command

Run:

```bash
php scripts/validate-rc1-public-surface.php
```

The script writes `build/rc1-public-surface-report.json` and is included in the RC1 GitHub Actions workflow. It is intentionally static: it verifies preservation guardrails without redesigning the theme or changing public rendering behavior.

## Reference baseline limitation

The v15 plugin and v15.1 GeneratePress child-theme ZIPs are not present in this checkout. When those artifacts are available in staging, compare page-by-page screenshots and menu/widget inventories against this static guardrail report before final launch sign-off.
