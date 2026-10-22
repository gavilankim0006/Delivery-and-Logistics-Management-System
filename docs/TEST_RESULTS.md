# Test Results

## Courier mock-first integration verification — 2026-10-18

| Case | Result | Evidence |
| --- | --- | --- |
| PHP syntax checks for changed/new PHP files | PASS | `php -l` over all changed/new PHP paths; latest targeted lint includes T8 changes |
| Mock is default without Lalamove credentials; mock quotation/order envelopes and event fixtures; real mode rejects missing credentials | PASS | `php tests/test_t6_courier_contract.php` |
| Shared webhook processor handles assigned, picked-up, delivered, failed and cancelled events; stores snapshots and cancellation reason; duplicates are idempotent; illegal transitions roll back; unknown orders are ignored; malformed payloads rejected | PASS (processor test double) | `php tests/test_t7_courier_webhooks.php`; does not exercise a live MySQL transaction or actual endpoint signature |
| Provider cancellation happens before local cancellation; provider failure leaves local state/history unchanged; local-only and ineligible order handling are safe | PASS (service test doubles) | `php tests/test_t8_courier_recovery.php` |
| Booking identity is persisted before driver lookup; lookup failure leaves the booking active and does not repeat placement | PASS (service test doubles) | `php tests/test_t8_courier_recovery.php` |
| Database schema and seed | PASS | `php tests/test_t1_schema_seed.php` |
| Google Maps and fee acceptance | PASS | `php tests/test_t3_google_maps_fee.php` |
| Order read/create/edit/cancel flows | PASS | `php tests/test_t4_order_reads.php` after fixing form initialization |
| Role and dispatch HTTP regression | PARTIAL / BLOCKED | Latest `php tests/test_t2_auth_roles.php` passes guest/customer access checks, but dispatcher `/dispatch` returns HTTP 500 because local orders table lacks courier snapshot columns selected by its query |
| T5 saved-address and page regression | PARTIAL / BLOCKED | Address CRUD, referenced-address protection/feedback, access control, admin lists, and order filters pass. Dispatch-board HTTP check returns 500 because local `orders` table lacks migration 003 snapshot columns used by the dispatch query. |
| Live database-backed courier booking/webhook persistence and HTTP simulator guard coverage | BLOCKED | Local DB is missing `courier_driver_id`, `courier_driver_name`, `courier_driver_phone`, and `courier_driver_vehicle`. Migration 003 is ready but was not applied because it changes the existing local database and requires explicit authorization. |

`test_t7_courier_webhooks.php` and `test_t8_courier_recovery.php` use in-memory/service test doubles; they are not substitutes for database-backed acceptance or HTTP signature checks. The real inbound Lalamove webhook signature convention and exact event payload must be confirmed with partner documentation before production use.

## Checking 2: Order read path and Phase C1 draft creation

| Case | Result | Evidence |
| --- | --- | --- |
| New-order form displays and invalid weight/distance are rejected while preserving submitted values | PASS | `php tests/test_t4_order_reads.php` |
| Customer order list is restricted to the signed-in customer's orders | PASS | `php tests/test_t4_order_reads.php` |
| Status filter and pagination route return successfully | PASS | `php tests/test_t4_order_reads.php` |
| Customer order detail displays saved addresses and delivery history | PASS | `php tests/test_t4_order_reads.php` |
| Customer cannot view another customer's order; inaccessible/missing ID returns 404 | PASS | `php tests/test_t4_order_reads.php` |
| Dispatcher can list orders for multiple customers | PASS | `php tests/test_t4_order_reads.php` |
| Valid customer submission persists draft order, both addresses, parcel, configured server-side fee, and initial delivery audit row | PASS | `php tests/test_t4_order_reads.php`; test cancels its created draft using the valid state-machine transition |
| Missing-CSRF create does not mutate order/address rows; cancellation CSRF failure and delivered-order state rejection do not mutate status or audit history | PASS | `php tests/test_t4_order_reads.php` |

## Checking 2: B5 database-backed read pages

| Case | Result | Evidence |
| --- | --- | --- |
| Customer saved-address page renders database rows scoped to the signed-in customer; other customer's addresses are absent | PASS | `php tests/test_t5_b5_read_pages.php` |
| Guest is redirected and dispatcher is denied access to the customer address page | PASS | `php tests/test_t5_b5_read_pages.php` |
| Admin users/riders pages render seeded rows and apply search/status filters | PASS | `php tests/test_t5_b5_read_pages.php` |
| Customer is denied access to admin users/riders pages | PASS | `php tests/test_t5_b5_read_pages.php` |
| Customer order date/search filters return matching rows and unknown order ID returns friendly 404 | PASS | `php tests/test_t5_b5_read_pages.php` |
| Dispatcher-board counters match independent SQL counts for the documented status/date predicates | BLOCKED | `php tests/test_t5_b5_read_pages.php` reaches this check but dispatch returns HTTP 500 until migration 003 adds snapshot columns to the local DB. |
| PHP syntax checks for the new address controller/model/view and T5 acceptance test | PASS | `php -l` on each listed PHP file |

The T5 test requires the local XAMPP app, seeded development database, and PHP cURL extension. It performs HTTP acceptance checks and does not claim that the manual demo was completed.

## Regression checks

- Historical regression results below were recorded before the 2026-10-18 courier integration edits. Current results and rerun status are listed above; the T2 dispatcher check is currently failing because migration 003 is not applied to the local database.
- `php tests/test_t0_api_client.php` — previously passed; it adds an `api_logs` row.
- `php tests/test_t1_schema_seed.php` — currently passes; schema, seed counts, and 10 original seeded orders verified.
- `php tests/test_t2_auth_roles.php` — latest run failed on dispatcher `/dispatch` HTTP 500 after the customer navigation assertion passed.
- `php tests/test_t3_google_maps_fee.php` — currently passes.
- `php tests/test_t4_order_reads.php` — currently passes, including create/edit/cancel cleanup.
- `php tests/test_t5_b5_read_pages.php` — latest run passed address CRUD/protection, access control, admin lists, and filters; final dispatch board check returned HTTP 500 until migration 003 adds snapshot columns to the local DB.
- `php tests/test_t8_courier_recovery.php` — currently passes cancellation ordering/failure safety and booking persistence before driver lookup.
- `php tests/test_t6_courier_contract.php` and `php tests/test_t7_courier_webhooks.php` — currently pass.
- PHP syntax checks — currently pass for all changed/new PHP files.

## Fresh follow-up verification — 2026-10-20

| Check | Result | Evidence / limitation |
| --- | --- | --- |
| Login page GET | PASS (HTTP 200) | Read-only GET of `/login`; no credentials were submitted. |
| Login HTTP 500 diagnosis | ROOT CAUSE CONFIRMED from user-provided error trace; not independently rerun | The supplied login stack trace shows MySQL error 1054 on `u.deleted_at` in `User_model::find_by_email()`, matching the prior read-only schema probe that found `users.deleted_at` absent. This confirms the reported login 500 is caused by the schema mismatch. Migration 005, which adds nullable `deleted_at` to `users`, `orders`, and `riders`, was not run; no database mutation is authorized by this evidence alone. |
| Health endpoint connected response | PASS (HTTP 200) | Read-only GET `/health` returned `{"status":"ok","database":"connected"}`. |
| Health endpoint failure response contract | PASS (simulated, database-free) | `php tests/test_t2_health_models_unit.php` exercised connected, failed-probe, and thrown-connection cases; both failure cases returned generic 503 JSON without exception details. This does not simulate Apache startup/runtime behavior with MySQL unavailable. |
| Customer_model and Delivery_model focused API contract | PASS (database-free doubles) | The same unit test exercises list/get/filter/create/update methods and confirms the intentional customer-history and append-only delivery deletion denials. It does not verify SQL against MySQL. |
| Rider unchanged-update behavior | PASS (database-free double) | `php tests/test_t3_rider_model_unit.php`; covers unchanged existing rider, missing record, invalid inputs, and failed writes. No end-to-end admin HTTP CRUD was run. |
| Courier/signature regression tests | PASS (isolated doubles) | `php tests/test_t6_courier_contract.php`, `test_t7_courier_webhooks.php`, `test_t8_courier_recovery.php`, and `test_t9_signature_features.php` passed on 2026-10-20. These do not prove live provider or MySQL behavior. |
| Current configured DB migration metadata | PARTIAL — read-only inspection only | `information_schema` showed the four courier snapshot columns and migration-004 feature tables present; `deleted_at` is absent from `users`, `orders`, and `riders`. No migration/bootstrap SQL was run. Dispatch HTTP was not rerun. |
| Foreign-key opt-in test | NOT RUN — database gate | No approved disposable DB is available. The test now requires explicit opt-in/approval variables, an explicit `DB_NAME` exactly matching `T2_APPROVED_DB_NAME`, and refuses the configured application DB. |
| Checking 2 / Checking 3 demos and database-mutating acceptance | NOT RUN — database gate | No demo was rehearsed and no DB-mutating acceptance test was run. Do not mark either checking complete. |
| Admin reports filter/query contract | PASS (database-free test doubles) | `php tests/test_t8_report_contract.php` validates status/date/rider filters, half-open date binding, soft-deleted order exclusion, aggregated paid amounts, bounded row queries, and CSV formula-cell neutralization. It does not execute report SQL against MySQL or verify browser rendering/export delivery. The configured DB is known to lack `orders.deleted_at`, so the report query requires migration 005 on an approved test DB before DB-backed acceptance. |
| Admin reports PHP syntax | PASS | `php -l` passed for `app/models/Report_model.php`, `app/controllers/Admin.php`, `app/views/admin/reports.php`, and `tests/test_t8_report_contract.php`. |

No migrations, schema/seed imports, login POSTs, or database-mutating acceptance requests were run during this follow-up. Historical database-dependent PASS entries above this section remain historical and were not rerun. The admin report implementation is new but its SQL, totals, HTTP access, and CSV response remain unverified against MySQL/Apache.

Checking 2 remains **incomplete**. The `/health` endpoint and focused `Customer_model` / `Delivery_model` contracts now have database-free verification, but MySQL-backed model acceptance, the bad-foreign-key rejection check, dispatch HTTP verification against the current schema, and the required manual database-to-page demonstration have not been completed. Record those results after testing against an approved disposable database; do not mark Checking 2 or the full order CRUD phase complete based on the current evidence.

## Checking 3: CRUD and order safety — 2026-10-22 status

| Acceptance area | Result | Evidence / limitation |
| --- | --- | --- |
| Draft soft-delete HTTP/DB behavior (CSRF, owner/staff permissions, state validation, retained history, list/detail visibility, no mutation on failure) | NOT RUN — database gate | No explicitly approved disposable MySQL target is recorded. Existing T4 acceptance evidence covers create/read/edit and selected state/ownership paths historically, not soft deletion. |
| Admin user CRUD HTTP/DB behavior (CSRF, validation, duplicates, missing IDs, self-delete, role/status rules, soft-delete visibility, no mutation on failure) | NOT RUN — database gate | No Checking 3 acceptance run has been performed. `test_t5_b5_read_pages.php` historically covered list/filter rendering and access only. |
| Admin rider CRUD HTTP/DB behavior (CSRF, validation, missing IDs, soft-delete visibility, no mutation on failure) | NOT RUN — database gate | The rerun of `test_t3_rider_model_unit.php` passed against a database-free double only; it is not HTTP/MySQL acceptance. |
| Order number allocation, concurrent creation, lock timeout/release, transaction rollback, transition/history atomicity | NOT RUN — database gate | No concurrency or failure-injection test was run. Existing T4 results are historical and do not establish these cases. |
| Ownership, deleted-order visibility, role/edit policy, email reuse after soft deletion | NOT RUN — database gate | No HTTP/DB acceptance was run. Policy decisions are recorded in D-013 through D-016 in `docs/DECISIONS.md`; implementation/test mismatches remain open as documented there. |
| Checking 3 database-to-page demo | NOT REHEARSED — database gate | `docs/DEMO_CHECKING_3.md` is a gated rehearsal plan, not evidence of a completed demonstration. |

Checking 3 remains **incomplete**. These NOT RUN results are current observations from this follow-up; they do not supersede historical, narrower test results elsewhere in this document. Do not mark order CRUD complete until the cases are accepted against an explicitly approved disposable database and the demo is actually rehearsed.

## Database-free regression rerun — 2026-10-22

| Check | Result | Evidence / limitation |
| --- | --- | --- |
| Customer/Delivery model API and health response contracts | PASS (database-free doubles) | `php tests/test_t2_health_models_unit.php`; simulated health-probe failure messages were emitted as expected. No MySQL was accessed. |
| Rider update validation and unchanged-value behavior | PASS (database-free double) | `php tests/test_t3_rider_model_unit.php`; no MySQL was accessed. |
| Courier provider contract | PASS (isolated) | `php tests/test_t6_courier_contract.php`; mock/default and missing-secret cases passed. |
| Courier webhook processor and inbound-enable gate | PASS (in-memory database double / config gate) | `php tests/test_t7_courier_webhooks.php`; lifecycle, duplicate, rollback, unknown-order, malformed-payload, and default-disabled / explicit-confirmation gate cases passed. This does not validate a real partner signature or live HTTP delivery. |
| Courier cancellation and recovery | PASS (service doubles) | `php tests/test_t8_courier_recovery.php`; expected simulated provider-failure and driver-lookup messages were emitted while checks passed. |
| Signature and dispatch-recovery feature logic | PASS (database-free doubles) | `php tests/test_t9_signature_features.php`; polygon, simulator, reservation, ambiguous booking, and rate-limit cases passed. |
| Report filter and CSV safety contract | PASS (database-free doubles) | `php tests/test_t8_report_contract.php`; does not execute report SQL or exercise Apache/CSV download. |

These were the only checks rerun in this follow-up. No MySQL-backed test, HTTP acceptance test, migration, demo rehearsal, or database mutation was performed. Checking 2 and Checking 3 remain incomplete; Phase D and handoff acceptance remain open.
