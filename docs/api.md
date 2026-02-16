# IWOSAN API (Laravel)

## Base URL
- Local: `http://localhost:8000/api`
- Versioned: `http://localhost:8000/api/v1`

## Auth
- Patient: `POST /patient/register`, `POST /patient/login`
- Carer: `POST /carer/register`, `POST /carer/login`
- Hospital: `POST /hospital/register`, `POST /hospital/login`

## Authenticated Requests
Use `Authorization: Bearer <token>`.

## Dashboard Endpoints
See `docs/api-inventory.md` for a full list.

## Consultations (Senior-Grade Flow)
**Creation (POST /v1/consultation)**
- Roles allowed to create: **Patient** or **Hospital** only (carers cannot create).
- `payment_id` is **optional** at creation.
- If `payment_id` is null, `status` must be `pending_payment`.
- Optional `status_reason` can be included when changing status.

**Status Lifecycle**
- `pending_payment`, `scheduled`, `in_progress`, `completed`, `cancelled`, `no_show`

**Status Metadata**
- `status_description`, `scheduled_at`, `started_at`, `completed_at`, `cancelled_at`, `no_show_at` are set on transitions.

**Cancellation / No-Show Rules**
- Patients can cancel `pending_payment` anytime, or `scheduled` at least 6 hours before start.
- Carer/Hospital can cancel `scheduled` before the start time.
- No-show can be set by Carer/Hospital at least 1 hour after the scheduled time.

**Payment Verification**
- `scheduled`/`in_progress`/`completed` require a verified payment (`payment.status = paid`).

**Treatment Types**
- `Virtual visit`
- `Home visit`
- `Home visit Admitted`

**Subtype payloads**
- Virtual visit requires `vConsultation`:
  - `consult_type`, `duration`
- Home visit requires `hConsultation`:
  - `address`, `ward_id`, `admitted`

**Updates (PUT /v1/consultation/{id})**
- `treatment_type` is **immutable**
- `patient_id`, `carer_id`, `hospital_id` are **immutable**
- If `status` is not `pending_payment`, `payment_id` is required

## Teletests (Senior-Grade Flow)
**Creation (POST /v1/teletest)**
- Roles allowed to create: **Patient** or **Hospital** only (carers cannot create).
- `payment_id` is **optional** at creation.
- If `payment_id` is null, `status` must be `pending_payment`.
- Optional `status_reason` can be included when changing status.

**Status Lifecycle**
- `pending_payment`, `scheduled`, `in_progress`, `completed`, `cancelled`, `no_show`

**Status Metadata**
- `status_description`, `scheduled_at`, `started_at`, `completed_at`, `cancelled_at`, `no_show_at` are set on transitions.

**Cancellation / No-Show Rules**
- Patients can cancel `pending_payment` anytime, or `scheduled` at least 6 hours before start.
- Carer/Hospital can cancel `scheduled` before the start time.
- No-show can be set by Carer/Hospital at least 1 hour after the scheduled time.

**Payment Verification**
- `scheduled`/`in_progress`/`completed` require a verified payment (`payment.status = paid`).

**Updates (PUT /v1/teletest/{id})**
- `patient_id`, `carer_id`, `hospital_id` are **immutable**
- If `status` is not `pending_payment`, `payment_id` is required

## Appointments (Status Rules)
**Status Lifecycle**
- `pending_payment`, `scheduled`, `in_progress`, `completed`, `cancelled`, `no_show`

**Status Metadata**
- `status_description`, `scheduled_at`, `started_at`, `completed_at`, `cancelled_at`, `no_show_at` are set on transitions.

**Cancellation / No-Show Rules**
- Patients can cancel `pending_payment` anytime, or `scheduled` at least 6 hours before start.
- Carer/Hospital can cancel `scheduled` before the start time.
- No-show can be set by Carer/Hospital at least 1 hour after the scheduled time.

**Payment Verification**
- `scheduled`/`in_progress`/`completed` require a verified payment (`payment.status = paid`).

## Notes
- Legacy (unversioned) endpoints exist for compatibility.
- Updates should use standard HTTP verbs (PUT/PATCH).
- Dashboard endpoints are protected by role middleware.

## Payments (Senior-Grade Flow)
**Creation (POST /v1/payment)**
- `status` must be one of: `pending`, `processing`, `paid`, `failed`, `cancelled`, `refund_pending`, `refunded`.
- `paid` requires `verified=true` and a `reference`.
- Supports `Idempotency-Key` header for safe retries.

**Updates (PUT /v1/payment/{id})**
- `type` and `type_id` are immutable.
- Status transitions are validated.
- `paid` can only be set by the payment processor (webhook).
- Supports `Idempotency-Key` header for safe retries.

**View**
- `GET /v1/payment` (patient/carer/hospital scoped to ownership)

**Metadata**
- Optional: `reference`, `gateway`, `verified_at`, `status_reason`.

**Webhook**
- `POST /v1/payment/webhook` (Paystack): verifies `x-paystack-signature` and queues async processing.

## Lab Results (Senior-Grade Flow)
**Creation (POST /v1/labresult)**
- Requires `patient_id`, `name`, `lab_name`, `extra_notes`, and either:
  - `file` (single file) **or**
  - `files[]` (1–2 files for front/back).
- `carer_id` and `teletest_id` are required for carer/hospital uploads.
- Patient uploads may omit `carer_id` and `teletest_id` for external lab results.
- When `teletest_id` is present, it must belong to the same patient + carer.

**Updates (PUT /v1/labresult/{id})**
- Same payload as create, file(s) optional.

**Access**
- Patient/carer/hospital access is enforced via ownership checks.

**Files Alias**
- `GET /v1/labresult/{id}/files` returns `result_pictures[]`.

**Recovery**
- `POST /v1/labresult/{id}/restore` restores a soft-deleted result.

## Prescriptions (Senior-Grade Flow)
**Endpoint**
- `GET /v1/patient/prescriptions/{id}`

**Drug Status**
- `active`, `completed`, `discontinued`

**Lab Test Status**
- `ordered`, `scheduled`, `collected`, `resulted`

**Response**
- Each consultation entry returns `drugs`, `lab_tests`, plus grouped `drugs_active`, `drugs_past`, `lab_tests_active`, `lab_tests_past`.

## General Vitals (Senior-Grade Flow)
**Store (POST /v1/patient/storevital)**
- Required: `patient_id`, `type`, `unit`, `taken_at`, `context`, `source`
- `type` enum: `temperature`, `heart_rate`, `respiratory_rate`, `blood_pressure`, `oxygen_saturation`, `blood_glucose`, `weight`, `height`, `bmi`, `pain_score`
- `blood_pressure` requires `systolic` + `diastolic`, unit `mmHg`
- Other types require `value` + matching unit:
  - temperature `C`, heart_rate `bpm`, respiratory_rate `rpm`, oxygen_saturation `%`, blood_glucose `mg/dL`, weight `kg`, height `m`, bmi `kg/m2`, pain_score `/10`
- Context enum: `resting`, `post_exercise`, `post_meal`, `fasting`, `pre_med`, `post_med`, `sleep`, `unknown`
- Source enum: `patient_manual`, `device_sync`, `clinic`

**View (GET /v1/patient/vitals/{id})**
- Returns `latest`, `series`, `alerts` with status flags.

**Deletion**
- Vitals are soft-deleted; audit log tracks create/update/delete actions.

**Audit**
- `GET /v1/genvital/{id}/audit` returns audit history for staff with access.

## Carer Search (Senior-Grade Flow)
**Endpoint**
- `GET /v1/patient/search_carers`

**Required**
- `visit_type`: `home` or `virtual`
- `availability`: `anytime` or `window`

**Optional Filters**
- `q` (name/position/hospital), `position`, `hospital_id`, `hospital_name`, `gender`
- `price_min`, `price_max`
- `lat`, `lon`, `max_distance_km`
- `availability_start`, `availability_end` (when `availability=window`)

**Response**
- Paginated with `total`, `page`, `per_page`, `results`
- Each result includes quick cards: `price`, `rating`, `next_available`, `distance_km`, `certifications_count`, `review_count`

## Test Search (Senior-Grade Flow)
**Endpoint**
- `GET /v1/patient/search_test`

**Optional Filters**
- `q` (test name or hospital), `hospital_id`, `hospital_name`
- `price_min`, `price_max`, `rating_min`
- `lat`, `lon`, `max_distance_km`

**Response**
- Paginated with `total`, `page`, `per_page`, `results`

## Manage Hospital Tests
**Create**
- `POST /v1/test`
- Required: `hospital_id`, `name`, `price`
- Optional: `code`, `sample_type`, `turnaround_time`, `preparation_notes`, `extra_notes`, `is_active`, `status_reason`

**Update**
- `PUT /v1/test/{id}`
- Same payload as create plus `price_reason` (optional, audit)
- Price changes are audited.

**List**
- `GET /v1/test`
- Hospital role sees all; patient role sees only active.

## Carer & Hospital Profiles (Senior-Grade Fields)
**Carer Profile**
- `GET /v1/carer/{id}` (patient or staff)
- Optional query params: `lat`, `lon` to compute `distance_km`
- Additional fields returned for patient profile:
  - `availability.next_available` (derived from `home_day_time` / `virtual_day_time`)
  - `performance.response_time_minutes`
  - `service_radius_km`
  - `pricing.home_visit_price`, `pricing.virtual_visit_price`, `pricing.virtual_ward_price`
  - `performance.total_sessions`, `performance.completed_sessions`, `performance.no_show_sessions`
  - `performance.completion_rate_pct`, `performance.no_show_rate_pct`

**Hospital Profile**
- `GET /v1/hospital/{id}` (patient or staff)
- Optional query params: `lat`, `lon` to compute `distance_km`
- Additional fields returned for patient profile:
  - `pricing.home_visit_price`, `pricing.virtual_visit_price`, `pricing.virtual_ward_price`
  - `verified`

## Metrics (Hospital + Carer)
**Hospital Metrics**
- `GET /v1/hospital/metrics/{id}`
- Optional query params: `from`, `to` (YYYY-MM-DD), `tz` (timezone)
- Returns: existing metrics fields plus `range`, `total_duration_seconds`, `total_duration`

**Carer Metrics**
- `GET /v1/hospital/carer_metrics/{id}`
- Optional query params: `from`, `to` (YYYY-MM-DD), `tz` (timezone)
- Returns: existing metrics fields plus `range`, `total_duration_seconds`, `total_duration`

## Hospital Pricing (Update + Audit)
**Update Prices**
- `POST /v1/hospital/updateprices/{id}`
- Required: `home_visit_price`, `virtual_visit_price`, `virtual_ward_price` (integers >= 0)
- Optional: `reason` (string)
- Response: `HospitalLiteResource`

**Price History**
- `GET /v1/hospital/price-history/{id}`
- Optional: `page`, `per_page`
- Returns: `total`, `page`, `per_page`, `results[]`
- Each history item includes `previous`, `current`, `reason`, `changed_by`, `created_at`

## Certificates (Trust & Verification)
**List**
- `GET /v1/certlice`
- Patients only see `verified` certificates.
- Use `?include_history=1` to include expired certificates.

**Create (Owner Upload)**
- `POST /v1/certlice`
- Required: `type` (`carer` or `hospital`), `type_id`, `cert_type`, `issuer`, `license_number`, `issued_at`, `file_name`, `file`
- Optional: `expires_at`, `notes`
- New uploads are `status = pending`.

**Update (Owner)**
- `PUT /v1/certlice/{id}`
- Same payload as create; any update resets status to `pending`.

**Review (Hospital/Admin)**
- `POST /v1/certlice/{id}/review`
- Payload: `status` (`verified` or `rejected`), optional `reason`
- Sets `verified_at`, `verified_by` when verified.

**Manage Own Certificates (Carer/Hospital)**
- `GET /v1/carer/certlices/{id}`
- `GET /v1/hospital/certlices/{id}`
- Optional: `?status=pending|verified|rejected|expired`
- Hospital review queue for carers: `GET /v1/hospital/certlices/{id}?type=carer&status=pending`
- Response includes `summary` + `certificates`

## Manage Carers (Hospital)
**List**
- `GET /v1/hospital/carers/{id}`
- Query params: `status=pending|approved|rejected`, `position`, `q`, `sort=rating|recent`, `page`, `per_page`
- Each carer includes `approval_status`, `last_reviewed_at`, `queue_age_days`.

**Review**
- `POST /v1/hospital/carer/{id}/review`
- Payload: `status` (`approved` or `rejected`), optional `reason`
- Updates approval flags and records an audit log.

**Approval History**
- `GET /v1/hospital/carer/{id}/approvals`
- Optional: `page`, `per_page`
- Returns: `total`, `page`, `per_page`, `results[]`

## Reviews (Carer View)
**Endpoint**
- `GET /v1/carer/reviews/{id}`

**Response**
- `summary`: `average_rating`, `review_count`, `rating_breakdown`, `recommend_rate_pct`, `last_30d_count`, `last_30d_average_rating`
- `reviews`: paginated list

**Respond**
- `POST /v1/review/{id}/respond`
- Payload: `response_text`

## Reviews CRUD (Production)
**List**
- `GET /v1/review`
- Filters: `status`, `rating_min`, `rating_max`, `date_from`, `date_to`, `q`, `page`, `per_page`
- Patients cannot see `rejected` reviews; carers only see `published`.

**Create**
- `POST /v1/review`
- Patient only. Requires completed consultation; one review per consultation per patient.

**Update**
- `PUT /v1/review/{id}`
- Patient only. Updates text/rating/recommendation/tags.

**Delete**
- `DELETE /v1/review/{id}`
- Patient only. Removes own review.

**Moderate**
- `DELETE /v1/review/{id}` (hospital role): marks review as `rejected` with optional `reason`.

**Audit**
- `GET /v1/review/{id}/audit`

## Find Lab Tech (Senior-Grade Flow)
**Endpoint**
- `GET /v1/patient/carer_test?hospital_id=...`

**Response**
- Returns top 3 `results` with `carer` and current `load` (active appointments + consultations + teletests).

**Optional Filters**
- `lat`, `lon` for distance ranking
- `visit_type`: `home` or `virtual`
- `availability`: `anytime` or `window`
- `availability_start`, `availability_end` (when `availability=window`)

## Ward Dashboard (Senior-Grade Flow)
**Endpoint**
- `GET /v1/ward/dashboard/{id}`

**Pagination**
- `timeline_per_page`, `timeline_page`
- `vitals_per_page`, `vitals_page`

**Response**
- `ward`: summary (status, diagnosis, admission/discharge)
- `patient`, `carer`, `hospital`
- `vitals`: grouped by type with pagination
- `timeline`: paginated events
- `prescriptions`: `drugs`, `lab_tests`
- `alerts`: out-of-range vitals using ward alert limits

## Ward Admissions (Assigned Wards)
**Carer**
- `GET /v1/carer/wards/{id}`

**Hospital**
- `GET /v1/hospital/wards/{id}`

**Ward CRUD**
- `GET /v1/ward` (patient/carer/hospital scoped, paginated)
- `POST /v1/ward`
- `PUT /v1/ward/{id}`
- `DELETE /v1/ward/{id}`
- `GET /v1/ward/{id}/audit` (audit log, paginated)

**Filters**
- `status`: `active` (default) or `discharged`
- `priority`: `high|medium|low`
- `q`: patient name or diagnosis
- `sort`: `recent` (default) or `priority`
- `per_page`, `page`

**Response**
- `data`: ward list with patient/carer, latest vitals timestamp, alerts count
- `pagination`

## Ward Vitals + Notes + Timeline (Clinical Metadata)
**Vitals input (ward vitals endpoints)**
- Accept optional `taken_at`, `source`
- `other_vitals` accepts optional `unit`
- Records `recorded_at` and adds timeline meta
- `GET /v1/ward/vitals/{id}` returns grouped vitals with `stats.last_24h` and `stats.last_7d` (count/min/max/avg)
- `GET /v1/ward/{id}/vitals/audit` returns audit history (filters: `type`, `vital_id`, `page`, `per_page`)

**Ward Notes**
- Accept optional `note_type`
- Stores `author_id`, `author_role`, `recorded_at`

**Timeline**
- Stores `author_id`, `author_role`, `meta`

## Prescriptions (Manage Drugs + Lab Tests)
**Drugs**
- Status transitions tracked with status change logs.
- Supports `status_reason` on create/update.

**Drugs CRUD (Filters)**
- `GET /v1/drug`
- Filters: `status`, `date_from`, `date_to`, `q`, `page`, `per_page`
- Returns: `data` + `pagination`

**Lab Tests**
- Status transitions tracked with status change logs.
- Supports `status_reason` on create/update.

## Lab Tests CRUD (Filters)
**List**
- `GET /v1/labtest`
- Filters: `status`, `date_from`, `date_to`, `q`, `page`, `per_page`
- Returns: `data` + `pagination`

## Ward Prescriptions Listing
**Endpoint**
- `GET /v1/ward/prescriptions/{id}`

**Filters**
- `type`: `drug|lab_test`
- `status`: `active|completed|discontinued` (drugs) or `ordered|scheduled|collected|resulted` (lab tests)
- `date_from`, `date_to`, `q`
- `page`, `per_page` when `type` is specified

**Response**
- If `type` provided: paginated list.
- Otherwise: grouped `drugs.by_status` and `lab_tests.by_status`.

## Lab Results (File Upload)
**List**
- `GET /v1/labresult`
- Filters: `date_from`, `date_to`, `q`, `page`, `per_page`
- Returns: `data` + `pagination`

**Files**
- `GET /v1/labresult/{id}/files`

**Audit**
- `GET /v1/labresult/{id}/audit`

**Metadata fields**
- `uploaded_at`, `uploaded_by`, `uploaded_role`, `source`

## Transfers (Payouts)
**List**
- `GET /v1/transfer`
- Returns `summary` + `transfers` + `pagination`
- Filters: `status`, `date_from`, `date_to`, `recipient`, `reference`, `reason`, `min_amount`, `max_amount`, `per_page`, `page`

**Create request**
- `POST /v1/transfer`
- Owner-only (carer/hospital)
- Supports `Idempotency-Key` header for safe retries.
- Optional: `type` (`appointment|consultation|teletest`), `type_id`

**Status update**
- `PATCH /v1/transfer/{id}/status`
- Payload: `status` (`pending|processing|paid|failed|reversed`), optional `reference`, `failure_reason`

## Payments Processing (Paystack)
**Webhook**
- `POST /v1/payment/webhook`
- Idempotent by `event + reference`, logs webhook events and audit trail.

**Transfer recipients**
- `POST /v1/paymentprocessing/create_transfer_recipient`
- `POST /v1/paymentprocessing/update_transfer_recipient`
- `POST /v1/paymentprocessing/delete_transfer_recipient`
- `POST /v1/paymentprocessing/resolve_account_details`

**Transfers**
- `POST /v1/paymentprocessing/initiate_transfer`
- `POST /v1/paymentprocessing/finalize_transfer`
- `POST /v1/paymentprocessing/verify_transfer`

**Refunds**
- `POST /v1/paymentprocessing/create_refund`
- `POST /v1/paymentprocessing/fetch_refund`

**Payment fields**
- `gateway_transaction_id`, `channel`, `currency`, `fees`, `processing_at`, `paid_at`, `failed_at`, `refunded_at`

## Agora Token (Realtime)
**Endpoint**
- `POST /v1/agora/token`
- Payload: `channelName`, `uid`, optional `ttl` (seconds, 60–86400)
- Response: `access_token`, `expires_at`

## Metrics (Daily Summary)
**Endpoint**
- `GET /v1/metrics/daily`
- Query params: `from` (YYYY-MM-DD), `to` (YYYY-MM-DD), `role` (patient|carer|hospital), `owner_type` (hospital|carer|patient), `owner_id`
- Returns daily aggregates: `conversion_rate`, `completion_rate`, `refund_rate`, plus raw `counts`.

## Secure File Access
**Signed URLs (Lab Results)**
- `GET /v1/files/labresult/{id}`
- Returns signed URLs for lab result files (short‑lived).

**Signed URL (Certificates)**
- `GET /v1/files/certlice/{id}`
- Returns signed URL for certificate file (short‑lived).

**Signed File Routes**
- `GET /files/labresult/{filename}` (signed)
- `GET /files/certlices/hospital/{filename}` (signed)
- `GET /files/certlices/carer/{filename}` (signed)
## Hospital Carers (Active + Pending)
**Active/Approved**
- `GET /v1/hospital/carers/{id}`
- Filters: `status` (`approved|pending|rejected`), `position`, `q`, `sort` (`rating|recent`), `per_page`, `page`

**Pending queue**
- `GET /v1/hospital/pendingcarers/{id}`
- Filters: `status`, `q`, `per_page`, `page`
- Response includes `summary` + paginated carers

## Complaints (Workflow)
**List**
- `GET /v1/complaints`
- Filters: `status`, `severity`, `category`, `date_from`, `date_to`, `q`, `per_page`, `page`
- Response includes `summary` + `data`

**Create**
- `POST /v1/complaints`
- Patient only.
- Required: `patient_id`, `hospital_id`, `title`, `complaint`, `category`, `severity`

**Update**
- `PUT /v1/complaints/{id}`
- Patient only. Updates title/complaint/category/severity.

**Delete**
- `DELETE /v1/complaints/{id}`
- Patient only. Removes own complaint.

**Status update (hospital only)**
- `PATCH /v1/complaints/{id}/status`
- Payload: `status` (`open|in_review|resolved|closed|rejected`), optional `response_notes`, `resolution_notes`, `rejection_reason`

**Audit**
- `GET /v1/complaints/{id}/audit`

## Pending Appointments (Hospital)
**Endpoint**
- `GET /v1/hospital/pendingappointments/{id}`

**Filters**
- `status` (default `pending_payment`)
- `appointment_type`
- `payment_status`
- `carer_id`
- `date_from`, `date_to`
- `q` (patient name)
- `per_page`, `page`

**Response**
- `summary` + paginated `data`

## Lite Profiles
**Patient lite**
- Includes `id`, `user`, `avatar`, `address`, `verified`

**Carer lite**
- Includes `id`, `user`, `position`, `rating`, `review_count`, `avatar`, `verified`, leave flags

**Hospital lite**
- Includes `id`, `name`, `rating`, `verified`, contact + pricing
