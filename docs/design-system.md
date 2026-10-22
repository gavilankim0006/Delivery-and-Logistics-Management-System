# Dispatch design system

This document records the shared visual language for Delivery & Logistics Management, based on sections 3.8 and 3.9 of `AI_Agent_Brief_Delivery_Logistics_REVISED.md`. The Control Tower direction is the default across customer, dispatcher, administrator, authentication, address, order, and profile pages: calm precision through a mist-gray canvas, white work surfaces, deep blue-gray text, and a clear teal route accent.

## Foundations

### Color tokens

`public/css/app.css` is the source of truth for the palette:

| Token | Value | Use |
| --- | --- | --- |
| `--ink` | `#172B3A` | Headings and primary text |
| `--ink-2` | `#4C6070` | Secondary body text and navigation |
| `--ink-3` | `#617687` | Hints and placeholders |
| `--route` | `#087F98` | Primary actions, links, focus and route tracker |
| `--route-dark` | `#05687E` | Hover and pressed actions |
| `--amber` | `#E8A33D` | Attention-state accent; pair text with `--amber-ink` |
| `--paper` | `#EEF2F5` | Page background |
| `--surface` | `#FFFFFF` | Navigation, cards, inputs and floating layers |
| `--line` | `#D3DDE5` | Borders and dividers |
| `--success` | `#146443` | Success state |
| `--danger` | `#A12C2A` | Error and destructive state |
| `--amber-ink` | `#704400` | Readable text on attention-state backgrounds |

Status-specific foreground/background aliases provide readable tints. Status meaning is conveyed by text and a marker as well as hue; do not use the route accent to imply success or failure.

### Type

Use Manrope at weights 400, 500 and 700 only, loaded from Google Fonts with `display=swap`; fall back to a system sans-serif stack. The scale is 12, 14, 16, 20, 24 and 32 px. Body line height is 1.5; headings use 1.2 and slight negative letter spacing from 24 px upward. Use tabular numerals for order numbers, money and distances. Write sentence case and keep line lengths below 80 characters.

### Shape, spacing and layout

Use radii `--r-sm: 6px`, `--r-md: 10px` and `--r-pill: 999px`. The spacing steps are `--s-1: 4px`, `--s-2: 8px`, `--s-3: 16px`, `--s-4: 24px`, `--s-5: 32px` and `--s-6: 48px`. The centered content width is at most 1120 px, with 16 px mobile and 32 px desktop gutters. A page has a clear title and one primary action, aligned at opposite ends when appropriate. Use whitespace to separate sections; use cards only to group related content. Cards have a 1 px border, white surface, 10 px radius and no shadow. Reserve the single `--shadow-pop` elevation for floating menus, modals and toasts.

## Shared components

| Component | Contract |
| --- | --- |
| Navbar | White, 64 px tall, hairline lower border, Dispatch wordmark and role-aware existing destinations. Active destination has a 2 px route-color underline. |
| Buttons | Minimum 40 px high (44 px on touch layouts), 6 px radius. Primary uses route teal; secondary uses white and a line border. Destructive actions remain text links until confirmation. Provide visible hover/focus/disabled/loading states. |
| Inputs | 40 px high, white, 1 px line border; visible label above and hint/error below. Focus uses a 3 px route-color ring. Invalid fields use danger border and concise guidance while preserving entered values. |
| Status chips | Pill shape, subtle status tint, readable foreground, status dot and sentence-case label. Draft: gray; pending payment: amber; paid/booking: blue; rider assigned: indigo; picked up: violet; delivered: green; cancelled: slate; failed: red. Never rely on color alone. |
| Tables | No zebra striping; hairline row dividers; 56 px rows; 14 px secondary-color headers in sentence case; paper-color hover. At widths below 576 px, stack rows into labeled cards. Route cells keep addresses readable and allow long values to wrap. |
| Empty states | Short helpful sentence and, where available, one primary action. A simple route-line SVG is the only illustration. |
| Notices | White surface and a 3 px colored leading edge, with accessible text and a dismiss control where appropriate. |
| API response status | A semantic, tinted status pill distinguishes successful responses from failures without solid Bootstrap badges. |

Shared semantic view hooks are `.ds-page`, `.ds-page-header`, `.ds-panel`, `.ds-segmented`, `.ds-segment`, `.ds-metric`, `.ds-table`, `.ds-status-chip` and `.ds-notice`. These supplement the baseline Bootstrap `.card` and `.table` defaults; keep semantic hooks consistent across role-specific pages.

### Dispatch workspace

The dispatch board uses the same global palette and shared component tokens as every other role. Its additional styles are limited to workspace layout: a four-part summary filter, a date/status toolbar, a wide table at intermediate widths, and stacked route addresses on narrow screens. It does not add a separate color scheme or override shared status semantics.

## Route tracker

`app/views/partials/route_tracker.php` renders the signature five-stage progress element: Placed, Paid, Rider assigned, Picked up and Delivered. A thin line joins labeled nodes; reached nodes and line segments use route teal, the current node has a static halo, and upcoming nodes remain hollow. Stages and timestamps are derived from the order's delivery history. If the order is cancelled or failed, progression ends at the last reached stage, that stage receives the danger treatment and the outcome is labeled. At 768 px and wider the tracker is horizontal; below that it is vertical. Each stage has an accessible text label and state, not color alone. The same visual language can be reused as a compact order-list progress indicator and as a small route-line accent in login and empty states.

## Standalone page scope

The routed application's standalone 403 and 404 views use the shared Control Tower stylesheet but do not render the authenticated app shell. PHP/database/exception diagnostic templates remain purpose-built so diagnostic content and framework behavior are not obscured. The framework welcome page is not the routed application landing page and is outside this product theme contract.

## Interaction, accessibility and copy

Motion is limited to 150 ms ease-out feedback on controls, a restrained modal fade/scale, toast entrance and a 400 ms tracker fill on a newly observed status. Disable transitions under `prefers-reduced-motion`. Every control has a visible keyboard focus state; form fields are explicitly labeled; meaningful contrast is at least 4.5:1 for text and 3:1 for meaningful icons/borders; touch controls target 44 px. Use concise customer-facing terms (“delivery”, “order”) rather than internal identifiers or status codes. Explain errors in plain language, with a next step and without raw API, PHP or SQL details. Avoid inline styles and raw color values in view markup.

## Review checklist

- Manrope only, at weights 400, 500 and 700.
- Color, radius and spacing values come from the defined tokens.
- One primary button per view; destructive actions require confirmation.
- No shadows except floating layers; no decorative gradients or imagery.
- Amber denotes attention states and uses a readable foreground.
- Lists include a helpful empty state; forms explain errors and preserve values.
- Route tracker appearance and data semantics are shared wherever used.
- Keyboard focus and reduced-motion preferences are respected.
- Review at 375, 768 and 1280 px widths; capture screenshots under `docs/ui-review/` when conducting the visual review.
