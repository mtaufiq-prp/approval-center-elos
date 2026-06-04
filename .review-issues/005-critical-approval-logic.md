# [Critical][Approval Logic] Keputusan RETURN/CANCEL menjadikan request APPROVED (fail-open) karena fallback completeProcess(COMPLETED)

## Summary
Saat approver menekan RETURN atau CANCEL, engine tidak menemukan edge RETURN/CANCEL (tidak ada di flow), lalu jatuh ke fallback `completeProcess(COMPLETED)` yang dipetakan menjadi `request_status=APPROVED`.

## Location
- `app/Http/Controllers/Web/InboxController.php:250` (izinkan `RETURN,CANCEL`)
- `app/Services/FlowEngineService.php:137-138` (map RETURN→RETURNED, CANCEL→CANCELLED) lalu `:242` (APPROVAL tanpa next → `completeProcess(COMPLETED)`) → `:473` (`COMPLETED`→`APPROVED`)
- `database/seeders/SfaReturFlowV2Seeder.php` (tidak ada edge `action_code='RETURN'`/`'CANCEL'`)

## Problem
Web menerima `decision_code in:APPROVE,REJECT,RETURN,CANCEL`. Engine set task RETURNED/CANCELLED lalu `traverseFromNode(..., 'RETURN')`. Filter edge action='RETURN' tak ada match (seeder tak punya). Tidak ada default ber-action NULL. → null → `completeProcess(COMPLETED)` → request `APPROVED`.

## Impact
Approver yang minta dokumen DIKEMBALIKAN (RETURN) atau membatalkan (CANCEL) justru menyetujui request. Task tercatat RETURNED/CANCELLED tetapi request & callback = APPROVED. State machine korup + persetujuan tak diinginkan.

## Risk Scenario
1. BMH pilih "RETURN" + catatan "lengkapi lampiran".
2. Task→RETURNED; engine traverse RETURN → tak ada edge → COMPLETED.
3. request APPROVED; SFA terima callback approved; retur diproses padahal diminta dikembalikan.

## Recommended Fix
Tangani RETURN/CANCEL eksplisit SEBELUM traversal: RETURN → `request_status=RETURNED` + hentikan token; CANCEL → `CANCELLED`. Untuk action non-APPROVE yang tidak punya next edge, JANGAN default ke COMPLETED — set status sesuai action atau ERROR. Alternatif: batasi `decision_code` web ke aksi yang punya edge pada node tsb.

## Acceptance Criteria
- [ ] RETURN → `request_status=RETURNED` (bukan APPROVED) + callback sesuai
- [ ] CANCEL → `CANCELLED`
- [ ] Fallback "tidak ada next node" untuk aksi non-APPROVE tidak pernah → APPROVED
- [ ] Test untuk RETURN dan CANCEL

## Priority
P0 - Must fix before production
