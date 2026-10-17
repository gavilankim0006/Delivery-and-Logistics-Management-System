# AI Agent Brief: Delivery & Logistics System


Paste this whole brief into a coding agent (Claude Code, Cursor, etc.) as its standing instructions. It defines the mission, the design plan to follow, and the build order with acceptance checks. Companion: the human Build Guide.

## 1. Role and mission

You are a senior full-stack PHP engineer. Build a **web-based Delivery and Logistics Management System** in **PHP 8.1+ with the LavaLust MVC framework, MySQL, JavaScript and Bootstrap 5**. It integrates five REST APIs: **Google Maps Platform** (address search, route, distance), **Lalamove** (rider quotation and booking), **PayMongo** (online payment), **Semaphore** (SMS) and **Firebase Cloud Messaging** (push). Users are small delivery businesses (admin, dispatcher) and their customers. Riders are represented by Lalamove drivers; there is no rider app in this version.

## 2. Working rules

1. Work in the order of section 4. Finish and verify one task before starting the next. After each task, commit with a clear message.
2. Build and test each external API alone first (a small CLI or test controller against sandbox/test mode), then wire it into the order flow. Never call an API directly from a controller: one service class per API in `app/libraries/`, all extending a shared `ApiClient` (cURL, 10 s timeout, 2 retries on 5xx or timeout, every call logged to `api_logs`).
3. Secrets live only in `.env` (loaded with phpdotenv). Commit `.env.example` with blank values. Never hard-code or print keys.
4. Only `Order_model::set_status()` may change `orders.status`. It validates the transition, writes a row to `deliveries`, and calls `NotificationService`.
5. Money is stored as DECIMAL and sent to PayMongo in centavos. The fee is always computed on the server.
6. Webhooks verify their signature, are idempotent through the `webhook_events` table, and return HTTP 200 only after processing.
7. A failed SMS or push must never roll back an order. Log it and continue.
8. Security baseline on every page: prepared statements, server-side validation, output escaping, CSRF tokens, `password_hash`, role check (deny by default), session regeneration at login.
9. If an API detail is uncertain (endpoint, header, field name), **read the provider's current official documentation instead of guessing**, and note the source in a code comment.
10. If a requirement is ambiguous, pick the simplest reasonable option, write it in `docs/DECISIONS.md`, and continue. Do not stop to ask unless a credential or external account is missing.
11. Never touch live payment or booking credentials. Use only Lalamove sandbox and PayMongo test keys.
12. Do not add features outside the scope below.

## 3. Design plan (follow this; save each item under `docs/` as you create it)

### 3.1 Actors and use cases

| Actor | Use cases |
| --- | --- |
| Customer | Register, log in, create order (pick addresses, see route and fee), pay online, track order, view history, cancel before pickup, receive SMS and push |
| Dispatcher | View all orders, filter, rebook failed bookings, cancel, view rider details, receive urgent push |
| Admin | Everything the dispatcher does, plus manage users, riders and fee settings, view reports and API logs |
| Lalamove (system) | Sends driver and status webhooks |
| PayMongo (system) | Sends payment webhooks |

Deliverable: `docs/use-case.puml` (PlantUML) or an image rendered from it.

### 3.2 Architecture

```text
Browser (Bootstrap, JS, Maps JS, FCM web SDK)
   |  HTTPS
LavaLust app
   Controllers -> Models -> MySQL
        |
        +-> Services: GoogleMaps, Lalamove, PayMongo, Semaphore, Fcm  (via ApiClient -> api_logs)
        +-> NotificationService (templates per status; SMS + push)
   Webhook endpoints: /webhooks/paymongo, /webhooks/lalamove  (public, signature-verified)
   Cron: backup.sh (daily), retry_failed.php (every 5 min)
```

Deliverable: `docs/architecture.md` with this diagram redrawn as Mermaid.

### 3.3 Database (ERD)

Tables: `roles`, `users`, `customers`, `riders`, `addresses`, `orders`, `parcels`, `payments`, `deliveries`, `notifications`, `api_logs`, `webhook_events`.

| Relationship | Type |
| --- | --- |
| roles to users | one to many |
| users to customers | one to one |
| customers to addresses | one to many |
| customers to orders | one to many |
| orders to addresses | two foreign keys: pickup and drop-off |
| orders to parcels, payments, deliveries | one to many |
| riders to orders | one to many (nullable until assigned) |
| users to notifications | one to many |

Key columns: `orders.lalamove_quotation_id`, `orders.lalamove_order_id`, `orders.share_link`, `payments.paymongo_session_id`, `payments.paymongo_payment_id`, `users.fcm_token`. Write the full DDL to `database/schema.sql` with indexes on `orders(status)`, `orders(customer_id)`, `deliveries(order_id)`. Deliverable: `docs/erd.mmd` (Mermaid `erDiagram`).

### 3.4 Order state machine (enforce in `set_status`)

| From | Allowed next | Trigger |
| --- | --- | --- |
| draft | pending_payment, cancelled | Customer confirms order |
| pending_payment | paid, cancelled, failed | PayMongo webhook or timeout |
| paid | booking, cancelled | Payment verified |
| booking | rider_assigned, failed, cancelled | Lalamove order placed, then driver webhook |
| rider_assigned | picked_up, cancelled, failed | Lalamove webhook |
| picked_up | delivered, failed | Lalamove webhook |
| delivered, cancelled, failed | none (failed may return to booking through staff Rebook) | Terminal |

### 3.5 Pages and routes

| Role | Pages |
| --- | --- |
| Public | `/login`, `/register` |
| Customer | `/orders/new`, `/orders`, `/orders/{id}`, `/orders/{id}/pay`, `/profile` |
| Dispatcher | `/dispatch` (live board), `/orders/{id}` (staff view with actions) |
| Admin | `/admin` (dashboard), `/admin/users`, `/admin/riders`, `/admin/settings` (fee rates), `/admin/reports`, `/admin/logs` |
| Webhooks and AJAX | `POST /webhooks/paymongo`, `POST /webhooks/lalamove`, `GET /orders/{id}/status` (JSON for polling), `POST /users/save_token` |

### 3.6 Wireframes (build these screens in Bootstrap; mobile-first)

New order (customer):

```text
+--------------------------------------------------+
| Navbar: Logo | New Order | My Orders | Logout     |
+--------------------------------------------------+
| Pickup address  [ autocomplete input          ]  |
| Drop-off address[ autocomplete input          ]  |
| Contact name/phone for each stop                 |
| Parcel: description | weight | size (S/M/L)      |
+----------------------+---------------------------+
| Google Map with      |  Summary card             |
| route line           |  Distance: 7.4 km         |
|                      |  Est. time: 22 min        |
|                      |  Delivery fee: PHP 152.00 |
|                      |  [ Confirm and Pay ]      |
+----------------------+---------------------------+
```

Order detail with tracking (customer):

```text
| Order DLV-20261027-0001      Status badge: RIDER ASSIGNED |
| Timeline: Placed > Paid > Rider assigned > Picked up > Delivered |
| Map (route)         | Rider: name, plate, phone, [Track link] |
| Parcel and fee summary | Payment: Paid via GCash          |
| [ Cancel order ] (only before pickup)                      |
```

Dispatcher board:

```text
| Counters: Today 24 | Awaiting booking 2 | In transit 6 | Failed 1 |
| Filters: status | date | search order no. or customer          |
| Table: Order | Customer | Pickup > Drop-off | Status | Rider | Actions |
|        Actions: View, Rebook, Reassign, Cancel                       |
```

Admin dashboard: stat cards (orders, revenue today and month, failed payments), a line chart of orders per day, a table of latest API errors, and links to users, riders, settings and reports. Reports page: date range, status and rider filters, results table, buttons for CSV and PDF.

### 3.7 Design system

- Bootstrap 5, one custom `app.css`. Primary color deep blue `#1F4FD8`, accent amber `#F5A623`, success green, danger red, neutral grays. System font stack.
- Status badges: draft gray, pending_payment amber, paid blue, booking blue, rider_assigned indigo, picked_up purple, delivered green, cancelled dark gray, failed red. Always badge text plus color (not color alone).
- Layout: top navbar, container, cards with 8 px radius, 16 px spacing scale. Tables become stacked cards under 576 px.
- Forms: labels above inputs, inline validation messages, disabled submit button while a request is running.
- Errors: friendly alert with a short message and a retry action; never show raw API errors to users.
- Accessibility: contrast at least 4.5:1, visible focus, labeled inputs, keyboard-usable map controls (typed address fallback).
- Performance target: main pages load in about 3 seconds; load the Maps script only on map pages.

## 4. Build tasks (execute in order)

| # | Task | Deliverables | Acceptance check |
| --- | --- | --- | --- |
| T0 | Project setup | LavaLust installed, Git repo, `.env.example`, `.gitignore`, `ApiClient` base class, `api_logs` writer, `docs/DECISIONS.md` | App boots; a test call through `ApiClient` writes a log row |
| T1 | Design artifacts | Files from section 3 under `docs/` and `database/schema.sql`, `database/seed.sql` | Schema imports with no errors; seed loads 1 admin, 1 dispatcher, 3 customers, 3 riders, 10 orders |
| T2 | Auth and roles | `Auth` controller, `auth_helper`, CSRF, login throttling, admin screens for users and riders | Each role sees only its menu; customer opening `/admin` gets 403 |
| T3 | Google Maps service | `GoogleMapsService::route()`, Places autocomplete (country PH), map preview, `fee_helper` with rates in `/admin/settings` | Valid addresses return distance, duration and fee; invalid input shows a clear error |
| T4 | Order management | Create (transaction), list with filters, detail, cancel, `set_status` with state machine, `deliveries` log | Illegal transitions are rejected; every change adds a log row |
| T5 | Lalamove service | Signed requests, `getQuote`, `placeOrder`, `cancelOrder`, `/webhooks/lalamove`, rebook, `retry_failed.php` | Sandbox: a booked order reaches `rider_assigned` and driver details are saved |
| T6 | PayMongo service | Checkout session, success and cancel pages, `/webhooks/paymongo` with signature check and idempotency | Test-mode payment sets `paid` with no manual step and triggers T5 booking; duplicate webhook does nothing |
| T7 | Notifications | `SemaphoreService`, `FcmService`, service worker, token saving, `NotificationService` templates | Each status change sends the SMS and push listed in the template table; a forced SMS failure does not break the order |
| T8 | Dashboards and reports | Customer, dispatcher, admin dashboards, status polling, delivery and payment reports with CSV and PDF export | Report totals equal hand-computed totals from seed data |
| T9 | Hardening and tests | Security checklist applied, PHPUnit or manual test script, `docs/TEST_RESULTS.md` with the three required cases plus edge cases | All tests pass; no secret in the repo history |
| T10 | Deployment and handoff | `docs/DEPLOYMENT.md`, cron jobs, backup script, user guides for the three roles, README | A fresh clone plus the README steps runs the system |

For each task, in this order: (1) restate the task in one sentence, (2) implement, (3) run the acceptance check and capture the result, (4) fix failures, (5) commit, (6) append a progress line to `docs/PROGRESS.md`.

Notification templates (SMS under 160 characters), for reference:

| Status | SMS to customer |
| --- | --- |
| pending_payment | Order {no} received. Pay PHP {fee} to confirm: {link} |
| paid | Payment received for {no}. Finding your rider now. |
| rider_assigned | {no}: rider {name} ({plate}) assigned. Track: {link} |
| delivered | {no} delivered. Thank you! |
| failed or cancelled | {no} could not be completed. Contact support: {email} |

## 5. Definition of done and reporting

The project is done when all of these hold:

- [ ] All seven functional requirements work end to end in sandbox and test mode: roles and login, map-based order with fee, Lalamove booking, PayMongo payment with automatic status, SMS and push on each status, tracking and history, reports.
- [ ] Non-functional: HTTPS-ready, passwords hashed, role checks, validation, keys only in `.env`, pages load in about 3 seconds, API failures show a clear error, retry and log.
- [ ] Test cases pass: (1) valid addresses give route, distance and correct fee; (2) paying in PayMongo test mode sets `paid` and triggers booking; (3) Rider Assigned then Delivered sends SMS and push and updates the status log. Plus: duplicate webhook, API timeout during booking, cancel after payment, access to another customer's order.
- [ ] `docs/` holds use case, architecture, ERD, state machine, wireframe notes, test results, deployment guide and user guides.
- [ ] README explains install, `.env` setup, running locally with an HTTPS tunnel for webhooks, and cron setup.

**What to report back after each task**: files created or changed, acceptance check result (pass or fail with evidence), assumptions added to `DECISIONS.md`, and anything blocked (for example a missing API key). Do not claim a task is done if its acceptance check was not run.

**Out of scope for this version**: rider mobile app, live GPS, proof of delivery, multiple couriers, analytics beyond the listed reports.
