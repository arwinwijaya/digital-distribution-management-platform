# API Request Flow (end to end)

```
Browser fetch(apiUrl(path), {Authorization: Bearer <JWT>})
  -> routes/api.php (prefix /api)
  -> group api: ThrottleRequests:api -> SubstituteBindings -> AttachCorrelationId
  -> auth:api (JWT guard)
  -> reject.stale_jwt (jwt_version claim == users.jwt_version)
  -> deny.finance / pre_pilot (route-specific)
  -> Controller (FormRequest validation)
  -> Service (DB::transaction + lockForUpdate where mutation)
  -> Eloquent -> PostgreSQL  (or WhatsApp Cloud API / Mailpit)
  -> {status,data} + X-Correlation-ID
```

## Example: POST /api/orders
`OrderController::store` -> `requestIdentity(outletId, idempotency_key)` = sha256 of `{outlet_id, request_identity}` -> `OrderCreationService::create` (see `mem:api/domain`) -> 201 when created, 200 when replaying an existing order -> `formatOrderResponse`.

## Example: POST /api/orders/{id}/approve
`OrderController::approve` -> `runApprovalTransaction` -> `InvoiceService::createForApprovedOrder` -> `approvalResponse`.

## Error contract
- 401 invalid/expired/stale JWT; 403 unauthorized (deny.finance or `abort_unless`); 409 `ConflictHttpException`; 422 `ValidationException::withMessages`; 503 `{code:'pre_pilot_disabled'}`; 429 rate limit (60/min).
- Failures still carry `X-Correlation-ID`; AttachCorrelationId renders exceptions and records a fail-open operational event when the gate is on.
