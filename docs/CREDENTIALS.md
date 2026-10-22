# Local setup and optional credentials

## Needed now for local development

- PHP 8.1+ with the extensions required by LavaLust and this project.
- A local MySQL/MariaDB database configured in `.env` (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`) and the correct `BASE_URL`.
- `APP_KEY` for application/session security. Keep `.env` local and never paste secrets into source control or chat.

Use `database/schema.sql` and `database/seed.sql` only for an empty/disposable development database. They are bootstrap scripts and replace existing seeded data.

## Courier flow: no Lalamove credentials needed

`COURIER_DRIVER=mock` is the default. Mock quotations, bookings, driver details, tracking links, and synthetic webhook-shaped events work without a Lalamove account or API keys. Admins can use `/admin/courier-simulator` in development to test rider assigned, picked up, delivered, failed, and cancelled events. These events use the same webhook processor as inbound courier events.

## Optional Lalamove sandbox credentials

Only set these when you are authorized and ready to test the real Lalamove sandbox driver:

- `COURIER_DRIVER=lalamove`
- `LALAMOVE_API_KEY`
- `LALAMOVE_API_SECRET`
- `LALAMOVE_BASE_URL=https://rest.sandbox.lalamove.com`
- `LALAMOVE_MARKET=PH`
- `LALAMOVE_WEBHOOK_SECRET` for this application's configured inbound webhook verification
- `LALAMOVE_WEBHOOK_AUTH_CONFIRMED=1` only after Lalamove partner documentation confirms this application's exact inbound authentication and payload contract; otherwise leave it unset/`0`. The endpoint also requires `COURIER_DRIVER=lalamove` and `APP_ENV=production`.

Do not use live booking credentials for class/demo testing. The public v3 API documentation describes HMAC signing for outbound API requests; it does not establish the webhook-signature header convention used by this application. Confirm incoming webhook authentication with Lalamove's partner webhook specification before enabling real webhooks.

## Maps and polygon service areas

The Admin polygon-zone editor uses `GOOGLE_MAPS_MAPS_JS_API_KEY`, a browser key restricted by HTTP referrer and limited to the Maps JavaScript API. Keep it separate from the server-side `GOOGLE_MAPS_API_KEY`, which must not be exposed to browsers. Without the browser key, zone management remains visible but map drawing and saving are disabled.

## Role signature feature database upgrade

The command simulator, public delivery-control passes, dispatcher request review, and booking idempotency guard require migration 004. Migrations remain disabled by default in `app/config/migration.php`. For a local development DB, explicitly run the app's migration command with migrations enabled temporarily, then disable them again; do not run the bootstrap schema against a database with data. The migration is `app/migrations/004_add_role_signature_features.php`.

## Other integrations

PayMongo test keys, Semaphore, and Firebase remain optional for the local courier simulator. Their associated app flows may have separate configuration requirements. Use test/sandbox credentials only and keep every secret in `.env`.
