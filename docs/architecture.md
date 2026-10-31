# System Architecture: Delivery & Logistics Management System

This document describes the architecture currently implemented in the repository. Items explicitly labeled “planned” or “not implemented” are not active application flows; consult `docs/system-process.mmd` for the detailed end-to-end process and its boundaries.

---

## Architecture Diagram

```mermaid
graph TD
    subgraph Client_Layer ["Client Layer (Browser)"]
        UI["Web Browser: PHP views, Bootstrap 5, custom CSS"]
        VueDispatch["Vue 3 Dispatch Board (Vite build)"]
        GMapsJS["Google Maps JavaScript API (admin service-area map)"]
        FCMWeb["FCM token registration UI (push delivery not implemented)"]
    end

    subgraph LavaLust_Core ["LavaLust MVC Core (PHP 8.1+)"]
        Router["Router / URL Dispatcher"]
        Middleware["Auth & CSRF Middleware (Deny-by-default, Throttling)"]
        
        subgraph Controllers ["Controllers (app/controllers/)"]
            C_Auth["Auth.php (Login, Register, Session)"]
            C_Orders["Orders.php (Create, Checkout, Track, Cancel)"]
            C_Dispatch["Dispatch.php (Staff Dispatch Board & Recovery)"]
            C_Admin["Admin.php (Users, Riders, Rates, Reports, Logs, Simulators)"]
            C_Webhooks["Webhooks.php (/webhooks/paymongo, /webhooks/lalamove)"]
        end

        subgraph Models ["Models & State Machine (app/models/)"]
            M_Order["Order_model.php (Enforces set_status & State Machine)"]
            M_User["User_model.php"]
            M_Rider["Rider_model.php"]
            M_Address["Address_model.php"]
            M_Delivery["Delivery_model.php"]
            M_Setting["Setting_model.php"]
        end

        subgraph Services ["External API Services (app/libraries/)"]
            S_ApiClient["ApiClient.php (cURL, 10s Timeout, 2 Retries on 5xx/Timeout)"]
            S_GMaps["GoogleMapsService.php"]
            S_Courier["CourierProviderFactory.php + MockCourierDriver.php / LalamoveCourierDriver.php"]
            S_PayMongo["PaymentCheckoutService.php + PayMongoService.php"]
            S_Semaphore["SemaphoreService.php (opt-in SMS)"]
            S_FCM["FirebasePushService.php (opt-in push notifications)"]
        end
    end

    subgraph Database_Layer ["Data Storage (MySQL / MariaDB)"]
        DB[(delivery_logistics DB)]
        T_Logs[("api_logs (Audit Trail)")]
        T_Webhooks[("webhook_events (Idempotency)")]
        T_Orders[("orders, deliveries, parcels, payments and notifications schema")]
    end

    subgraph External_APIs ["External REST Services"]
        API_GMaps["Google Maps Platform (Routes, Places)"]
        API_Lalamove["Lalamove Sandbox API (Quotation, Booking)"]
        Tracking["Courier share link or customer-visible periodic rider-shared location"]
        API_PayMongo["PayMongo test checkout API"]
        API_Semaphore["Semaphore SMS API (disabled unless opted in and enabled)"]
        API_FCM["Firebase Cloud Messaging HTTP v1 API (disabled unless opted in and enabled)"]
    end

    subgraph Planned_Work ["Not Implemented / Potential Future Work"]
        Planned_Payment["Payment reconciliation and refunds"]
        Planned_Retry["Automatic booking retry scheduler"]
        Planned_NotifyWorker["Notification retry worker"]
    end

    %% Client communication
    UI -->|HTTPS Requests| Router
    VueDispatch -->|GET /dispatch/data (same-origin JSON)| C_Dispatch
    VueDispatch -.->|Mounted in dispatch PHP view| UI
    GMapsJS -.->|Service-area map| UI
    FCMWeb -->|POST token| C_Auth

    %% Routing to Controllers
    Router --> Middleware
    Middleware --> Controllers

    %% Controller to Model / Service flows
    Controllers --> Models
    Controllers --> Services

    %% Service inheritance & Logging
    S_GMaps -->|Extends| S_ApiClient
    S_Courier -->|Real driver uses| S_ApiClient
    C_Auth -->|Stores FCM token only; no push send| M_User
    C_Orders -->|Explicit checkout POST| S_PayMongo
    S_PayMongo -->|Test-key-only checkout session| API_PayMongo
    S_Semaphore -->|Opt-in payment and delivery-status SMS| API_Semaphore
    S_ApiClient -->|Logs every attempt| T_Logs

    %% External API calls
    S_GMaps -->|cURL REST| API_GMaps
    S_Courier -->|Optional signed sandbox API calls| API_Lalamove
    C_Orders -->|Displays stored courier share link| Tracking
    Planned_Payment -.->|Future checkout integration| API_PayMongo
    Planned_Notifications -.->|Future delivery integration| API_Notifications

    %% Webhook ingest
    API_PayMongo -->|POST signed webhook; session revalidated via API| C_Webhooks
    API_Lalamove -->|POST Webhook (configured HMAC; verify partner spec)| C_Webhooks
    C_Webhooks -->|Check Idempotency| T_Webhooks
    C_Webhooks -->|Update Order Status| M_Order
    S_Courier -->|Mock assignment uses shared processor| C_Webhooks

    %% Models to Database
    Models --> DB
    DB --- T_Orders

    %% Planned capabilities are not connected to active application flows
    Planned_Retry -.->|Future; manual recovery is active today| S_Courier
```

---

## Implemented Architecture & Constraints

1. **Layered Decoupling:** Controllers use service libraries for courier and route-provider calls; outbound HTTP requests are handled through `ApiClient`.
2. **Centralized State Transitions:** `Order_model::set_status()` enforces allowed order transitions and records a history event in `deliveries`. Status notifications are not dispatched by the current application.
3. **Resilience & Fault Tolerance:**
   - 10-second cURL timeouts with up to 2 request-level retries on 5xx or network errors. These do not retry courier bookings.
   - Outbound HTTP attempts are recorded in `api_logs`.
   - Webhook idempotency uses `webhook_events` to guard against duplicate processing.
   - Booking recovery is staff-initiated and guarded by a recovery lock; uncertain provider outcomes are escalated rather than blindly retried.
4. **Security Hardening:**
   - Deny-by-default role authorization.
   - Output escaping via `htmlspecialchars` across all views.
   - CSRF protection enabled on state-changing forms.
   - Session regeneration upon successful authentication.
   - Sensitive credential isolation in `.env`.

5. **Courier provider selection:** `COURIER_DRIVER=mock` is the local default. Lalamove keys are optional. Mock events and inbound webhook requests share one payload processor. Courier booking and staff-triggered recovery use a provider interface; automatic retry scheduling is not implemented.
6. **Integration caveat:** PayMongo checkout accepts test secret keys only and orders are marked paid only after the signed webhook and server-side session lookup agree. Semaphore SMS requires the relevant separate customer opt-in and `SEMAPHORE_ENABLED=1`; delivery-status SMS is queued transactionally in an idempotent outbox and dispatched after commit. Claim rows left in `sending` for more than five minutes are marked failed for manual review rather than resent automatically.

## Not Implemented (Potential Future Work)

- **Payment reconciliation and refunds:** Checkout creation and signed successful-payment processing are implemented for PayMongo test keys; refunds, disputes, expired-session reconciliation, and live-mode credentials are not supported.
- **Automatic courier retries:** There is no retry scheduler or `retry_failed.php` job. Staff can initiate recovery for eligible orders; uncertain provider states require review.
- **Notifications:** Semaphore payment-confirmation SMS and delivery-status SMS use separate opt-ins and remain disabled unless `SEMAPHORE_ENABLED=1`. Delivery-status messages use an idempotent outbox and are attempted after the owning transaction commits; failed or expired claims are left for manual review. There is no scheduled worker, automatic retry, operations UI for reviewing failed outbox rows, or Firebase push delivery.
- **Local rider assignment:** Dispatch can select an eligible internal rider or Lalamove per order. Internal riders use the authenticated portal, submit proof of delivery, and can share foreground location periodically; dependable background tracking and native push are not available in the web portal.
- **Route-based order creation:** A route-calculation API exists, but current order creation uses a manually entered distance and does not call that API.
