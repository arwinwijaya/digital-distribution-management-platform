# Task T7 — WhatsApp Integration

**Phase:** 2
**Depends:** T2
**Source plan:** ../../execution-plan.md

---

### Pocket Packet

### Task 7: WhatsApp Integration [depends: T2] [parallel: T6]

## OBJECTIVE
Implement WhatsApp Business API integration for orders and notifications. This enables mobile-first ordering.

Files:
- Create: `apps/api/app/Services/WhatsAppService.php`
- Create: `apps/api/app/Http/Controllers/WhatsAppController.php`
- Create: `apps/api/app/Models/WhatsAppMessage.php`
- Test: `apps/api/tests/Feature/WhatsAppTest.php`

Steps:
1. Write failing test for: WhatsApp order
   Test file: `apps/api/tests/Feature/WhatsAppTest.php`
   Level: integration
   Test intent: Given outlet sends WhatsApp message, When ordering, Then order is created
   Exercise through: POST /api/whatsapp/webhook
   Test doubles: mock WhatsApp API
   Expected RED: WhatsApp webhook endpoint does not exist

2. Run test — verify FAIL: `cd apps/api && php artisan test --filter WhatsAppTest`

3. Implement minimal code to satisfy the test:
   File: `apps/api/app/Services/WhatsAppService.php`
   Implement: WhatsApp webhook handler

4. Run test — verify PASS: `cd apps/api && php artisan test --filter WhatsAppTest`

5. Refactor while green (bounded):
   - Extract message parsing logic
   - Re-run test: `cd apps/api && php artisan test --filter WhatsAppTest`

6. Commit:
   `git add . && git commit -m "feat(whatsapp): add WhatsApp order integration"`

## REFERENCES LOADED
docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md — rule: Phase6 WhatsApp Integration
Phase6 deliverables: WhatsApp order, notifications, catalog sharing
Success criteria: WhatsApp adoption >50%
Risk: WhatsApp Business API approval lead time panjang

## WHY THIS APPROACH
Complexity: standard
Justification: Critical for Indonesian warung ecosystem

## SANDWICH CONTEXT
[CRITICAL: Must handle WhatsApp Business API approval delay]
You are implementing WhatsApp Integration for Digital Distribution Platform.
Spec: docs/pocket/spec/2025-09-08-development-phasing/development-phasing-spec.md
Design decision: Option B (Flexible Overlap Execution)
Files in scope: apps/api/app/Services/WhatsAppService.php, related controllers
Available after: T2 (Outlet Onboarding)
Architecture rule: Laravel service class, WhatsApp Business API integration
[RESTATE: Must handle WhatsApp Business API approval delay]

## DELIVERABLE
Given outlet sends WhatsApp message, When ordering, Then order is created
Given order is confirmed, When notifying, Then WhatsApp notification is sent
Given product catalog exists, When sharing, Then catalog is shared via WhatsApp

Format: DONE | DONE_WITH_CONCERNS | NEEDS_CONTEXT | BLOCKED

## QUALITY BAR
Must-have:
  - WhatsApp webhook handling
  - Order creation via WhatsApp
  - Notification system

Must-not-have:
  - Complex chatbot logic (keep simple for MVP)

Open question risks:
  - WhatsApp Business API approval timeline

Rollback note:
  - Can disable WhatsApp integration if API approval delayed

## STOP CONDITIONS
Done when: WhatsApp orders work, notifications sent
Uncertain when: WhatsApp API approval delayed
Escalate when: Cannot get WhatsApp Business API approval
