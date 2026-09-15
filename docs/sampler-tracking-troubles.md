# Sampler tracking: shared activities and overdue work

## Deployment

- Deploy Lumen and Apps FDL together. No office-frontend changes are required.
- Apply `database/migrations/2026_09_15_100000_create_sampler_tracking_troubles.php` through the normal deployment process. It checks `Schema::hasTable`. The development task does not apply this migration to the application database.
- The trouble guard deliberately returns 503 until the table exists; install the migration before exposing the updated endpoints.
- Ensure the server's existing Artisan scheduler runs. `sampler-tracking:collect-troubles` is registered at **00:00 Asia/Jakarta**, with overlap protection.
- The collector defaults to yesterday's deadline. If a scheduled run was missed, an operator can explicitly run `php artisan sampler-tracking:collect-troubles --date=YYYY-MM-DD` for that deadline. Repeating a deadline does not duplicate or reopen an existing trouble record.

## Activity identity and completion

Same sampling start date, customer ID from OrderHeader, and exact active sampler team become one logical stop. Underlying sessions, orders and historical events remain intact. The response includes `activity_session_ids`, `activity_orders`, and `activity_member_ids` for traceability. Unknown customer IDs are never merged by company name.

Events for a logical stop are persisted against its underlying order memberships. Previously recorded events are retained, retries do not insert the same event type again, and blocked teammates do not automatically inherit today's events. Checkout/return preserve the maximum effective duration per sampler at that stop.

A day's progress requires departure, checkin and checkout for all logical stops, and return. Duration 0/1 is due on the start date; duration 2 means one 24-hour period (due next day), etc. Multi-day work is not flagged before its deadline. The trouble `activity_date` is the original start date, even if its deadline is later.

## Supervisor endpoints

Use the existing authenticated `SamplerTrackingController` dispatch (office API or mobile API):

- `teamTroubles`: returns unresolved trouble for the authenticated employee's direct reports. No arbitrary sampler selector is trusted for this list.
- `reopenTrouble`: payload `{ "trouble_id": 123, "note": "Laporan diterima" }`. The actor must be in the sampler's `master_karyawan.atasan_langsung`; self-approval is rejected. This saves `reopened_by`, `reopened_at`, and `reopen_note`, **not** `is_clear = 1`.

There is no new supervisor UI in this change. These endpoints are ready for integration into the subsequently chosen supervisor workflow.

On refresh Apps FDL automatically opens the oldest approved unresolved date, without a date dropdown. Once complete, the next approved date is selected; when all trouble is clear it returns to today. The mobile index ignores client dates and returns `server_today` (explicit Asia/Jakarta), `activity_date`, and `is_recovery`, including before 07:00 WIB when UTC is still yesterday. Event submission records the actual submission time, not a client-supplied backdate. On subsequent requests only unresolved troubles are checked: once the old progress is complete, `is_clear = 1` and `cleared_at` are saved. Cleared rows are not checked again. All unresolved dates must be finished before today's activity is enabled.

## Verification

`php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit tests/Unit/SamplerTrackingTroubleTest.php`

Tests bind all database access to isolated in-memory SQLite, including legacy models explicitly naming the mysql connection. Never point these tests at an application database.
