# Pharmacy POS

Laravel 12, Vue 3, TypeScript, and Inertia pharmacy POS with a local event ledger and cloud synchronization.

## Deployment roles

Set `SYNC_ROLE` explicitly for every build:

- `child` — installed POS. It commits business data and its outbound ledger event in the same database transaction, then uploads with its unique device credential.
- `parent` — cloud authority. Only this route cache exposes `/api/v1/sync/receive`, `/serve`, and the retired restore endpoint.

Build and cache routes independently for each role. A device token authenticates one registered device; it is not an administrator credential. Do not expose device tokens through Vite variables, browser storage, source control, or shared installers.

## Local development

1. Install dependencies: `composer install && npm install`.
2. Copy `.env.example` to `.env`, set `APP_KEY`, and configure the database.
3. Run `php artisan migrate` and `npm run build`.
4. Run the app plus matching workers:

   `php artisan queue:work --queue=sync-outbox,sync-inbox,default --timeout=60 --tries=5`

   `php artisan schedule:work`

Parent devices must have an active `device_sync_policies` record with `download_mode=continuous` before `/serve` returns business data. Uploads remain enabled when downloads are disabled.

## Recovery status

The old full-restore endpoint is deliberately disabled because it could erase local read models while unsynced ledger entries still existed. Restore must use the staged, device-bound grant protocol described in `docs/OFFLINE_CLOUD_POS_IMPLEMENTATION_BRIEF.md`; it has not yet been implemented. Keep encrypted backups and preserve the local ledger before recovery work.

NativePHP packaging, per-device secure credential storage, scoped restore grants, inventory allocations, and mobile lifecycle adapters remain planned work; this repository does not claim these distributions are available yet.

## Verification

Run focused tests first, then the full suite:

`php artisan test tests/Feature/Sync/SyncPipelineTest.php`

`php artisan test`
