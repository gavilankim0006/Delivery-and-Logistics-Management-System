# Vue Dispatch Board Integration

## Why Vue is meaningfully used

The dispatcher order table is a Vue 3 component, not a decorative widget. It owns the live order list, loading and error states, and client-side filter interactions. The board refreshes from the PHP application every 30 seconds and after a dispatcher searches or changes date/status filters.

## Responsibilities

- **Vue 3 (`frontend/dispatch/`)**: renders the order table, displays loading/error/last-updated states, and refreshes data without a full page reload.
- **Vite**: compiles the Vue single-file component into browser-ready JavaScript under `public/build/`.
- **PHP/LavaLust**: continues to own login/session authorization, SQL queries, filter validation, delivery recovery recommendations, and server-rendered fallback content.
- **Security**: the data endpoint is same-origin and protected by the dispatcher/admin role check. Recovery actions remain ordinary POST forms with the session CSRF token; they are not client-side authorization decisions.

## Reproducible build

Requirements: Node.js/npm plus PHP and the existing MySQL database configured through local `.env`. From repository root:

```sh
npm ci
npm run build
```

`npm ci` installs exact dependency versions from `package-lock.json`; it does not modify the lockfile. Vite writes `public/build/dispatch.js`. `node_modules/` and generated `public/build/` output are ignored by Git, so each checkout must build the bundle before using the live board. Build output is served by the PHP app; no separate Vite dev server is needed. Vite may warn that its `outDir` is nested under its default public directory; current production build completes successfully and emits the expected bundle.

## Short live demonstration

1. Start Apache/MySQL in the local XAMPP setup and sign into the app as a dispatcher or admin with existing order data.
2. Open `/dispatch`. Show PHP-rendered page shell and server-side fallback, then open browser DevTools Network and identify Vue's same-origin `GET /dispatch/data` request. It includes current search/date/status filter values; response is JSON containing `data.orders` and summary counts.
3. Type a customer or order number in **Search orders**. After the 300 ms debounce, show a new request with `search=...` and the updated order rows without a page reload. Optionally change date/status or wait for the 30-second refresh.
4. Point out the boundary: browser sends filters; LavaLust route/controller applies the dispatcher/admin authorization, queries database, and returns JSON. Mutating fulfillment actions remain CSRF-protected POST forms.

The demo requires a working local database, account, and order data. Do not claim live end-to-end verification if those are unavailable; the source/build and database-free contract checks can still be demonstrated separately.

Then run the existing PHP/Apache application and visit `/dispatch` as a dispatcher or administrator. If the bundle is missing, the PHP-rendered order table remains visible, but live Vue refresh behavior is unavailable.

## Files

- `frontend/dispatch/DispatchBoard.vue` — live dispatch board component.
- `frontend/dispatch/main.js` — Vue mount entry point.
- `vite.config.js` — Vue plugin and production output settings.
- `app/controllers/Dispatch.php` — protected page and JSON data action.
- `app/config/routes.php` — `/dispatch/data` route.

## Current verification status (2026-10-27)

- `npm run build` passed with Node.js v24.14.0, npm 11.9.0, and Vite 5.4.14; output: `public/build/dispatch.js` (68.99 kB, gzip 27.02 kB). Vite printed a public-directory/outDir nesting warning, but completed successfully.
- `php tests/test_dispatch_vue_contract.php` passed.
- Browser acceptance against the configured MySQL database has not been performed in this session. The live demo above is a reproducible procedure, not a claim that the interaction was exercised here.
