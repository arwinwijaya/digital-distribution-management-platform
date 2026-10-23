# Phase 4 — Example Mapping: Order UX (Outlet + Sales)

**Status:** Draft for independent edge-case review. No design proposal yet.

## Context and boundary

- This spec covers two separate creation flows: outlet order creation (`OrderForm`) and sales-assisted order creation for a territory outlet (`/sales/orders`).
- Outlet behavior includes online submit and offline local queue/sync. Sales submits directly to `/sales/orders`; do not assume it shares outlet's offline queue or idempotency behavior.
- Focus: user understanding of validation, whether an order was created, and what to do after failure. Existing mechanics should not be restated as absent features.
- User decisions: (1) do not send an outlet's queued order under a different account; hold until its originating account returns; (2) if sales submission outcome is uncertain, do not automatically retry or state definite failure; tell user outcome is unconfirmed and direct them to inspect their order list before retrying.
- No visual redesign, broad navigation changes, new queue for sales, or implementation is authorized by the current exploration.

## Stories, rules, examples

### Story O — Outlet places an order
As an outlet user, I want to submit a product order online or while offline and understand whether it was created or only queued, so I can avoid duplicate orders and know when action is still needed.

**Rules**
1. An empty cart cannot be submitted as an order.
2. Online success is confirmed only after the server returns success; the confirmation identifies the created order and its status.
3. If online submit fails, show an actionable error and do not show a success confirmation; preserve enough context to let the user correct/retry without silently losing selections.
4. Offline submission may be described as queued only after durable local persistence has succeeded. It is not a server-created order.
5. A queued order is sent only under the originating account. If another account is active, hold it; resume only when the originating account is authenticated. Re-check account identity at send time; if the active account changes during a flush, do not send subsequent items for the prior account.
6. If a legacy queued item has no verifiable originating account, hold it; never send it under the active account. It may be removed only after the user deliberately chooses a delete action and confirms it.
7. After reconnection, successful queue sends are removed; failed sends remain pending and the UI must not imply that they were created. What per-order recovery affordance is supported remains to be confirmed from product/repository evidence.
8. If the queue store is not ready or durable persistence fails, do not claim the order is queued; explain that it was not safely saved and retain the cart where possible.

**Examples**
- Positive quantity for an active in-stock product online + HTTP 201 → show order ID and server status; clear submitted cart.
- No selected products → inline validation; no POST and no queue entry.
- Server responds with stock/validation error → explain error; no success message; retain selection for correction.
- Offline, queue write succeeds → pending synchronization count/status; do not show a server order ID or success state.
- Offline, persistence fails (e.g. storage quota or queue not ready) → do not claim queued; preserve cart and explain the save failed.
- Queued item receives non-2xx/transport failure on flush → keep it pending, show unresolved state and a way to understand retry status; no created confirmation.
- Account A queues order, account B logs in → do not submit A's item using B's credentials; keep it held until A returns.
- Account A's flush begins, then account B becomes active → stop sending further A-owned items; no queued item is sent with B's credentials.
- Legacy queued item has no verifiable owner → hold it without sending; remove only after a deliberate delete action and explicit confirmation.
- Account A returns online and re-authenticates → eligible for sync; successful response removes the item and user can distinguish synchronized from still pending.

### Story S — Sales user creates an order for a territory outlet
As a sales user, I want to choose an outlet assigned to my territory, select products, and know whether the server created the order, so I can place a correct order for the intended outlet.

**Rules**
1. The available outlet list is fetched from the authenticated territory-scoped endpoint. A territory assignment failure is distinct from an empty outlet list or generic load failure.
2. The user must choose an outlet and at least one product with quantity greater than zero before submission; invalid forms are not posted.
3. Server success confirms the order ID, intended outlet, and total; reset quantities only after confirmed success.
4. A definite server rejection is shown as an error without success confirmation; preserve selections for correction where safe.
5. If the request's outcome is uncertain (e.g. timeout/connection loss after request), do not automatically resubmit and do not label it definitively failed. Tell the user it is unconfirmed and provide access to the authenticated sales user's order history so they can check before retrying.
6. This page has no evidenced sales offline queue; do not describe its order as queued or promise offline submission.

**Examples**
- Territory-scoped outlets and products load; user selects outlet + positive quantities; HTTP 201 → show ID/outlet/total and clear quantities.
- No outlet selected → local validation message; no POST.
- No positive-quantity product → local validation message; no POST.
- `/sales/outlets` returns territory-unassigned 403 → show territory-specific guidance, not generic empty catalog.
- Product/outlet loading fails → distinguish load error and do not allow an invalid submit.
- `/sales/orders` definitively rejects → show server error; retain selection to correct/retry.
- Request is sent but connection drops before reliable response → show “hasil belum terkonfirmasi”, no automatic retry. Provide access to the authenticated sales user's order history, where they can check whether an order for the selected outlet and item/amount appears before deciding to submit again. The history view/API is a new scoped capability and must isolate the current sales user.

## GWT Scenarios

### Outlet — online

**Scenario O1: Confirm a successful online order**
Given an authenticated outlet user is online and has selected an active product with available stock and positive quantity
When the user submits the order and the API returns HTTP 201 with order ID `ORD-999` and status `pending`
Then the UI shows that `ORD-999` was created and displays its server status
And the submitted cart is cleared
And no offline queue entry is added.

**Scenario O2: Reject an empty order**
Given an authenticated outlet user has no positive-quantity products selected
When the user presses “Kirim pesanan”
Then the UI explains that at least one product must be selected
And it does not call the order API or enqueue an order.

**Scenario O3: Keep selection after a definitive server validation error**
Given an authenticated outlet user has selected a product and quantity
When the online order API returns a validation/stock rejection
Then the UI shows the returned error without a success confirmation
And keeps the selection available for correction.

### Outlet — offline and synchronization

**Scenario O4: Distinguish a durably queued order from a created order**
Given an authenticated outlet user is offline and the queue store is ready
When the user submits a valid order and local persistence succeeds
Then the UI indicates that the order is waiting for synchronization
And does not claim that the server created it or show a server order ID.

**Scenario O5: Do not claim queued when local persistence is unavailable**
Given an authenticated outlet user is offline and the queue is unavailable or durable storage fails
When the user submits a valid order
Then the UI does not report that the order is queued
And preserves the cart where possible
And explains that the order was not safely saved.

**Scenario O6: Keep a failed synchronization visibly unresolved**
Given a locally queued order is pending
When reconnection occurs and the server rejects the request or the transport fails
Then the order remains in the local queue
And the UI continues to show it as not synchronized, not as server-created.

**Scenario O7: Hold queued orders across account changes**
Given account A has a locally queued outlet order
When account B becomes the active account on the device
Then the order is not sent using B's credentials
And it remains held until account A is authenticated again.

**Scenario O8: Resume held order only for its originating account**
Given account A's queued order is held while another account was active
When account A authenticates and the device is online
Then the queue may send the order with A's credentials
And the UI updates the pending state based on the server result.

**Scenario O9: Stop a flush when the active account changes**
Given a flush is sending queued orders owned by account A
When account B becomes the active account before the flush completes
Then no subsequent A-owned order is sent using B's credentials
And remaining A-owned orders stay held until A authenticates again.

**Scenario O10: Hold and explicitly delete an unowned legacy item**
Given a legacy queued item has no verifiable originating account
When a different account is active
Then the item is not sent with that account's credentials
And it remains held unless the user deliberately selects delete and confirms
And after confirmed deletion it is removed without being sent.

### Sales — direct online submit

**Scenario S1: Confirm a territory-scoped sales order**
Given an authenticated sales user has loaded an outlet from their territory and selected positive product quantities
When the user submits and `/sales/orders` returns HTTP 201 with ID `ORD-0007`, the selected outlet, and total
Then the UI confirms the order ID, outlet, and total
And clears quantities only after the confirmed response.

**Scenario S2: Require an outlet and at least one product**
Given a sales user has not selected an outlet or has no positive-quantity items
When the user submits the form
Then the UI identifies the missing requirement
And does not send a POST to `/sales/orders`.

**Scenario S3: Explain missing territory assignment**
Given the sales outlet endpoint returns HTTP 403 because no territory is assigned
When the sales page loads
Then it displays territory-specific guidance to contact an administrator
And does not present the condition as an ordinary empty outlet list.

**Scenario S4: Preserve form after a definite rejection**
Given the sales user selected an outlet and product quantities
When the server returns a definite non-success response
Then the UI shows the rejection without success confirmation
And preserves the selections so the user can correct them.

**Scenario S5: Do not retry automatically after an uncertain response**
Given a valid sales order request was sent but the client loses the connection before receiving a reliable response
When the page cannot determine whether the server created the order
Then the UI says the result is unconfirmed
And does not automatically submit the order again or claim definite failure
And provides access to order history created by the authenticated sales user so they can inspect whether the order exists before deciding whether to retry.

**Scenario S6: Absence from history does not prove submission failure**
Given the sales user has an unconfirmed submission and opens their own order history
When no matching order is currently visible
Then the UI does not treat the absence as proof that the server did not create the order
And does not automatically retry or describe retry as safe.

**Scenario S7: Keep sales history private to its creator**
Given sales user A and sales user B have created separate orders
When sales user A opens their order history
Then the history includes orders created by A
And does not disclose orders created by B.

## Open questions / verification risks

- **Architecture discovery required:** The user chose a sales-created order history for uncertain-result verification. No such sales screen is present in the inspected path, and the general order list endpoint is guarded for admin/outlet, not sales. Validate endpoint/authorization scope before design. History must show only orders created by the authenticated sales user.
- **Queue ownership:** Current queue uses one local key `ddp_offline_order_queue:v1`, and the sender uses the current token. User decided cross-account send must be held; implementing this may require per-item owner identity or equivalent safe mechanism. Must not store credentials in the queue. Legacy items without a verifiable owner must be held and only removed by confirmed user action.
- **Durability gap:** `LocalStorageAdapter.set()` swallows exceptions, and `useOrderSubmission` can fall through to online submit if offline but `queueRef.current` is not ready. Acceptance requires correcting/reporting these states, but exact user-facing text can be finalized during design.
- **Retry result details:** queue flusher exposes aggregate sent/failed counts; identify whether individual errors/retry controls are available before specifying per-item copy.
- Existing form tests cover online success, offline enqueue message, and flush trigger; queue tests cover retention on failed flush. They do not establish all proposed user-visible requirements.
