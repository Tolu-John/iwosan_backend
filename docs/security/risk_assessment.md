# Risk Assessment

Date: 2026-02-15
Owner: Security Officer (TBD)

## Scope
- Backend services (Laravel API, jobs, scheduler)
- Data stores (MySQL, object storage)
- Third-party providers (WhatsApp, Termii, Paystack)

## Assets
- Patient records (PHI)
- Clinical notes, lab results
- Authentication tokens

## Threats
- Unauthorized access
- Data exfiltration
- Misconfigured storage/public URLs
- Webhook spoofing

## Controls
- Role-based access controls
- Audit logging
- Signed URLs for files
- Webhook signature validation

## Open Risks
- Incomplete read-access logging
- Retention policy not enforced
- Incident response not automated

