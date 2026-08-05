# Admin Menu Inventory

| Page / Tool | Menu Slug | Parent | Capability | Present | Files | Notes |
|---|---|---|---|---:|---|---|
| Region 9 Studio Automation | `r9ls` | Top-level plugin menu | `manage_options` | Yes | `class-admin.php` | Main RC1 dashboard/workspace. |
| Run Manual Validation | `admin-post.php?action=r9ls_validate` | Form action | `manage_options` + nonce | Yes | `class-admin.php` | Manual source validation. |
| Settings | `admin-post.php?action=r9ls_settings` | Form action | `manage_options` + nonce | Yes | `class-admin.php` | RC1 source/publication settings. |
| Approval Queue / Pending Material Changes | `r9ls` section | Plugin dashboard | `manage_options` | Yes | `class-admin.php`, `class-material-change-engine.php` | Approve/reject/publish/rollback buttons. |
| Forecast Production Workspace | `r9ls` section | Plugin dashboard | `manage_options` | Yes | `class-admin.php`, `class-product-generator.php` | Reads generated workspace when available. |
| Decision History | `r9ls` section | Plugin dashboard | `manage_options` | Yes | `class-admin.php`, `class-material-change-engine.php` | Decision history JSON. |
| Publication History | REST/history + product history | Plugin/API | public read filtered | Yes | `class-product-generator.php`, `class-rest-api.php` | Public history summaries; admin product history stored. |
| Audit Log | `r9ls` section | Plugin dashboard | `manage_options` | Yes | `class-admin.php`, `class-audit-log.php` | Audit log JSON. |
| Source Health | `r9ls` status rows | Plugin dashboard | `manage_options` | Yes | `class-admin.php`, `class-national-guidance.php` | NWS, SPC, WPC rows. |
| Scheduler | `r9ls` status rows | Plugin dashboard | `manage_options` | Yes | `class-admin.php`, `class-scheduler.php` | Last/next validation, duration. |
| Temporary Overrides | `admin-post.php?action=r9ls_override` | Plugin dashboard | `manage_options` + nonce | Yes | `class-admin.php` | Save override form. |
| Theme Site Setup | `r9-studio-setup` | `r9-studio` or `r9ls` when RC1 active | `manage_options` | Yes | `inc/admin-studio.php` | Builds/repairs pages and menu. |
| Theme System Health | `r9-studio-health` | `r9-studio` or `r9ls` when RC1 active | `manage_options` | Yes | `inc/admin-studio.php` | Legacy service health. |
| Theme Backup & Restore | `r9-studio-backup` | `r9-studio` or `r9ls` when RC1 active | `manage_options` | Yes | `inc/admin-studio.php` | Export/import theme settings. |
| Theme Live Controls | `customize.php?autofocus[section]=r9_studio` | `r9-studio` or `r9ls` when RC1 active | `manage_options` | Yes | `inc/admin-studio.php`, `inc/customizer.php` | Customizer controls. |
| Legacy dashboard | `r9-studio` | Top-level theme menu when RC1 inactive | `manage_options` | Yes | `inc/admin-studio.php` | Removed/redirected when RC1 active. |
| Partners | `r9-studio-partners` | `r9-studio` or `r9ls` when RC1 active | `manage_options` | Restored entry point | `inc/admin-studio.php` | Workflow deferred pending v15/v15.1 artifact. |
| Clients | `r9-studio-clients` | `r9-studio` or `r9ls` when RC1 active | `manage_options` | Restored entry point | `inc/admin-studio.php` | Workflow deferred pending v15/v15.1 artifact. |
| Production / Rural Reports / Rural Operations | `r9-studio-production`, `r9-studio-rural-reports`, `r9-studio-rural-operations` | `r9-studio` or `r9ls` when RC1 active | `manage_options` | Restored entry points | `inc/admin-studio.php` | Detailed workflow deferred pending v15/v15.1 artifact. |
| Protection | `r9-studio-protection` | `r9-studio` or `r9ls` when RC1 active | `manage_options` | Restored entry point | `inc/admin-studio.php` | Workflow deferred pending v15/v15.1 artifact. |
