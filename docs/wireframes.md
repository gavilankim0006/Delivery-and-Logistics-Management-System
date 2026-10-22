# Dispatch wireframes

These mobile-first wireframes translate sections 3.8 and 3.9 of `AI_Agent_Brief_Delivery_Logistics_REVISED.md` into page structures. Each screen has one primary job and one primary action. Use generous whitespace, group by spacing before adding cards, and keep to at most two cards per screen. On narrow screens, multi-column layouts stack with the primary content first.

## Login

Centered 360 px column without card chrome. The route-line motif is a quiet decorative accent, not a progress control.

```text
+----------------------------------------------+
|                                              |
|                 Dispatch                     |
|          o-------o-------o-------o            |
|                                              |
|                 Sign in                      |
| Email       [                            ]    |
| Password    [                            ]    |
|             [          Sign in           ]    |
|          New here? Create an account          |
+----------------------------------------------+
```

## Register

Use the same centered, no-card column as login. Provide labeled name, email, phone and password fields, one primary “Create account” action, then a sign-in link. Keep validation messages adjacent to their fields.

## New order

Pickup, drop-off and parcel details form the main column. Route preview and fee summary form a secondary column that moves below the form on mobile. Until map routing is enabled, retain a quiet map placeholder and a typed distance field; the delivery fee remains server-computed.

```text
+----------------------------------------------------------+
| Dispatch       New order     My orders          (account) |
+----------------------------------------------------------+
| New delivery                                             |
|                                                          |
| Pickup                         | +----------------------+ |
|  Address [autocomplete]        | | Map / route preview  | |
|  Contact [name] [phone]        | +----------------------+ |
|                                | Distance       7.4 km  |
| Drop-off                       | Estimated time 22 min  |
|  Address [autocomplete]        | ---------------------- |
|  Contact [name] [phone]        | Delivery fee           |
|                                | PHP 152.00             |
| Parcel                         |                        |
|  Description [             ]   | [ Save draft ]         |
|  Weight [  ] kg   Size S M L   | Payment not implemented |
+----------------------------------------------------------+
```

## Order list

Show one row per order, plain hairline separators, no zebra stripes. Include order number/date, route, status, fee and a detail action. On mobile, present each row as a labeled stacked card and replace the full status column with a compact five-segment tracker. Filters are labeled and remain available on mobile. Empty results show a helpful message and a single relevant action.

```text
| My orders                               [ New order ]    |
| Status [All v]  Date [       ] Search [           ]     |
|----------------------------------------------------------|
| DLV-...-0001 · Oct 27                                   |
| Pickup → Drop-off     [route · · · ·]    PHP 152.00     |
|----------------------------------------------------------|
| DLV-...-0002 · Oct 26                                   |
| Pickup → Drop-off     [route route · ·]  PHP 210.00     |
```

## Order detail

Lead with the order number and a readable status chip. Follow with the reusable five-stage route tracker backed by delivery history. Group route and parcel/payment information in no more than two sections. Show rider information when assigned. Draft-only edit and order cancellation actions are secondary; cancellation requires confirmation.

```text
| DLV-20261027-0001                 [Rider assigned]       |
|                                                          |
| o-------o-------(o) - - - -o - - - -o                   |
| Placed  Paid    Rider       Picked up  Delivered          |
| 09:12   09:14   09:20       pending    pending            |
|----------------------------------------------------------|
| Route                           | Rider                  |
| From  Pickup address            | Name, plate, phone     |
| To    Drop-off address          | [ Open live link ]    |
|                                 | Fee and payment        |
|                                 | PHP 152.00, paid       |
|----------------------------------------------------------|
| [ Edit order ]                       Cancel order         |
```

## Order edit

Reuse the New order field grouping, prefilled with current values. Clearly indicate that only draft orders can be edited. “Save changes” is the sole primary action; “Cancel” returns to the order list/detail without changing data. Show field-level validation next to invalid inputs.

## Dispatcher board

Use a page header with search, then a segmented status filter rather than four statistic cards. Place date filter with the segments. Use a compact table with order, customer, route, status, rider and one row action menu. Mobile rows stack into cards; avoid duplicating each action as a separate button.

```text
| Dispatch                   [ Search order or customer ]  |
|                                                          |
| [ All 24 ] [ Awaiting booking 2 ] [ In transit 6 ] [Failed 1]
|                                      Date: [Today v]     |
|----------------------------------------------------------|
| Order       Customer    Route        Status     Rider  … |
| DLV-...-7   A. Santos   Pickup → ... Failed     —      … |
|                              Actions: View, Rebook, Cancel|
```

## Admin dashboard

Use one summary strip for orders today, revenue this month and failed payments, followed by one orders-per-day line chart and a plain latest API errors list. Avoid navigation link cards; users, riders, settings and reports remain in the navbar. Ensure the chart has an accessible title, data alternative and responsive sizing.

## Riders list

Page title and one “Add rider” primary action, followed by a plain table of rider name, contact, vehicle/plate and availability. On mobile, stack rows with labels. Empty state invites the administrator to add the first rider.

## Rider form

A single grouped form with labeled rider name, phone, vehicle type, plate number and availability fields. Keep one primary “Save rider” action and a secondary cancel link back to Riders. Inline field errors preserve submitted values; destructive removal is separate and requires confirmation.
