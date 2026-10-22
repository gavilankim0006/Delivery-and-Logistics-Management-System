# AI Agent Brief: Delivery & Logistics System (Revised for Instructor Checkings)

Paste this whole brief into a coding agent (Claude Code, Cursor, etc.) as its standing instructions. It defines the mission, the design plan, and a build order **organized around the instructor's three graded checkings**. Companion: the human Build Guide.

**What changed from the previous version:** the UI direction was redesigned for a premium but simple look (sections 3.8 and 3.9: design tokens, one typeface, hairline borders, a signature route tracker, simplified wireframes, and a review checklist). Also, the build order is now split into phases that match Checking 1 (Planning and Setup), Checking 2 (Database and Backend) and Checking 3 (CRUD). Each phase has the rubric, the required demonstration, and an acceptance check. A site map was added. Real database records must be displayed from the start, full CRUD with validation and delete confirmation is a hard requirement, and the external API integrations were moved to Phase D (after the three checkings) so they cannot block the graded work.

## 1. Role and mission

You are a senior full-stack PHP engineer. Build a **web-based Delivery and Logistics Management System** in **PHP 8.1+ with the LavaLust MVC framework, MySQL, JavaScript and Bootstrap 5**. Users are small delivery businesses (admin, dispatcher) and their customers. Riders are represented by Lalamove drivers; there is no rider app in this version.

The system is graded by an instructor in three checkings (section 4). **Passing each checking comes first.** After the checkings, integrate external services behind provider interfaces, each with a real and mock driver selected through `.env`: **Leaflet + OpenStreetMap (Nominatim, OpenRouteService)** for map display, address search, route and distance; **Lalamove** for rider quotation and booking; **PayMongo** for online payment; **Semaphore** as an optional SMS provider (SMS defaults to log mode); and **Firebase Cloud Messaging** for push.

## 2. Working rules

1. Work phase by phase (section 4). Finish and verify one phase before starting the next. After each task, commit with a clear message.
2. **Everything shown on a page must come from the database.** No hardcoded or static sample records in views (seed data in `database/seed.sql` is fine, because it lives in the database).
3. Build and test each external API alone first (small CLI or test controller against sandbox/test mode), then wire it into the order flow. Never call an API directly from a controller: put every external service behind a provider interface in `app/libraries/`, with a real driver and a mock driver selected through `.env`. Real HTTP drivers use the shared `ApiClient` (cURL, 10 s timeout, 2 retries on 5xx or timeout, every call logged to `api_logs`); mock drivers implement the same interface without requiring credentials. Applies in Phase D.
4. Secrets live only in local `.env` (loaded with phpdotenv); never ask for secrets in chat, hard-code them, or print them. Database credentials also come from `.env`. Commit `.env.example` with variable names only and no populated values. Keep `.env` and Firebase service-account JSON in `.gitignore`.
5. Only `Order_model::set_status()` may change `orders.status`. It validates the transition, writes a row to `deliveries`, and (from Phase D) calls `NotificationService`.
6. Money is stored as DECIMAL and sent to PayMongo in centavos. The fee is always computed on the server.
7. Webhooks verify their signature, are idempotent through `webhook_events`, and return HTTP 200 only after processing.
8. A failed SMS or push must never roll back an order. Log it and continue.
9. **Security and data-handling baseline on every page and form:** prepared statements (query builder bindings), server-side validation for every field, output escaping, CSRF tokens, `password_hash`, role check (deny by default), session regeneration at login.
10. **Every form must handle bad input gracefully:** required fields, invalid formats, out-of-range values, duplicates, and database errors. Show inline messages, keep the user's input, and never show a raw PHP or SQL error. Empty tables show a friendly "No records yet" state.
11. If an API detail is uncertain (endpoint, header, field name), read the provider's current official documentation instead of guessing, and note the source in a code comment.
12. If a requirement is ambiguous, pick the simplest reasonable option, write it in `docs/DECISIONS.md`, and continue. Do not stop to ask unless a credential or external account is missing.
13. Never touch live payment or booking credentials. Use only Lalamove sandbox and PayMongo test keys.
14. Do not add features outside the scope below.
14a. **Follow the design system in 3.9 exactly.** Premium feel comes from restraint: use only the tokens, components and copy rules defined there. No inline styles, no raw hex values in views, no extra libraries for visuals.
15. Keep a **demo-ready state** at the end of every phase: the app runs locally with `php -S` or Apache/XAMPP, seed data loaded, no major errors, and a short demo script in `docs/DEMO_CHECKING_N.md` (what to click, in order, in about 5 minutes).

## 3. Design plan (follow this; save each item under `docs/` as you create it)

### 3.1 System requirements: major features and functions

Save as `docs/requirements.md`. List each feature with the actor and the page/route that delivers it.

| # | Feature | Actor | Phase delivered |
| --- | --- | --- | --- |
| F1 | Register, log in, log out, role-based menus | All | B |
| F2 | Manage orders: create, list/filter, view, edit (while draft), delete/cancel | Customer, Dispatcher, Admin | C |
| F3 | Manage saved addresses (CRUD) | Customer | C |
| F4 | Manage users, riders and fee settings (CRUD) | Admin | C |
| F5 | Order status workflow with delivery log (state machine) | System, Dispatcher | B/C |
| F6 | Map-based address search, route, distance and fee | Customer | D |
| F7 | Lalamove rider quotation and booking, driver details | System, Dispatcher | D |
| F8 | PayMongo online payment with automatic status | Customer, System | D |
| F9 | SMS and push notifications on each status | System | D |
| F10 | Dashboards, tracking, reports (CSV, PDF) | All | D |

### 3.2 Actors and use cases

| Actor | Use cases |
| --- | --- |
| Customer | Register, log in, manage saved addresses, create order, edit draft order, pay online, track order, view history, cancel before pickup, receive SMS and push |
| Dispatcher | View all orders, filter, rebook failed bookings, cancel, view rider details, receive urgent push |
| Admin | Everything the dispatcher does, plus manage users, riders and fee settings, view reports and API logs |
| Lalamove (system) | Sends driver and status webhooks |
| PayMongo (system) | Sends payment webhooks |

Deliverable: `docs/use-case.puml` (PlantUML) or an image rendered from it.

### 3.3 Site map and navigation (required for Checking 1)

Save as `docs/sitemap.md` using a Mermaid `flowchart`, plus a short table of "page, who can open it, linked from".

```mermaid
flowchart TD
  Home[/ Home or Login /] --> Login[/login]
  Home --> Register[/register]
  Login --> CustomerArea
  Login --> StaffArea
  subgraph CustomerArea[Customer]
    CD[/orders  My Orders/] --> CN[/orders/new/]
    CD --> CV[/orders/id  Detail/]
    CV --> CE[/orders/id/edit/]
    CV --> CP[/orders/id/pay/]
    CA[/addresses/] --> CAN[/addresses/new/]
    CA --> CAE[/addresses/id/edit/]
    CPR[/profile/]
  end
  subgraph StaffArea[Dispatcher and Admin]
    DB[/dispatch  Live board/] --> SV[/orders/id  Staff view/]
    AD[/admin  Dashboard/] --> AU[/admin/users/]
    AD --> AR[/admin/riders/]
    AD --> AS[/admin/settings/]
    AD --> ARP[/admin/reports/]
    AD --> AL[/admin/logs/]
  end
```

Navigation rules: a top navbar whose links depend on the role; a breadcrumb on detail and edit pages; every create/edit form has a Cancel link back to its list; the logo always returns to the role's home page.

### 3.4 Architecture

```text
Browser (Bootstrap, JS, Leaflet + OpenStreetMap tiles, FCM web SDK)
   |  HTTPS
LavaLust app
   Controllers -> Models -> MySQL
        |
        +-> Services: MapsService, CourierService, PaymentService, SmsService (log/semaphore drivers), PushService (each has a provider interface and real/mock drivers selected by `.env`; real HTTP drivers use ApiClient -> api_logs) [Phase D]
        +-> NotificationService (templates per status; SMS + push)                                  [Phase D]
   Webhook endpoints: /webhooks/paymongo, /webhooks/lalamove  (public, signature-verified)          [Phase D]
   Cron: backup.sh (daily), retry_failed.php (every 5 min)                                          [Phase D]
```

Deliverable: `docs/architecture.md` with this diagram redrawn as Mermaid, marking which parts exist at each checking. Also document the LavaLust folder layout actually used (`app/controllers`, `app/models`, `app/views`, `app/libraries`, `app/helpers`, `app/config/routes.php`).

### 3.5 Database design (ERD) (required for Checking 1)

Tables: `roles`, `users`, `customers`, `riders`, `addresses`, `orders`, `parcels`, `payments`, `deliveries`, `notifications`, `sms_logs`, `geocoding_cache`, `api_logs`, `webhook_events`, `settings`. `sms_logs` stores recipient, message body, timestamp, status and an optional order reference. `geocoding_cache` stores a normalized address/query key, provider, cached result (including coordinates), and cache timestamps/expiry; index the lookup key. Include both tables and all fields, keys, nullability and relationships in `database/schema.sql`, `docs/erd.mmd`, `docs/erd.png` and `docs/data-dictionary.md`.

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
| geocoding_cache | independent cache of geocoder queries and results |
| orders to sms_logs | one to many (message audit trail; order reference may be nullable) |

**The ERD deliverable must show, for every table: all fields with data types, the primary key, foreign keys, and unique/NOT NULL markers.** Also write `docs/data-dictionary.md`: one table per entity with columns *field, type, key, null, description*.

Key columns: `orders.lalamove_quotation_id`, `orders.lalamove_order_id`, `orders.share_link`, `payments.paymongo_session_id`, `payments.paymongo_payment_id`, `users.fcm_token`. Use `created_at`/`updated_at` on main tables and `deleted_at` (soft delete) on `users`, `riders`, `addresses` and `orders`. Write the full DDL to `database/schema.sql` with indexes on `orders(status)`, `orders(customer_id)`, `deliveries(order_id)`. Deliverables: `docs/erd.mmd` (Mermaid `erDiagram`) and a rendered image `docs/erd.png`.

### 3.6 Order state machine (enforce in `set_status`)

| From | Allowed next | Trigger |
| --- | --- | --- |
| draft | pending_payment, cancelled | Customer confirms order |
| pending_payment | paid, cancelled, failed | PayMongo webhook or timeout |
| paid | booking, cancelled | Payment verified |
| booking | rider_assigned, failed, cancelled | Lalamove order placed, then driver webhook |
| rider_assigned | picked_up, cancelled, failed | Lalamove webhook |
| picked_up | delivered, failed | Lalamove webhook |
| delivered, cancelled, failed | none (failed may return to booking through staff Rebook) | Terminal |

Before the integrations exist (Checkings 2 and 3), staff and a dev-only "simulate" button on the staff order view may advance statuses by hand through `set_status`, so the workflow can be demonstrated. Remove or hide the simulate button in Phase D.

### 3.7 Pages and routes

| Role | Pages |
| --- | --- |
| Public | `/login`, `/register` |
| Customer | `/orders/new`, `/orders`, `/orders/{id}`, `/orders/{id}/edit`, `/orders/{id}/delete`, `/orders/{id}/pay`, `/addresses` (+ `/new`, `/{id}/edit`, `/{id}/delete`), `/profile` |
| Dispatcher | `/dispatch` (live board), `/orders/{id}` (staff view with actions) |
| Admin | `/admin` (dashboard), `/admin/users` (+ new, edit, delete), `/admin/riders` (+ new, edit, delete), `/admin/settings` (fee rates), `/admin/reports`, `/admin/logs`, `/admin/sms-logs` |
| Webhooks and AJAX (Phase D) | `POST /webhooks/paymongo`, `POST /webhooks/lalamove`, `GET /orders/{id}/status` (JSON for polling), `POST /users/save_token` |

All routes are defined in `app/config/routes.php`. Create, update and delete actions are `POST` only and check the CSRF token.

### 3.8 Wireframes (required for Checking 1; build the important screens in Bootstrap; mobile-first)

Save the wireframes as `docs/wireframes.md` (ASCII below, or exported images) and then build them as real Bootstrap pages styled with the design system in 3.9. At minimum: Login, Register, New order, Order list, Order detail, Order edit, Dispatcher board, Admin dashboard, Riders list and form.

**Layout principle for every screen:** one job per screen, one primary button, generous whitespace, content grouped by space first and by cards only when grouping is needed (two cards per screen at most). Page header: title on the left, the single primary action on the right.

Login and register (no card chrome, centered column, 360 px wide):

```text
+------------------------------------------------+
|                                                |
|                 (logo)  Dispatch               |
|          o-------o-------o-------o  route line |
|                                                |
|                 Sign in                        |
|          Email     [                  ]        |
|          Password  [                  ]        |
|          [          Sign in           ]        |
|          New here? Create an account           |
+------------------------------------------------+
```

New order (customer). Form on the left, sticky summary on the right; on mobile the summary moves below the form:

```text
+--------------------------------------------------------+
| Dispatch    New order    My orders            (avatar) |
+--------------------------------------------------------+
| New delivery                                           |
|                                                        |
| Pickup                         | +--------------------+ |
|   Address   [ autocomplete ]   | | Map with route     | |
|   Contact   [ name ] [ phone ] | +--------------------+ |
|                                | Distance  7.4 km      |
| Drop-off                       | Time      22 min      |
|   Address   [ autocomplete ]   | ------------------    |
|   Contact   [ name ] [ phone ] | Delivery fee          |
|                                | PHP 152.00            |
| Parcel                         |                       |
|   What are you sending? [    ] | [  Confirm and pay  ] |
|   Weight [  ] kg   Size S M L  |                       |
+--------------------------------------------------------+
```

Until Phase D, the map area is a quiet placeholder and distance is typed in by hand; the fee is computed on the server.

Order detail with the route tracker (customer). The tracker is the signature element of the product (see 3.9):

```text
| DLV-20261027-0001                      ( Rider assigned ) |
|                                                           |
| o-------o-------(o)- - - - -o- - - - - -o                 |
| Placed  Paid    Rider        Picked up   Delivered        |
| 09:12   09:14   assigned     (pending)   (pending)        |
|                 09:20                                     |
|-----------------------------------------------------------|
| Route                          | Rider                    |
|  From  Pickup address          |  Name, plate, phone      |
|  To    Drop-off address        |  [ Open live link ]      |
|  (map)                         | Fee and payment          |
|                                |  PHP 152.00, paid (GCash)|
|-----------------------------------------------------------|
| [ Edit ] (draft only)                 Cancel order (text) |
```

Order list (customer): one row per order, a mini five-segment progress bar in place of a status column on mobile, no zebra stripes.

Dispatcher board. Counters become a segmented filter instead of four stat cards; row actions live in one menu:

```text
| Dispatch                                [ Search order or customer ] |
|                                                                      |
| [ All 24 ] [ Awaiting booking 2 ] [ In transit 6 ] [ Failed 1 ]     |
|                                                  Date: [ Today v ]   |
|----------------------------------------------------------------------|
| Order           Customer     Route             Status      Rider  ...|
| DLV-...-0007    A. Santos    Pickup to Drop    Failed      none   ...|
|                                      (menu: View, Rebook, Reassign, Cancel)
```

Admin dashboard: one summary strip (orders today, revenue this month, failed payments), then a single line chart of orders per day, then a plain list of the latest API errors. Navigation to users, riders, settings and reports lives in the navbar, not in extra link cards. Reports page: filters in one row (date range, status, rider), results table, two buttons (Export CSV, Export PDF).

### 3.9 Design system: premium feel, kept simple

**Design intent.** The product should feel like a calm, well-made courier service: precise, trustworthy, quiet. "Premium" here comes from restraint, not decoration: consistent spacing, one typeface, hairline borders instead of shadows, a small controlled palette, and one memorable element (the route tracker). Simplicity rules: fewer elements per screen, fewer colors, fewer styles.

Save this section as `docs/design-system.md`, and implement the tokens below at the top of `public/css/app.css`. Views may only use these tokens: no inline styles and no raw hex values in views or components.

**Tokens**

```css
:root {
  /* Color */
  --ink:        #14213D;  /* headings, navbar text */
  --ink-2:      #4B5873;  /* body secondary text */
  --ink-3:      #5F6B85;  /* hints, placeholders (still 4.5:1 on white) */
  --route:      #2F5BEA;  /* primary actions, links, tracker */
  --route-dark: #2449C4;  /* primary hover and pressed */
  --amber:      #E8A33D;  /* attention accent: pending states only, never as text */
  --paper:      #F6F7F9;  /* page background */
  --surface:    #FFFFFF;  /* cards, inputs, navbar */
  --line:       #E4E7EC;  /* all borders and dividers */
  --success:    #17794C;
  --danger:     #C8362B;

  /* Shape */
  --r-sm: 6px;   /* inputs, buttons */
  --r-md: 10px;  /* cards, tables, modals */
  --r-pill: 999px; /* status chips */

  /* Spacing scale (use only these) */
  --s-1: 4px; --s-2: 8px; --s-3: 16px; --s-4: 24px; --s-5: 32px; --s-6: 48px;

  /* Elevation: only floating layers (menus, modals, toasts) */
  --shadow-pop: 0 8px 24px rgba(20, 33, 61, 0.12);

  /* Map onto Bootstrap */
  --bs-primary: var(--route);
  --bs-body-color: var(--ink);
  --bs-body-bg: var(--paper);
  --bs-border-color: var(--line);
  --bs-border-radius: var(--r-sm);
  --bs-link-color: var(--route);
}
```

**Typography.** One family: Manrope (weights 400, 500 and 700 only), loaded with `font-display: swap` and a system-font fallback stack. Type scale: 12, 14, 16 (base), 20, 24, 32. Body line-height 1.5, headings 1.2 with slight negative letter-spacing from 24 px up. Money, distances and order numbers use tabular numerals (`font-variant-numeric: tabular-nums`). Sentence case everywhere; no all-caps labels; line length under 80 characters.

**Layout and space.** Container max width 1120 px; page padding 16 px on mobile and 32 px on desktop. Separate sections with space (32 to 48 px), not boxes. Cards are flat: 1 px `--line` border, `--surface` background, `--r-md` radius, 24 px padding, no shadow. Page header: title (24 px, 700) left, one primary button right.

**Components**

| Component | Specification |
| --- | --- |
| Navbar | White, 64 px high, 1 px bottom border, wordmark left, links center or left, user menu right. Active link has a 2 px `--route` underline. No dark bar, no gradient. |
| Buttons | 40 px high (44 px on touch screens), `--r-sm` radius. Primary solid `--route`; secondary white with `--line` border; danger is a plain text link until the confirmation modal, where it becomes solid red. Exactly one primary button per view. Loading state: disabled with a small spinner and "Saving..." text. |
| Inputs | 40 px high, white, 1 px `--line` border, label above, hint below. Focus: 3 px ring in `--route` at 35% opacity. Error: `--danger` border and a short message under the field, with the typed value preserved. |
| Status chips | Pill shape, tinted background with dark text of the same hue, small dot, sentence-case text ("Rider assigned"). Hues: draft gray, pending payment amber, paid and booking blue, rider assigned indigo, picked up violet, delivered green, cancelled slate, failed red. Text always present, contrast at least 4.5:1. |
| Tables | No zebra, hairline row dividers, 56 px rows, header text 14 px in `--ink-2` (not caps), row hover `--paper`. Under 576 px they become stacked cards. |
| Modals | White, `--r-md`, `--shadow-pop`, title states the action ("Delete this address?"), body names the record, buttons "Cancel" (secondary) and the verb ("Delete address", solid red). |
| Toasts | Top right, white with a 3 px colored left edge, auto-dismiss in 5 s, verb matches the action ("Address deleted"). |
| Empty states | One line that invites action plus one primary button ("No orders yet. Create your first delivery." with [New order]). A small route-line SVG is the only illustration. |
| Icons | One set (Bootstrap Icons), 16 to 20 px, used only where they speed up scanning (menu, search, map pin). No emoji in the UI. |
| Imagery | No stock photos, no gradients, no decorative backgrounds. |

**The signature element: route tracker.** Build one reusable partial `app/views/partials/route_tracker.php` and use it on the order detail page, as a compact five-segment bar in order lists, and as a small decorative line on login and empty states.

- A 2 px line in `--line` with nodes for Placed, Paid, Rider assigned, Picked up and Delivered. The completed part of the line and nodes are filled `--route`.
- Current node has a static halo ring (no pulsing). Upcoming nodes are hollow.
- If the order is cancelled or failed, the line stops at the last reached node and that node turns `--danger` with a cross; the label says what happened.
- Under each node: the label and, if reached, the time from the `deliveries` table.
- Horizontal from 768 px up, vertical below. Each node has an accessible text label (not color alone).

**Motion.** Motion only answers an action. Allowed: 150 ms ease-out on hover and focus for buttons, inputs and links; modal fade with a slight scale; toast slide-in; the tracker line filling over 400 ms when polling reveals a new status. Not allowed: entrance animations on page load, hover lifts on cards, parallax, looping animations. Respect `prefers-reduced-motion` by turning all of the above off.

**Copy.** Plain sentence-case language written for the customer, not the system. Buttons say what happens ("Confirm and pay", "Save changes", "Add rider"); the success toast repeats the same verb ("Rider added"). Errors say what went wrong and how to fix it, without apologizing and never with raw API, PHP or SQL text ("We couldn't calculate the route. Check both addresses and try again."). Use "delivery" and "order" consistently; never show internal terms such as webhook, quotation id or status codes to customers.

**Accessibility and performance.** Contrast at least 4.5:1 for text and 3:1 for icons and borders that carry meaning; visible focus on every control; labeled inputs; keyboard-usable map controls with a typed-address fallback; touch targets at least 44 px. Main pages load in about 3 seconds; only Manrope 400, 500 and 700 are loaded; Leaflet assets load only on map pages; no icon font beyond Bootstrap Icons subset or inline SVG.

**Premium checklist (a review gate for every phase).** Capture screenshots at 375, 768 and 1280 px widths into `docs/ui-review/` and confirm each line:

- [ ] One font family and at most three weights across the whole app.
- [ ] One primary button per view; destructive actions only behind a confirmation modal.
- [ ] No shadows except on floating layers; no gradients, emoji or stock photos.
- [ ] Amber appears only for pending or attention states, never as text color.
- [ ] Spacing uses only the 4, 8, 16, 24, 32 and 48 px scale; radius uses only the three shape tokens.
- [ ] Every list has an empty state; every form has an error state with preserved input.
- [ ] Route tracker is identical wherever it appears and driven by the real `deliveries` data.
- [ ] No inline styles or raw hex values in views.

## 4. Build phases aligned to the instructor's checkings (execute in order)

Each checking is worth 100 points. The rubric below is the instructor's; use it as the acceptance test. Do not move to the next phase until the demo script for the current one runs cleanly.

### Phase A: Checking 1, Planning and Initial Setup (100 pts)

**Objective:** a clear system design and a working LavaLust project structure.

| Rubric criterion | Weight | What the agent must produce |
| --- | --- | --- |
| System requirements | 20 | `docs/requirements.md` (3.1), `docs/use-case.puml` (3.2) |
| Site map and navigation | 15 | `docs/sitemap.md` (3.3) |
| Interface design | 20 | `docs/wireframes.md` for the screens in 3.8, `docs/design-system.md` (3.9), tokens implemented in `app.css`, and a polished, consistent premium look on every built page |
| Database design | 20 | `docs/erd.mmd` + `docs/erd.png`, `docs/data-dictionary.md`, `database/schema.sql` (3.5) |
| LavaLust project setup | 25 | Working project with framework structure, routing and navigation (tasks below) |

| # | Task | Deliverables | Acceptance check |
| --- | --- | --- | --- |
| A1 | Project setup | LavaLust installed, Git repo, `.env.example` (variable names only; include `SMS_DRIVER`, `ORS_API_KEY`, provider/mock-driver switches; remove `GOOGLE_MAPS_*`), `.gitignore` (`.env` and Firebase service-account JSON), phpdotenv, `docs/DECISIONS.md` (record Leaflet/OSM + Nominatim/ORS-or-OSRM, log-default SMS, mock/real provider selection, and typed-address fallback decisions), `docs/PROGRESS.md` | App boots locally with no errors or warnings; home route returns a page |
| A2 | Design documents | All Phase A docs listed above | Every rubric row above has a file; Mermaid diagrams render; ERD lists all tables, fields, PKs, FKs |
| A3 | Layout, design system and navigation shell | Shared layout partials, navbar, footer, `app.css` with the 3.9 tokens, `route_tracker`, empty-state, toast and status-chip partials, Manrope loaded; role-aware menu links; `docs/ui-review/` screenshots at 375, 768 and 1280 px | Every link in the site map opens a page (a styled placeholder page is allowed in Phase A only) with no 404 or PHP error; the premium checklist in 3.9 passes |
| A4 | Initial routes | `app/config/routes.php` with all routes from 3.7, controllers with stub methods | `docs/DEMO_CHECKING_1.md` lists each route and its expected result; all behave as listed |

**Required demonstration (write it as `docs/DEMO_CHECKING_1.md`):** run the LavaLust project, show the site map and wireframes, explain the ERD (tables, keys, relationships), and click through the basic navigation and routes.

### Phase B: Checking 2, Database and Backend Implementation (100 pts)

**Objective:** database connection and the initial backend functions, with real data on screen.

| Rubric criterion | Weight | What the agent must produce |
| --- | --- | --- |
| Database implementation | 25 | Database created from `database/schema.sql`; tables and fields match the ERD exactly; `database/seed.sql` loads 1 admin, 1 dispatcher, 3 customers, 3 riders, saved addresses and 10 orders |
| Database connectivity | 20 | LavaLust `app/config/database.php` reads credentials from `.env`; a visible health check (for example `/health` showing "DB connected") |
| Models and queries | 20 | One model per main table extending the LavaLust model, with methods for get-all, get-by-id, filtered list, insert, update, delete; joins for order lists (customer, addresses, rider) |
| Controllers and routes | 20 | Controllers handle GET requests for the main pages and route to the right models and views; sensible HTTP status codes (404 for missing records, 403 for wrong role) |
| Dynamic data display | 15 | Order list, order detail, riders list, users list, addresses list and dispatcher board all render rows from the database |

| # | Task | Deliverables | Acceptance check |
| --- | --- | --- | --- |
| B1 | Create database | `schema.sql` imports cleanly; `seed.sql` loads | Row counts match the seed spec; foreign keys enforced (inserting a bad FK fails) |
| B2 | Connection | Database config via `.env`; `/health` route | `/health` shows connected; stopping MySQL shows a friendly error page, not a stack trace |
| B3 | Auth and roles | `Auth` controller and `auth_helper`: register, login, logout, session regeneration, CSRF, role check, login throttling | Each role sees only its menu; a customer opening `/admin` gets 403; wrong password is rejected with a clear message |
| B4 | Models | `User_model`, `Customer_model`, `Rider_model`, `Address_model`, `Order_model` (with `set_status()` and the state machine), `Delivery_model` | Calling each method from a small test script returns correct rows; illegal status transition is rejected and logged |
| B5 | Read-only pages | Controllers and views for order list (with filters by status and date and search), order detail with delivery log, riders list, users list, addresses list, dispatcher board counters | Pages show seeded records; counters equal counts computed by a SQL query; an unknown order id returns a friendly 404; a customer cannot open another customer's order |
| B6 | Verify database operations | `docs/TEST_RESULTS.md` section for Checking 2: query checks with evidence (SQL output or screenshot) | All checks pass |

**Required demonstration (`docs/DEMO_CHECKING_2.md`):** show the database in phpMyAdmin or CLI with tables and relationships, show the connection working, show a controller, a route and a model query side by side, and show records displayed dynamically on the website. To prove it is dynamic, change one value in the database and refresh the page.

### Phase C: Checking 3, CRUD Operations (100 pts)

**Objective:** the website can create, retrieve, update and delete records using the database.

| Rubric criterion | Weight | What the agent must produce |
| --- | --- | --- |
| Create | 20 | Forms that add records and save them: order (draft), saved address, rider, user |
| Read | 20 | List pages plus individual detail pages for orders, riders and users |
| Update | 20 | Edit forms pre-filled with current data; saves update the correct row only (verified by id) |
| Delete | 20 | Confirmation modal, POST with CSRF, correct row removed or soft-deleted, success message |
| Data handling | 20 | Server-side validation for required fields and invalid input; handling of empty lists, duplicates, missing records, and database errors |

**Main entity for CRUD: Orders.** Supporting CRUD entities: Addresses (customer), Riders and Users (admin). All four must be complete.

| # | Task | Deliverables | Acceptance check |
| --- | --- | --- | --- |
| C1 | Order create | `/orders/new` form (pickup, drop-off, stop contacts, parcel description, weight, size, distance in km entered manually until Phase D); fee computed on the server by `fee_helper` using rates in `settings`; order saved as `draft` inside a transaction with its parcel and a first `deliveries` row | New order appears in the list and in the database with the correct fee and a log row |
| C2 | Order read | List with filters and pagination; detail page with parcel, fee, addresses, status timeline and delivery log | Details match the database row exactly |
| C3 | Order update | `/orders/{id}/edit` allowed only while status is `draft` (owner or staff); other statuses show a friendly "cannot edit" message; fee is recomputed on save | Editing changes only that order; editing a non-draft order is rejected server-side even if the URL is typed manually |
| C4 | Order delete | Draft orders can be deleted (soft delete) by the owner; non-draft orders cannot be deleted and offer **Cancel order** through `set_status` instead; confirmation modal always shown | Deleted draft disappears from lists but the row remains with `deleted_at`; non-draft delete attempt is blocked |
| C5 | Address CRUD | Customer addresses: add, list, edit, delete with confirmation; deleting an address used by an order is blocked with a friendly message | All four operations work; FK safeguard verified |
| C6 | Rider and user CRUD (admin) | Riders: add, list, view, edit, delete (soft); Users: add, list, view, edit (role change, active flag), delete (soft); admin cannot delete themselves | All four operations work for each; duplicate email is rejected with a clear message |
| C7 | Validation and error handling | A shared validation approach (LavaLust form validation or a small helper): required, max length, email, phone (PH format), numeric ranges (weight, distance greater than 0), size in (S, M, L), unique email | Submitting empty, too long, wrong-type and duplicate values shows inline errors, keeps typed values, and writes nothing to the database; forced database error shows a friendly alert |
| C8 | Verify and record | `docs/TEST_RESULTS.md` section for Checking 3: table of cases (create, read, update, delete, invalid input, empty list, missing id, forbidden access) with pass/fail and evidence | All pass; changes made through the website are visible in the database |

**Required demonstration (`docs/DEMO_CHECKING_3.md`):** add a new record, display and view existing records, update a record, delete a record (showing the confirmation), and submit invalid and incomplete forms to show the error handling. After each action, show the matching database row.

### Phase D: Integrations and completion (after the three checkings)

Do this only after Phases A to C are verified. Keep all earlier features working; re-run the Checking 3 demo script after each task.

| # | Task | Deliverables | Acceptance check |
| --- | --- | --- | --- |
| D1 | `ApiClient` and `api_logs` | Base class (10 s timeout, 2 retries, logging); `/admin/logs` page | A test call through `ApiClient` writes a log row |
| D2 | Maps service | `MapsService::route()` behind `MapsProviderInterface`, with a real Nominatim + configurable OpenRouteService (`ORS_API_KEY`) or OSRM driver and a mock driver selected through `.env`; Leaflet + OpenStreetMap map preview; Nominatim address autocomplete restricted to the Philippines, debounced, limited to one request per second, sent with a proper User-Agent, and cached in `geocoding_cache`; replace the manual distance field with route distance; rates stay in `/admin/settings`; keep typed-address fallback with a clear message if geocoding or routing fails | Valid Philippine addresses return route, distance, duration and correct fee using Nominatim + ORS; geocoder/router failure leaves typed addresses usable and explains the fallback |
| D3 | Lalamove service | `CourierProviderInterface` with `COURIER_DRIVER=mock` as the default and optional real Lalamove driver; mock returns v3-shaped quotations, order IDs, driver details and webhook events without credentials; `getQuote`, `placeOrder`, `cancelOrder`, `/webhooks/lalamove`, admin/dev simulator events routed through the same webhook processor, rebook, `retry_failed.php`; Lalamove keys are optional and needed only for authorized sandbox testing | Mock flow completes without credentials; sandbox: a booked order reaches `rider_assigned` and driver details are saved |
| D4 | PayMongo service | `PaymentProviderInterface` with real PayMongo and mock drivers; checkout session, success and cancel pages, `/webhooks/paymongo` with signature check and idempotency | Mock flow works without credentials; test-mode payment sets `paid` automatically and triggers D3 booking; duplicate webhook does nothing |
| D5 | Notifications | `SmsProviderInterface` with `LogSmsDriver` (DEFAULT; sends nothing, records recipient/body/timestamp/status in `sms_logs` and a log file) and optional `SemaphoreSmsDriver`, selected by `.env` using `SMS_DRIVER=log` (default) or `SMS_DRIVER=semaphore`; `PushProviderInterface` with real FCM and mock drivers; service worker, token saving, `NotificationService` calls interfaces only; admin `/admin/sms-logs` page | Log mode writes SMS rows and the order still completes; real/mock push and SMS drivers are selectable; a forced SMS or push failure does not break the order |
| D6 | Dashboards and reports | Customer, dispatcher and admin dashboards, status polling, delivery and payment reports with CSV and PDF export; remove the dev "simulate" button | Report totals equal hand-computed totals from seed data |
| D7 | Hardening and tests | Security checklist applied, PHPUnit or manual test script, `docs/TEST_RESULTS.md` completed with the three required cases plus edge cases: (1) valid Philippine addresses return a route, distance and correct fee using Nominatim + ORS; (2) PayMongo test-mode payment sets `paid` and triggers booking; (3) Rider Assigned then Delivered sends SMS and push and updates the status log; additionally verify SMS log mode writes to `sms_logs` while the order completes, geocoder/router failure preserves typed-address fallback with a clear message, forced SMS/push failures do not roll back orders, duplicate webhook, API timeout during booking, cancel after payment, cross-customer access denial, used-address deletion block and non-draft edit rejection | All tests pass; no secret in the repo history |
| D8 | Deployment and handoff | `docs/DEPLOYMENT.md`, cron jobs, backup script, user guides for the three roles, README | A fresh clone plus the README steps runs the system |

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

### 5.1 Checking gates (must be true before starting the next phase)

- [ ] **Checking 1 ready:** requirements, site map, wireframes, design system implemented in `app.css`, ERD with fields and keys, project runs locally, routes and navigation work, `DEMO_CHECKING_1.md` rehearsed.
- [ ] **Checking 2 ready:** database built from the ERD, connection proven, models and controllers in use, real records displayed, `DEMO_CHECKING_2.md` rehearsed.
- [ ] **Checking 3 ready:** create, read, update and delete all work for orders (plus addresses, riders, users), delete asks for confirmation, invalid input is handled, database reflects every change, `DEMO_CHECKING_3.md` rehearsed.

### 5.2 Final project

- [ ] All functional requirements work end to end in sandbox and test mode: roles and login, map-based order with fee, Lalamove booking, PayMongo payment with automatic status, SMS and push on each status, tracking and history, reports.
- [ ] Non-functional: HTTPS-ready, passwords hashed, role checks, validation, keys only in `.env`, pages load in about 3 seconds, API failures show a clear error, retry and log.
- [ ] Test cases pass: (1) valid Philippine addresses give route, distance and correct fee using Nominatim + ORS; (2) paying in PayMongo test mode sets `paid` and triggers booking; (3) Rider Assigned then Delivered sends SMS and push and updates the status log. Also verify SMS log mode writes to `sms_logs` and the order still completes; geocoder/router failure retains typed-address fallback with a clear message; forced SMS/push failures never roll back an order; duplicate webhook, API timeout during booking, cancel after payment, cross-customer access denial, used-address deletion block and non-draft edit rejection.
- [ ] The premium checklist (3.9) passes on every screen at 375, 768 and 1280 px, with screenshots in `docs/ui-review/`.
- [ ] `docs/` holds requirements, use case, site map, architecture, ERD, design system, data dictionary, state machine, wireframes, demo scripts, test results, deployment guide and user guides.
- [ ] README explains install, `.env` setup, database import (schema and seed), running locally, running with an HTTPS tunnel for webhooks, and cron setup.

### 5.3 Reporting after each task

Report: files created or changed, acceptance check result (pass or fail with evidence), assumptions added to `DECISIONS.md`, and anything blocked (for example a missing API key). Do not claim a task is done if its acceptance check was not run. At the end of each phase, also report the **self-scored rubric** for that checking (each criterion, points claimed out of its weight, and the evidence file).

**Out of scope for this version**: rider mobile app, live GPS, proof of delivery, multiple couriers, analytics beyond the listed reports.
