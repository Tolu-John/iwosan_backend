# Encryption Policy

Date: 2026-02-15

## In Transit
- Enforce TLS for all API traffic.

## At Rest
- Use database provider encryption at rest.
- Encrypt backups and object storage.
- Enforce provider encryption via `ENCRYPTION_PROVIDER_ENFORCED=true` and record `ENCRYPTION_PROVIDER_NAME`.
- Optional: field-level encryption controlled by `ENCRYPTION_FIELD_LEVEL`.

## Key Management
- Keys stored in secure secret manager.
- Rotate keys annually or after incident.
- Track active key id in `ENCRYPTION_KEY_ID`.
