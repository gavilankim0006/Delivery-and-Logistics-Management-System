# Checking 2 and Checking 3 Policy Register

This register resolves the outstanding policy questions identified during acceptance planning. The decisions guide implementation and tests; they are not evidence of acceptance.

| Topic | Decision | Current alignment / follow-up |
| --- | --- | --- |
| Staff draft editing | Owner, admin, and dispatcher may edit only draft orders. Staff may not alter customer ownership, order number, status, or delivery history. | D-013. Current `Orders::edit()` / `Orders::update()` and T4 assertions deny staff; reconcile implementation and tests with the decision before claiming C3. |
| Customer deletion | Admin soft-deletes the user account, marking it inactive while retaining the customer profile and all operational/financial history. | D-014. Never cascade-delete records through account deletion. Add HTTP/database retention and visibility acceptance. |
| Role changes | Admin may change staff roles between admin and dispatcher, respecting self-demotion/deactivation protections. Generic editing may not convert to/from customer; use a dedicated customer conversion workflow if needed. | D-015. Current model permits roles 1, 2, and 3; constrain controller/model and add tests. |
| Email reuse after soft deletion | Email remains reserved; no new account may reuse it. Account restore is not supported by current CRUD. | D-016 and global unique email index. Confirm duplicate-after-delete through HTTP/database acceptance. |
| Address deletion | Customer may delete an address only if it is not referenced by an order; referenced-address deletion is blocked. Existing address deletion is hard deletion, guarded by the reference check. | This preserves order address snapshots and matches the current address model/controller. The original schema/brief soft-delete wording should be revisited if an address soft-delete requirement is restored; current migration 005 does not add `addresses.deleted_at`. |

## Status

No database-backed acceptance tests or demo have been performed for these decisions. See `docs/TEST_RESULTS.md` for the current gate and `docs/DEMO_CHECKING_3.md` for the gated demonstration plan.
