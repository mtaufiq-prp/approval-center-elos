# [Medium][Reliability] Auto-forward node REVIEW/NOTIFICATION/SYSTEM tanpa loop-guard runtime & fail-open ke APPROVED

## Summary
Cabang fallback `traverseFromNode` auto-forward node non-START/APPROVAL/DECISION/END tanpa `visited`/hop-counter (berbeda dari `projectApprovalRoute` yang punya guard<100). Bila buntu → `completeProcess(COMPLETED)` → APPROVED. **POTENTIAL RISK (tergantung konfigurasi admin).**

## Location
- `app/Services/FlowEngineService.php:261-267` (fallback) + rekursi `enterNode`→`traverseFromNode:301`

## Problem
Tidak ada batas hop runtime. Cycle melibatkan node SYSTEM/REVIEW (A→B→A via edge AUTO) → rekursi tak terbatas → stack overflow/timeout. Node REVIEW yang harusnya menahan justru auto-forward; buntu → COMPLETED→APPROVED.

## Impact
Potensi infinite recursion (DoS proses); node REVIEW di-skip diam-diam jadi approved.

## Risk Scenario
Admin tambah node SYSTEM dengan edge balik ke DECISION tanpa kondisi → instance loop tak terbatas.

## Recommended Fix
Hop-counter per `completeCurrentTask`/`startProcess` (maks ~200) dan/atau `visited` node; lempar ERROR bila terlampaui. Node buntu non-approval → ERROR, bukan COMPLETED→APPROVED. Implementasi REVIEW/NOTIFICATION/SYSTEM eksplisit.

## Acceptance Criteria
- [ ] Cycle node non-approval → ERROR (bukan crash)
- [ ] Node tipe tak dikenal/buntu tidak pernah → APPROVED diam-diam

## Priority
P2 - Should fix soon
