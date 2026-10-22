# Architecture & Implementation Decisions

This document records architectural, technical, and operational decisions made for the Delivery and Logistics Management System, adhering to Section 2 Rule 10 of the brief.

---

## D-001: Database Selection & Character Set
- **Decision:** MySQL / MariaDB database named `delivery_logistics` using `utf8mb4` character set and `utf8mb4_unicode_ci` collation.
- **Rationale:** Standard in XAMPP local environment and production Linux servers. Full Unicode support for names, addresses, and parcel descriptions across Philippine languages and special characters.

## D-002: Base URL and Routing Configuration
- **Decision:** Default base URL is `http://localhost/SIAproject/` dynamically loaded from `getenv('BASE_URL')` in `.env`.
- **Rationale:** Works seamlessly with XAMPP htdocs subdirectory hosting and can be overridden by ngrok / Cloudflare Tunnel for webhook ingestion.

## D-003: External API Architecture & Client Inheritance
- **Decision:** Shared `ApiClient` class in `app/libraries/ApiClient.php` managing all cURL transport, headers, 10-second timeout, 2 automatic retries on 5xx / connection timeouts, and audit logging into `api_logs`. Specific service classes (`GoogleMapsService`, `LalamoveService`, `PayMongoService`, `SemaphoreService`, `FcmService`) extend `ApiClient`.
- **Rationale:** Enforces clean separation of concerns, guarantees reliable logging, prevents raw API leakage, and ensures consistent error handling.

## D-004: Role Hierarchy & Access Control
- **Decision:** Three roles represented in `roles` table:
  1. `admin` (ID 1): Full administrative access, user/rider management, rates, financial/delivery reports, API logs.
  2. `dispatcher` (ID 2): Live operations dispatch board, rebooking, cancellation, rider assignment view.
  3. `customer` (ID 3): Customer self-service portal (new order, payment, tracking, history, profile).
- **Rationale:** Strict deny-by-default role enforcement via middleware / `auth_helper`.

## D-005: Order Status State Machine Enforcement
- **Decision:** The state machine is strictly enforced exclusively inside `Order_model::set_status()`.
- **Allowed transitions:**
  - `draft` → `pending_payment`, `cancelled`
  - `pending_payment` → `paid`, `cancelled`, `failed`
  - `paid` → `booking`, `cancelled`
  - `booking` → `rider_assigned`, `failed`, `cancelled`
  - `rider_assigned` → `picked_up`, `cancelled`, `failed`
  - `picked_up` → `delivered`, `failed`
  - `delivered`, `cancelled`, `failed` → terminal (except staff rebook transitioning `failed` back to `booking`)
- **Rationale:** Prevents out-of-order transitions, race conditions from asynchronous webhooks, and ensures audit consistency.

## D-006: Monetary Storage and Centavo Conversion
- **Decision:** All prices and fees stored as `DECIMAL(10,2)` in MySQL. When interacting with PayMongo Checkout or Payments API, amounts are converted to integer centavos (`(int) round($amount * 100)`).
- **Rationale:** Eliminates floating-point rounding inaccuracies while strictly complying with PayMongo's integer centavo currency requirement.

## D-007: Webhook Idempotency & Signature Verification
- **Decision:** Public endpoints `/webhooks/paymongo` and `/webhooks/lalamove` verify provider signatures before parsing payloads. Processed webhook IDs are recorded in `webhook_events` with unique index `(provider, event_id)` to ensure duplicate transmissions return HTTP 200 without executing side effects twice.
- **Rationale:** Webhook delivery guarantees at-least-once semantics; idempotency prevents double bookings or repeated status triggers.

## D-008: Non-blocking Notification Dispatch
- **Decision:** Failure in `SemaphoreService` or `FcmService` throws no uncaught exceptions and does not roll back database transactions. Errors are logged into `notifications` and `api_logs`.
- **Rationale:** Network blips with telecom aggregators or push services must never disrupt critical order and payment processing.

## D-009: Session-based RBAC, Throttling & Subdirectory URL Normalization
- **Decision:** Session authentication uses `password_hash`/`password_verify` and `session_regenerate_id(true)` upon login. Route protection is enforced by deny-by-default `require_role($roles)` which serves a dedicated HTTP 403 response for unauthorized roles. Login throttling enforces a maximum of 5 failed attempts per 5 minutes per IP/email via persistent runtime file tracking. LavaLust kernel routing was normalized to correctly handle XAMPP subdirectory rewrite structures (`/SIAproject/public` vs `/SIAproject`).
- **Rationale:** Prevents cookie-clearing bypass of login rate-limiting, enforces strict role separation across UI and backend endpoints, and ensures consistent routing across local XAMPP and production server environments.

## D-010: Google Maps Routing & Fallback Geocoding Engine (Superseded)
- **Original decision:** `GoogleMapsService` wrapped Google Directions and Places Autocomplete APIs with strict `country:ph` restriction and a deterministic Philippine fallback.
- **Status:** Superseded by D-012. Existing Google Maps code and configuration are legacy until the Leaflet/OpenStreetMap provider path is implemented and verified; do not treat this historical implementation as the current provider direction.

## D-011: Mock-First Courier Provider
- **Decision:** `COURIER_DRIVER` defaults to `mock`; local booking and the development simulator require no Lalamove credentials. `LalamoveCourierDriver` remains an explicit optional sandbox provider. Simulated v3-shaped events pass through the same `Webhooks::processPayload()` path as inbound webhook requests, and order status changes remain enforced by `Order_model::set_status()`.
- **Rationale:** Partner Portal access is unavailable for the student developer, so delivery workflows must remain demonstrable without courier account access while preserving an adapter boundary for later authorized sandbox testing. Mock provider driver details are stored as order snapshots, not as internal fleet riders.
- **Security note:** Inbound processing is disabled unless `COURIER_DRIVER=lalamove`, `APP_ENV=production`, `LALAMOVE_WEBHOOK_AUTH_CONFIRMED=1`, and `LALAMOVE_WEBHOOK_SECRET` are all set. The public Lalamove v3 API guide documents outgoing API request signing but does not document this inbound webhook convention. Leave the confirmation flag unset/0 until partner authentication and payload specifications are verified; the current HMAC implementation is not evidence that the partner uses this convention.

## D-012: OpenStreetMap-Based Routing and Geocoding Direction
- **Decision:** Replace the planned Google Maps path with a provider boundary for Leaflet map display, OpenStreetMap tiles, Nominatim geocoding (Philippines-restricted, rate-limited and cached), and a configurable ORS or OSRM routing adapter. Validate coordinates server-side and retain typed-address/manual fallback behavior when external providers are unavailable. Compute distance and fees server-side; browser-supplied totals are never authoritative.
- **Rationale:** The project selected a no-Google-key mapping direction. Provider terms, request limits, attribution and any required ORS credentials must be respected. This is an architectural direction only; implementation and live-provider acceptance remain outstanding.

## D-013: Draft Order Editing Authority
- **Decision:** The owning customer, an admin, or a dispatcher may edit an order only while it is in `draft`. Staff edits must use the same server-side validation, fee recalculation, ownership-preserving transaction, and CSRF protections as customer edits; they must not alter the customer, order number, status, or delivery history.
- **Rationale:** This follows the C3 acceptance requirement (“owner or staff”) while limiting staff edits to the pre-payment draft state. Staff editing is not currently implemented: `Orders::edit()` and `Orders::update()` are customer-only, and the T4 acceptance test explicitly expects admin/dispatcher denial. Update implementation and tests to this decision before claiming C3 acceptance.

## D-014: Customer Account Deletion
- **Decision:** Admin deletion of a customer is a soft deletion of the user account: mark the account deleted and inactive, prevent future authentication, and exclude it from active user lists. Retain the customer profile, addresses, orders, payments, deliveries, and audit references; never cascade-delete customer/order history through this action.
- **Rationale:** Historical delivery and financial records must remain attributable and available to staff. This is an account offboarding action, not erasure of operational records. The current generic user soft-delete path follows this retention model, but Checking 3 HTTP/database acceptance is outstanding.

## D-015: User Role Changes
- **Decision:** Admins may change an existing staff account between `admin` and `dispatcher`, subject to the existing self-demotion/deactivation guard. Generic user editing must not convert an account to or from `customer`; customer provisioning and conversion require a dedicated workflow that creates or preserves the associated `customers` row and validates ownership/history.
- **Rationale:** A role-only update can leave a staff account without a customer profile or change a customer’s access to historical records without a controlled migration. The current `update_user()` accepts all three role IDs, so controller/model validation and acceptance tests must be aligned with this policy.

## D-016: Email Reuse After Soft Deletion
- **Decision:** An email address remains reserved after a user is soft-deleted and cannot be assigned to another account. Account restore/reuse is not supported by the current CRUD flow.
- **Rationale:** The users table has a global unique email constraint and `User_model::email_in_use()` intentionally checks deleted rows too. This preserves an unambiguous identity for retained order/audit history. The current model behavior matches this policy; duplicate-after-delete HTTP/database acceptance remains outstanding.
