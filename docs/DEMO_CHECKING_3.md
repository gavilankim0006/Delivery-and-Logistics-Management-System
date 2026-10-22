# Checking 3 Demonstration Guide

> **Status: gated rehearsal plan — not yet performed.** The CRUD demonstration changes database records. Run it only after the user has explicitly approved a disposable MySQL database and its exact database name is configured as `DB_NAME`. Do not use the configured shared/development database or valuable data. No approved disposable target is currently recorded in project configuration.

## Preconditions

1. Obtain explicit approval for a disposable database and record its exact name in the test-session notes. Confirm it is not the configured application database and contains no valuable data.
2. Configure the local app to use only that database. Keep credentials private; do not show `.env` on screen.
3. Apply the schema, required migrations, and seed data only to that disposable database. Confirm `users.deleted_at`, `orders.deleted_at`, and `riders.deleted_at` exist before opening the app. Never import the destructive bootstrap schema into any database containing data to preserve.
4. Start Apache/MySQL and verify login and the required role accounts against the disposable target. If the schema or app is not ready, stop and record the blocker rather than using another database.
5. Open a SQL client focused on the disposable database. Prepare read-only queries for the relevant records and record the baseline IDs/counts before the demo.

## Demonstration sequence

For every successful write, show the returned page and the matching row in the disposable database. For each rejected request, show that the database row and relevant history remain unchanged.

1. **Order create/read:** As a customer, create a valid draft order with distinct demo addresses and parcel details. Show it in the order list/detail and compare order number, owner, addresses, parcel, fee, status, and initial delivery history in SQL.
2. **Order update:** Edit that draft as its owner; show only that draft's fields and calculated fee changing. Demonstrate the approved staff-edit policy only after D-013 is implemented and the corresponding acceptance test passes. Show that the order number, status, owner, and history are unchanged.
3. **Order soft delete:** Delete the disposable draft using the confirmation flow. Show that it disappears from active lists/detail while its row remains with `deleted_at` populated and its audit history retained. Show an invalid-CSRF, other-owner, staff, and non-draft attempt leaves order and history data unchanged (use separate disposable fixtures for state/ownership cases).
4. **Admin user CRUD:** As admin, create a disposable staff account, view it, update permitted fields, then soft-delete it with the confirmation flow. Show the row changes and that deleted account is absent from active lists and cannot authenticate. Attempt missing-ID, invalid, duplicate-active-email, duplicate-email-of-soft-deleted-account, self-delete, invalid-role, and invalid-CSRF cases; compare relevant user rows before/after to show no mutation.
5. **Admin rider CRUD:** Create a disposable rider, view/list it, update it, and soft-delete it. Show each corresponding row. Attempt missing-ID, invalid fields/status, invalid-CSRF, and duplicate or other rejected inputs; compare the rider row before/after.
6. **Cross-role and ownership checks:** Demonstrate a second customer cannot edit/delete the first customer's draft; staff can act only as allowed by current approved policies; non-draft orders cannot be edited or soft-deleted. Confirm denied requests do not change orders, addresses, parcels, deliveries, users, or riders.
7. **Cleanup:** Remove only demo fixtures created for this rehearsal, following the app's retention rules. Do not hard-delete historical records or run schema/seed scripts as cleanup. Verify all fixture IDs and references before cleanup and leave the disposable database isolated for reset/disposal.

## Acceptance commands

Run only after the explicit disposable-database approval and after confirming the app targets that exact database. Current tests do not yet cover every Checking 3 case in this guide; record any unsupported case as NOT RUN rather than implying coverage.

```powershell
php tests/test_t4_order_reads.php
php tests/test_t5_b5_read_pages.php
# Run the dedicated Checking 3 HTTP/database acceptance suite once implemented.
```

A completed demonstration requires a dated result entry in `docs/TEST_RESULTS.md` that lists the database target by its approved disposable name (never credentials), each scenario's observed outcome, corresponding row/history evidence, and any skipped cases. This guide itself is not evidence of rehearsal.
