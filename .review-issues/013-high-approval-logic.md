# [High][Approval Logic] Celah boundary threshold nilai retur (pecahan) tidak ter-cover & batas DECISION vs edge BMH/RRM tidak konsisten

## Summary
Batas tier nilai retur memakai bilangan bulat dengan operator inklusif/eksklusif campuran, padahal SUM bisa pecahan → nilai di celah (mis. 15.000.000,50) jatuh ke jalur lebih rendah dari seharusnya.

## Location
- `database/seeders/SfaReturFlowV2Seeder.php`: DECISION P2 `between(15000001,25000000)`, P3 `between(5000001,15000000)`, P1 `SUM_GT 25000000`, default P4; edge BMH `SUM_LTE 5000000`, RRM `between(5000001,15000000)`
- `app/Services/ConditionEvaluatorService.php` (SUM ops, float)

## Problem
- Nilai (5.000.000, 5.000.001) mis 5.000.000,5: P3 butuh `≥5.000.001` → false → default P4 (BMH saja). Under-routed.
- Nilai (15.000.000, 15.000.001) mis 15.000.000,5: P3 `≤15.000.000` false, P2 `≥15.000.001` false, P1 `>25jt` false → default P4 (BMH saja). Severely under-routed.
- Batas di DECISION vs edge end BMH/RRM tidak dari sumber tunggal → hasil bisa inkonsisten.

## Impact
Transaksi dengan komponen pecahan di sekitar batas 5jt/15jt lolos ke jalur approval lebih rendah → kontrol nilai bocor.

## Risk Scenario
Retur SUM=15.000.000,75 → default P4 → hanya BMH; NRM/CEO tidak dilibatkan.

## Recommended Fix
Gunakan batas kontigu tanpa gap: P3=`SUM_GT 5000000 AND SUM_LTE 15000000`; P2=`SUM_GT 15000000 AND SUM_LTE 25000000`; P1=`SUM_GT 25000000`; P4=sisanya. Pastikan edge end BMH/RRM memakai batas identik (idealnya rujuk tier `_computed` yang dihitung sekali di enrichment).

## Acceptance Criteria
- [ ] Boundary 5.000.000,01 / 15.000.000,01 / 25.000.000,01 ter-route benar
- [ ] Batas DECISION & edge end identik (single source of truth)
- [ ] Setiap nilai ≥0 ter-cover tepat satu tier
- [ ] Test boundary pecahan

## Priority
P1 - Important before production
