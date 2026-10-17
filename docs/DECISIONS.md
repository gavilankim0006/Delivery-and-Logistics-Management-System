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

## D-010: Google Maps Routing & Fallback Geocoding Engine
- **Decision:** `GoogleMapsService` wraps Google Directions and Places Autocomplete APIs with strict `country:ph` restriction. When `GOOGLE_MAPS_API_KEY` is not configured, in development mode, or quota-limited, the service seamlessly falls back to a deterministic Philippine geographic routing engine based on Haversine distance, known Metro Manila hubs, and an urban road factor multiplier (1.32x straight-line, 2.8 min/km duration). Geocoordinates are validated against the Philippine geographical bounds (Lat 4.0°-22.0°N, Lng 115.0°-128.0°E).
- **Rationale:** Guarantees robust, unblocked automated testing and local developer workflows while executing identical interfaces, route polylines, and fee calculations when production Google Maps credentials are deployed.
