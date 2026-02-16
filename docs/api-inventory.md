# API Inventory (Backend + Android Client)

## Source of Truth
- Backend routes: `core/routes/api.php`
- Android client interface: `Iwosan/app/src/main/java/com/jools/iwosan/database/IwosanOnlineAPI.java`

## Backend Routes (Grouped)

### Auth + Password Reset
- `POST /patient/register`
- `POST /patient/login`
- `POST /carer/register`
- `POST /carer/login`
- `POST /hospital/register`
- `POST /hospital/login`
- `POST /forgot_password`
- `POST /forgot_password/check`
- `POST /forgot_password/reset`

### Storage File Serving
- `GET /storage/carer/{filename}`
- `GET /storage/patient/{filename}`
- `GET /storage/hospital/{filename}`
- `GET /storage/labresult/{filename}`
- `GET /storage/certlices/hospital/{filename}`
- `GET /storage/certlices/carer/{filename}`

### Authenticated `/v1` Dashboard Endpoints
- `GET /v1/hospital/tests/{id}`
- `GET /v1/hospital/hconsultations/{id}`
- `GET /v1/hospital/vconsultations/{id}`
- `GET /v1/hospital/teletests/{id}`
- `GET /v1/hospital/carers/{id}`
- `POST /v1/hospital/carer/{id}/review`
- `GET /v1/hospital/carer/{id}/approvals`
- `GET /v1/hospital/complaints/{id}`
- `GET /v1/hospital/pendingappointments/{id}`
- `GET /v1/hospital/pendingcarers/{id}`
- `GET /v1/hospital/lite/{id}`
- `POST /v1/hospital/updateprices/{id}`
- `GET /v1/hospital/price-history/{id}`
- `GET /v1/hospital/metrics/{id}`
- `GET /v1/hospital/carer_metrics/{id}`
- `GET /v1/hospital/certlices/{id}`

- `GET /v1/carer/showappointment/{id}`
- `GET /v1/carer/showpayments/{id}`
- `GET /v1/carer/allvconsults/{id}`
- `GET /v1/carer/allhconsults/{id}`
- `GET /v1/carer/allteletests/{id}`
- `GET /v1/carer/certlices/{id}`
- `POST /v1/certlice/{id}/review`
- `GET /v1/carer/reviews/{id}`
- `GET /v1/carer/lite/{id}`
- `GET /v1/carer/reviews/{id}`

- `GET /v1/patient/prescriptions/{id}`
- `GET /v1/patient/search_carers`
- `GET /v1/patient/appointments/{id}`
- `GET /v1/patient/labresults/{id}`
- `GET /v1/patient/vconsult/{id}`
- `GET /v1/patient/hconsult/{id}`
- `GET /v1/patient/teletest/{id}`
- `GET /v1/patient/search_test`
- `GET /v1/patient/carer_test/{id}`
- `GET /v1/patient/lite/{id}`
- `GET /v1/patient/vitals/{id}`
- `POST /v1/patient/storevital`

### Ward Flows
- `GET /v1/carer/wards/{id}`
- `GET /v1/hospital/wards/{id}`
- `GET /v1/ward/vitals/{id}`
- `POST /v1/ward/updatevitals/{id}`
- `GET /v1/ward/dashboard/{id}`
- `GET /v1/ward/vitals/{id}`
- `GET /v1/ward/{id}/audit`
- `GET /v1/ward/{id}/vitals/audit`
- `GET /v1/ward/prescriptions/{id}`
- `GET /v1/ward/timeline/{id}`
- `GET /v1/ward/prescriptions/{id}`

### Ward CRUD Resources
- `POST /v1/othervitals`
- `GET /v1/othervitals/{id}`
- `DELETE /v1/othervitals/{id}`
- `POST /v1/wardtimeline`
- `GET /v1/wardtimeline/{id}`
- `DELETE /v1/wardtimeline/{id}`
- `POST /v1/wardbpdia`
- `GET /v1/wardbpdia/{id}`
- `DELETE /v1/wardbpdia/{id}`
- `POST /v1/wardbpsys`
- `GET /v1/wardbpsys/{id}`
- `DELETE /v1/wardbpsys/{id}`
- `POST /v1/wardnote`
- `GET /v1/wardnote/{id}`
- `DELETE /v1/wardnote/{id}`
- `POST /v1/wardsugar`
- `GET /v1/wardsugar/{id}`
- `DELETE /v1/wardsugar/{id}`
- `POST /v1/wardtemp`
- `GET /v1/wardtemp/{id}`
- `DELETE /v1/wardtemp/{id}`
- `POST /v1/wardweight`
- `GET /v1/wardweight/{id}`
- `DELETE /v1/wardweight/{id}`

### Transfers
- `GET /v1/transfer`
- `POST /v1/transfer`
- `PATCH /v1/transfer/{id}/status`

### Complaints
- `GET /v1/complaints`
- `POST /v1/complaints`
- `PATCH /v1/complaints/{id}/status`
- `POST /v1/ward`
- `GET /v1/ward/{id}`
- `DELETE /v1/ward/{id}`

### Token + Media Uploads
- `POST /v1/agora/token`
- `GET /v1/files/labresult/{id}`
- `GET /v1/files/certlice/{id}`
- `GET /files/labresult/{filename}` (signed)
- `GET /files/certlices/hospital/{filename}` (signed)
- `GET /files/certlices/carer/{filename}` (signed)
- `POST /v1/patient/uploadimage`
- `POST /v1/carer/uploadimage`
- `POST /v1/hospital/uploadimage`

### Payment Processor (Paystack)
- `POST /v1/paymentprocessing/create_transfer_recipient`
- `POST /v1/paymentprocessing/resolve_account_details`
- `POST /v1/paymentprocessing/update_transfer_recipient`
- `POST /v1/paymentprocessing/delete_transfer_recipient`
- `POST /v1/paymentprocessing/initiate_transfer`
- `POST /v1/paymentprocessing/finalize_transfer`
- `POST /v1/paymentprocessing/verify_transfer`
- `POST /v1/paymentprocessing/create_refund`
- `POST /v1/paymentprocessing/fetch_refund`

### Core CRUD Resources
- `POST /v1/appointment` | `GET /v1/appointment/{id}` | `DELETE /v1/appointment/{id}`
- Appointment statuses: `pending_payment`, `scheduled`, `in_progress`, `completed`, `cancelled`, `no_show`.
- Appointment transitions set `status_description` + timestamps and enforce payment verification before scheduling.
- `POST /v1/certlice` | `GET /v1/certlice/{id}` | `DELETE /v1/certlice/{id}`
- `POST /v1/genvital` | `GET /v1/genvital/{id}` | `DELETE /v1/genvital/{id}`
- `GET /v1/genvital/{id}/audit`
- `POST /v1/carer` | `GET /v1/carer/{id}` | `DELETE /v1/carer/{id}`
- `POST /v1/drug` | `GET /v1/drug/{id}` | `DELETE /v1/drug/{id}`
- `POST /v1/complaints` | `GET /v1/complaints/{id}` | `DELETE /v1/complaints/{id}`
- `GET /v1/complaints/{id}/audit`
- `POST /v1/consultation` | `GET /v1/consultation/{id}` | `DELETE /v1/consultation/{id}`
- Consultation creation requires subtype payload (`vConsultation` or `hConsultation`) based on treatment type; payment can be deferred with `status=pending_payment`.
- Consultation transitions set `status_description` + timestamps, enforce cancellation/no-show rules, and require verified payment before scheduling.
- `POST /v1/hconsultation` | `GET /v1/hconsultation/{id}` | `DELETE /v1/hconsultation/{id}`
- `POST /v1/vconsultation` | `GET /v1/vconsultation/{id}` | `DELETE /v1/vconsultation/{id}`
- `POST /v1/hospital` | `GET /v1/hospital/{id}` | `DELETE /v1/hospital/{id}`
- `POST /v1/labresult` | `GET /v1/labresult/{id}` | `DELETE /v1/labresult/{id}`
- `GET /v1/labresult/{id}/files`
- `POST /v1/labresult/{id}/restore`
- `GET /v1/labresult/{id}/audit`
- `POST /v1/labtest` | `GET /v1/labtest/{id}` | `DELETE /v1/labtest/{id}`
- `POST /v1/patient` | `GET /v1/patient/{id}` | `DELETE /v1/patient/{id}`
- `POST /v1/payment` | `GET /v1/payment/{id}` | `DELETE /v1/payment/{id}`
- `POST /v1/payment/webhook`
- `POST /v1/paymentprocessing/create_transfer_recipient`
- `POST /v1/paymentprocessing/resolve_account_details`
- `POST /v1/paymentprocessing/update_transfer_recipient`
- `POST /v1/paymentprocessing/delete_transfer_recipient`
- `POST /v1/paymentprocessing/initiate_transfer`
- `POST /v1/paymentprocessing/finalize_transfer`
- `POST /v1/paymentprocessing/verify_transfer`
- `POST /v1/paymentprocessing/create_refund`
- `POST /v1/paymentprocessing/fetch_refund`
- Payment statuses: `pending`, `processing`, `paid`, `failed`, `cancelled`, `refund_pending`, `refunded`.
- `POST /v1/agora/token`
- `POST /v1/review` | `GET /v1/review/{id}` | `DELETE /v1/review/{id}`
- `GET /v1/review/{id}/audit`
- `POST /v1/teletest` | `GET /v1/teletest/{id}` | `DELETE /v1/teletest/{id}`
- Teletest creation requires patient/hospital role; payment can be deferred with `status=pending_payment`.
- Teletest transitions set `status_description` + timestamps, enforce cancellation/no-show rules, and require verified payment before scheduling.
- `POST /v1/test` | `GET /v1/test/{id}` | `DELETE /v1/test/{id}`
- `POST /v1/ward` | `GET /v1/ward/{id}` | `DELETE /v1/ward/{id}`
- `POST /v1/transfer` | `GET /v1/transfer/{id}` | `DELETE /v1/transfer/{id}`

## Android Client API (IwosanOnlineAPI)
The Android client maps to the same endpoints. See `IwosanOnlineAPI.java` for exact parameter names and expected payloads.

## Notes
- Auth endpoints exist both versioned and unversioned for backward compatibility.
- Update endpoints were removed in favor of proper HTTP verbs; client may need updates.
- Role-based middleware is enforced for dashboard routes (`role.patient`, `role.carer`, `role.hospital`).
