# Region 9 Live Studio 17 RC1 Rollback Procedure

1. Freeze publication approvals and notify operations that rollback is in progress.
2. Export current `r9ls_*` options and copy the current plugin ZIP for evidence.
3. Deactivate Region 9 Live Studio from WordPress admin or WP-CLI.
4. Restore the previously approved plugin ZIP and verify the version shown in WordPress.
5. Reactivate the previous version and confirm only one scheduler event exists.
6. Restore the last known-good approved publication state if the incident involved generated content.
7. Clear public product transients and page caches.
8. Verify public products, county matrix, REST output, and homepage widgets render from the last known-good state.
9. Record incident start/end time, root cause, restored version, restored publication version, and follow-up owner.
