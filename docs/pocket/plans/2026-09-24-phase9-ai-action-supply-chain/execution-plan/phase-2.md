# Phase 2 — Draft + Approval + Execute API

**Date:** 2026-09-24
**Original plan:** ../execution-plan.md
**Prerequisite:** Phase 1 DONE
**Contains tasks:** {T5, T6, T7, T8}
**Unlocks next:** Phase 3, Phase 4

---

## Task List

Total: 4 tasks | Prerequisite phases must be complete before starting

- **T5:** Endpoint create draft action (`POST /admin/recommendation-actions`) [depends: T2, T4] → [tasks/T5-create-draft-action-endpoint.md](tasks/T5-create-draft-action-endpoint.md)
- **T6:** Approve/reject + audit append-only [depends: T5] → [tasks/T6-approve-reject-audit.md](tasks/T6-approve-reject-audit.md)
- **T7:** Execute `draft_order` via `OrderCreationService` [depends: T6] → [tasks/T7-execute-draft-order.md](tasks/T7-execute-draft-order.md)
- **T8:** Execute `draft_campaign` via `PromotionService` [depends: T6] → [tasks/T8-execute-draft-campaign.md](tasks/T8-execute-draft-campaign.md)

T7, T8 paralel setelah T6 selesai.

---

## Phase Completion Gate

DONE when ALL of the following:
- Every task in this phase: status DONE
- `php artisan test` hijau untuk test baru (`CreateDraftActionTest`, `ApproveRejectActionTest`, `ExecuteDraftOrderTest`, `ExecuteDraftCampaignTest`)
- `php artisan migrate` + rollback bersih
- All commits created with correct format
- No task has status BLOCKED or NEEDS_CONTEXT