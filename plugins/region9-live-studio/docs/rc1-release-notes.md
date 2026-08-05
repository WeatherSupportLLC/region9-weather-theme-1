# Region 9 Live Studio 17 RC1 Release Notes

## Highlights

* Promotes the production hardening checklist for staging validation, operational scenario coverage, publication workflow review, accessibility, security, compatibility, performance, packaging, and rollback readiness.
* Keeps automatic publishing disabled by default; every public content change still requires approval and publication workflow validation.
* Documents the production requirement for official U.S. Census Bureau TIGER/Line county geometry and launch-time checksum evidence.

## Upgrade notes

* Upgrade from Alpha 8 should preserve existing `r9ls_*` options, approved product state, immutable history, rollback references, and editor overrides.
* After upgrade, run one manual validation and confirm no duplicate scheduled event exists.

## Known launch gates

* Complete 24- to 48-hour scheduler soak testing on staging.
* Complete operational source-failure and severe/flooding scenarios.
* Attach ZIP checksum and launch checklist evidence before production deployment.
