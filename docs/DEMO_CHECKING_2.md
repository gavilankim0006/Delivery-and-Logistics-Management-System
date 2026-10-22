# Checking 2 Demonstration Guide

This is a walkthrough plan, not evidence that the demonstration has been rehearsed. Rehearse only after an explicitly approved disposable MySQL database has been created and its exact `DB_NAME` is configured. No approved disposable target is currently recorded in the project; do not run the database-mutating steps or acceptance scripts below until one is explicitly approved. Never use valuable shared data. `database/schema.sql` drops and recreates tables, and `database/seed.sql` replaces seeded records.

## Before the demonstration

1. Start Apache and MySQL in XAMPP and open the app at `http://localhost/SIAproject`.
2. Confirm the local `.env` points to the intended development database. Do not display or share `.env` secrets during the presentation.
3. Sign in with the seeded test accounts as needed:
   - Customer: `maria@example.com`
   - Dispatcher: `dispatcher@delivery.local`
   - Admin: `admin@delivery.local`
   - Seeded test password: `password123`
4. In phpMyAdmin or a MySQL client, select the configured development database and show the tables, primary keys, and foreign-key relationships from `database/schema.sql`. Avoid showing sensitive local credentials.

## Show the MVC path

1. Open `app/config/routes.php` and point out the GET route `/orders` mapped to `Orders::index` and the GET route `/addresses` mapped to `Addresses::index`.
2. Open `app/controllers/Orders.php` and `app/controllers/Addresses.php` to show role checks, retrieval of the authenticated customer ID, and view rendering.
3. Open `app/models/Order_model.php` and `app/models/Address_model.php` to show the parameterized SQL used for filtered order reads and customer-scoped address reads.
4. Open `app/views/orders/index.php`, `app/views/orders/show.php`, and `app/views/addresses/index.php` to show escaped dynamic values rendered from those queries.

## Demonstrate database-backed pages

1. Sign in as the customer and open **My Orders**. Show a seeded order and its current status.
2. Open the order detail page and compare its order number, pickup/drop-off addresses, parcel values, fee, and delivery timeline with the corresponding `orders`, `addresses`, `parcels`, and `deliveries` rows in phpMyAdmin or SQL.
3. Open **Saved Addresses**. Show the customer's saved addresses and compare their labels/contact details to the `addresses` table rows with that customer's `customer_id`. Sign in as a different customer to demonstrate that their rows are not shown.
4. Sign in as the admin and open **Users** and **Riders**. Show seeded rows; optionally apply the user search and rider status filters to demonstrate database-backed results.
5. Sign in as the dispatcher and open **Dispatch Board**. Compare the displayed counts with independent SQL queries using the same definitions:

   ```sql
   SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURRENT_DATE();
   SELECT COUNT(*) FROM orders WHERE status = 'paid';
   SELECT COUNT(*) FROM orders WHERE status IN ('booking', 'rider_assigned', 'picked_up');
   SELECT COUNT(*) FROM orders WHERE status = 'failed';
   ```

   The labels map respectively to Today's Orders, Awaiting Booking, In Transit, and Failed / Exceptions.

## Prove that the page is dynamic

Use an isolated disposable development database or a disposable test row; do not edit a seeded/business record just for the demo. In a local development database only, create a standalone saved address for seeded customer 1 (this row is not referenced by an order):

```sql
INSERT INTO addresses
    (customer_id, label, address_line, contact_name, contact_phone, notes)
VALUES
    (1, 'Demo Original Label', 'Disposable demo address', 'Demo Contact', '+639171234500', 'Remove after demonstration');
SELECT LAST_INSERT_ID();
```

Record the returned ID. Sign in as Maria (`maria@example.com`) and refresh `/addresses` to show `Demo Original Label`. Then update only that row, commit the change, and refresh again:

```sql
UPDATE addresses SET label = 'Demo Temporary Label' WHERE id = <recorded_disposable_id>;
```

After demonstrating the updated value, restore and remove only the disposable row:

```sql
DELETE FROM addresses WHERE id = <recorded_disposable_id>;
```

Because the row is standalone, it is not subject to the order-address foreign-key restriction. Confirm the selected database is the disposable local development database before running these statements. Never run `database/schema.sql` or `database/seed.sql` as a cleanup step on a database containing work to preserve.

## Run acceptance checks

From the project root, run:

```powershell
php tests/test_t5_b5_read_pages.php
php tests/test_t4_order_reads.php
php tests/test_t1_schema_seed.php
```

These checks verify read-page rendering, customer scoping, role restrictions, order filtering/detail behavior, and dispatcher counter values against database counts. The B5 checks do not by themselves establish every Checking 2 criterion: health-check behavior, the complete model API requirements, and the remaining instructor demonstration items must also be independently reviewed before declaring Checking 2 ready.
