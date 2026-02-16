# Migration Notes

## Order Dependencies
1. Core table creations (`users`, `patients`, `carers`, `hospitals`, `payments`, `appointments`, `consultations`, `teletests`, `wards`, `lab_tests`, `drugs`, `reviews`, `complaints`, `transfers`).
2. Add-on schema changes (status metadata, audit logs, new fields).
3. FK nullability fixes (optional relationships).
4. Performance indexes.
5. Backfill migrations (data-only).

## Data Checks Before Running
- Ensure no orphan rows exist for new FK constraints.
- Clear `0` foreign key placeholders (should be `NULL`).
- Remove duplicate reviews for the same patient + consultation.

## Backfill Migrations
- `2026_02_07_000017_backfill_lab_results_uploaded_at.php` sets `uploaded_at = created_at` where missing.

## Breaking Changes
- `2026_02_07_000015_drop_payments_transfer_id.php` removes `payments.transfer_id`.
  - Relationship now uses `transfers.payment_id`.

## SQLite Test Notes
- Some migrations use MySQL-specific `ALTER TABLE ... MODIFY` for FK nullability fixes.
- In tests (SQLite), these migrations are guarded and skipped.
- CI environments that use MySQL should run the full migration set to validate production parity.
