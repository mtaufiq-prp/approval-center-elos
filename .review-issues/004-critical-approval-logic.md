# [Critical][Approval Logic] START → edge SUBMIT tidak pernah match saat startProcess → request langsung APPROVED tanpa approval

## Summary
Pada `startProcess`, traversal dari node START tidak pernah menemukan edge berikutnya karena edge START→DECISION ber-`action_code='SUBMIT'` di-exclude oleh filter (actionCode=null). Akibatnya proses langsung COMPLETED → APPROVED tanpa membuat task apapun.

## Location
- `app/Services/FlowEngineService.php:89` (`startProcess` memanggil `traverseFromNode(..., null)`)
- `app/Services/FlowEngineService.php:225-230` (cabang START → `completeProcess(COMPLETED)`)
- filter edge di `findNextEligibleNode` (action_code IS NULL OR 0 saat actionCode null)
- `database/seeders/SfaReturFlowV2Seeder.php:124` (edge START→DECISION `action_code='SUBMIT'`)

## Problem
`startProcess` traverse dengan `actionCode=null`. Filter menjadi `action_code IS NULL OR (0)` → hanya edge ber-action NULL yang lolos. Edge START→DECISION di seeder ber-`action_code='SUBMIT'` → tidak pernah match → `findNextEligibleNode` null → `completeProcess(COMPLETED)` → `request_status=APPROVED`.

Bukti tak teruji: `SfaReturFlowV2TrialSeeder` membuat task manual, tidak memanggil `startProcess` — jalur START asli engine belum pernah dieksekusi terhadap flow seeded.

## Impact
**Fail-open total pada entry point.** Setiap submission via `ApprovalSubmitController` pada flow seeded otomatis APPROVED tanpa approver mana pun.

## Risk Scenario
1. SFA POST submit retur 30 juta.
2. startProcess → START → tidak ada edge action NULL → completeProcess(COMPLETED).
3. request APPROVED, callback "approved", tanpa BMH/RRM/NRM/CEO.

## Recommended Fix
Perlakukan edge SUBMIT/AUTO sebagai eligible pada traversal otomatis: panggil `traverseFromNode($startNode, ..., 'SUBMIT')`, ATAU ubah filter agar saat actionCode null/auto, edge `action_code IN ('SUBMIT','AUTO','AUTO_APPROVE', NULL)` lolos. Samakan semantik dengan `peekNextNode`/`projectApprovalRoute`.

## Acceptance Criteria
- [ ] `startProcess` pada flow seeded membuat task di node BMH (bukan langsung COMPLETED)
- [ ] Edge START→DECISION ber-action SUBMIT ikut terevaluasi
- [ ] Runtime forward & `projectApprovalRoute` konsisten untuk konteks yang sama
- [ ] Test: submit → instance RUNNING dengan 1 task OPEN

## Priority
P0 - Must fix before production
