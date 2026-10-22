# Vue Dispatch Board Integration

## Why Vue is meaningfully used

The dispatcher order table is a Vue 3 component, not a decorative widget. It owns the live order list, loading and error states, and client-side filter interactions. The board refreshes from the PHP application every 30 seconds and after a dispatcher searches or changes date/status filters.

## Responsibilities

- **Vue 3 (`frontend/dispatch/`)**: renders the order table, displays loading/error/last-updated states, and refreshes data without a full page reload.
- **Vite**: compiles the Vue single-file component into browser-ready JavaScript under `public/build/`.
- **PHP/LavaLust**: continues to own login/session authorization, SQL queries, filter validation, delivery recovery recommendations, and server-rendered fallback content.
- **Security**: the data endpoint is same-origin and protected by the dispatcher/admin role check. Recovery actions remain ordinary POST forms with the session CSRF token; they are not client-side authorization decisions.

## Build and run locally

From the repository root, with Node.js and npm installed:

```sh
npm install
npm run build
```

Then run the existing PHP/Apache application and visit `/dispatch` as a dispatcher or administrator. Vite writes the entry bundle to `public/build/dispatch.js`; generated dependency and build output are not committed. If the bundle is missing, the PHP-rendered order table remains visible, but the live Vue refresh behavior is unavailable.

## Files

- `frontend/dispatch/DispatchBoard.vue` — live dispatch board component.
- `frontend/dispatch/main.js` — Vue mount entry point.
- `vite.config.js` — Vue plugin and production output settings.
- `app/controllers/Dispatch.php` — protected page and JSON data action.
- `app/config/routes.php` — `/dispatch/data` route.

## Current verification status

The database-free contract test `php tests/test_dispatch_vue_contract.php`, PHP syntax checks, `node --check frontend/dispatch/main.js`, and `git diff --check` passed. Package installation and the production Vite build still need to be run locally; browser acceptance against the configured MySQL database has not been performed.
