<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\TblFlowDefinition;
use App\Models\TblFlowVersion;
use App\Models\TblFlowStep;
use App\Models\TblFlowTransition;
use App\Models\TblStepAssigneeRule;
use App\Models\TblUser;

class SfaReturFlowV2Seeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  SFA Retur Flow V2 Seeder');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $def = TblFlowDefinition::where('flow_code', 'FLOW_SFA_RETUR')->first();
        if (! $def) { $this->command->error('Flow Definition FLOW_SFA_RETUR tidak ditemukan!'); return; }

        // Hapus V2 lama jika ada
        $old = TblFlowVersion::where('idtblflow_definition', $def->idtblflow_definition)
            ->where('version_no', 2)->first();
        if ($old) {
            $vid = $old->idtblflow_version;
            $stepIds = TblFlowStep::where('idtblflow_version', $vid)->pluck('idtblflow_step');
            TblStepAssigneeRule::whereIn('idtblflow_step', $stepIds)->delete();
            TblFlowTransition::where('idtblflow_version', $vid)->delete();
            TblFlowStep::where('idtblflow_version', $vid)->delete();
            $old->delete();
            $this->command->info('  Flow V2 lama dihapus.');
        }

        $nrmUser = TblUser::where('user_ref', '11990056')->first();

        if (! $nrmUser) {
            $this->command->error('User NRM (11990056 - Julius Kurata) tidak ditemukan!');
            $this->command->line('  Jalankan SfaUsersAndBranchMapSeeder dulu.');
            return;
        }
        $this->command->line('  NRM: '.$nrmUser->full_name.' ✓');
        $this->command->line('  PKG & CEO: via JOBTITLE (JT0286 & JT0526) — lookup otomatis dari db_master');

        DB::transaction(function () use ($def, $nrmUser) {
            $version = TblFlowVersion::create([
                'idtblflow_definition' => $def->idtblflow_definition,
                'version_no'           => 2,
                'version_name'         => 'v2 — Routing Nilai & Alasan Retur',
                'status'               => 'DRAFT',
                'effective_start'      => now(),
                'effective_end'        => '2035-12-31',
                'validation_status'    => 'DRAFT',
            ]);
            $vid = $version->idtblflow_version;
            $this->command->info("  Flow Version V2 dibuat (id={$vid})");

            // ── NODES ────────────────────────────────────────────────────
            $nodeDefs = [
                ['START',        'Mulai',                 'START',    'NONE',       10,   80,  300, null, null],
                ['DECISION_JALUR','Tentukan Jalur',       'DECISION', 'EXCLUSIVE',  20,  280,  300, null, null],
                ['BMH',          'Persetujuan BMH',       'APPROVAL', 'NONE',       30,  520,  300, 'ANY', 48],
                ['RRM',          'Persetujuan RRM',       'APPROVAL', 'NONE',       40,  760,  300, 'ANY', 48],
                ['NRM',          'Persetujuan NRM/BU',    'APPROVAL', 'NONE',       50, 1000,  300, 'ANY', 48],
                ['PMM',          'Persetujuan PMM',       'APPROVAL', 'NONE',       60, 1240,  120, 'ANY', 48],
                ['PD',           'Persetujuan PD Manager','APPROVAL', 'NONE',       70, 1480,  120, 'ANY', 48],
                ['PKG',          'Persetujuan Packaging', 'APPROVAL', 'NONE',       75, 1240,  480, 'ANY', 48],
                ['CEO',          'Persetujuan CEO',       'APPROVAL', 'NONE',       80, 1720,  300, 'ANY', 72],
                ['END',          'Selesai',               'END',      'NONE',       90, 1960,  300, null, null],
            ];

            $nodes = [];
            foreach ($nodeDefs as [$code,$name,$type,$gw,$order,$px,$py,$mode,$sla]) {
                $nodes[$code] = TblFlowStep::create([
                    'idtblflow_version' => $vid,
                    'node_code'         => $code,
                    'step_code'         => $code,
                    'step_name'         => $name,
                    'step_type'         => $type,
                    'gateway_type'      => $gw,
                    'step_order'        => $order,
                    'approval_mode'     => $mode ?? 'ANY',
                    'reject_behavior'   => 'END_REJECTED',
                    'pos_x'             => $px,
                    'pos_y'             => $py,
                    'sla_hours'         => $sla,
                ]);
            }
            $this->command->info('  Nodes: '.count($nodes).' dibuat.');

            // ── ASSIGNEE RULES ────────────────────────────────────────────
            // JOBTITLE: lookup otomatis ke db_master.tbemployeeit berdasarkan jobtitleid
            // Keuntungan: saat ganti pejabat PKG atau CEO, tidak perlu ubah konfigurasi flow
            $rules = [
                'BMH' => ['FIELD_USER', '_computed.bmh_user_ref'],
                'RRM' => ['FIELD_USER', '_computed.rrm_user_ref'],
                'NRM' => ['USER',       $nrmUser->user_ref],
                'PMM' => ['FIELD_USER', '_computed.pmm_user_ref'],
                'PD'  => ['FIELD_USER', '_computed.pd_user_ref'],
                'PKG' => ['JOBTITLE',   'JT0286'],  // Packaging Management Sub Department Head
                'CEO' => ['JOBTITLE',   'JT0526'],  // Chief Executive Officer
            ];
            foreach ($rules as $code => [$type, $val]) {
                TblStepAssigneeRule::create([
                    'idtblflow_step'  => $nodes[$code]->idtblflow_step,
                    'assignee_type'   => $type,
                    'assignee_value'  => $val,
                    'priority_no'     => 1,
                    'is_required'     => true,
                    'is_active'       => true,
                ]);
            }
            $this->command->info('  Assignee rules: '.count($rules).' dibuat.');

            // ── EDGES ─────────────────────────────────────────────────────
            $n = $nodes;
            $edges = [];

            // START → DECISION_JALUR
            $edges[] = $this->e($vid,$n['START'],$n['DECISION_JALUR'],'SUBMIT','START_TO_DECISION','Dokumen Masuk',100);

            // DECISION_JALUR → BMH (7 jalur, prioritas menentukan jalur mana)
            $edges[] = $this->e($vid,$n['DECISION_JALUR'],$n['BMH'],'AUTO_APPROVE','D_JALUR_P6','Poin 6: Produk Rusak/Bermasalah',100,
                $this->anyIn('_computed.idmsalasan_list',[61,68]));
            $edges[] = $this->e($vid,$n['DECISION_JALUR'],$n['BMH'],'AUTO_APPROVE','D_JALUR_P7','Poin 7: Kemasan/Label',110,
                $this->anyIn('_computed.idmsalasan_list',[11,33,34,35,36]));
            $edges[] = $this->e($vid,$n['DECISION_JALUR'],$n['BMH'],'AUTO_APPROVE','D_JALUR_P1','Poin 1: Nilai > 25 Juta',120,
                ['op'=>'SUM_GT','field'=>'_computed.total_nilai_retur','value'=>25000000]);
            $edges[] = $this->e($vid,$n['DECISION_JALUR'],$n['BMH'],'AUTO_APPROVE','D_JALUR_P2','Poin 2: Nilai 15-25 Juta',130,
                $this->between('_computed.total_nilai_retur',15000001,25000000));
            $edges[] = $this->e($vid,$n['DECISION_JALUR'],$n['BMH'],'AUTO_APPROVE','D_JALUR_P5','Poin 5: Alasan Khusus',140,
                $this->anyIn('_computed.idmsalasan_list',[56,62,63,64,66]));
            $edges[] = $this->e($vid,$n['DECISION_JALUR'],$n['BMH'],'AUTO_APPROVE','D_JALUR_P3','Poin 3: Nilai 5-15 Juta',150,
                $this->between('_computed.total_nilai_retur',5000001,15000000));
            $edges[] = $this->e($vid,$n['DECISION_JALUR'],$n['BMH'],'AUTO_APPROVE','D_JALUR_P4','Poin 4: Default ≤ 5 Juta',200,
                null,null,true);

            // BMH
            $edges[] = $this->e($vid,$n['BMH'],$n['END'],'REJECT','BMH_REJECT','BMH Tolak',100,null,'REJECTED');
            $edges[] = $this->e($vid,$n['BMH'],$n['END'],'APPROVE','BMH_APPROVE_END_P4','BMH Approve — Selesai (Poin 4)',110,
                $this->and([
                    ['op'=>'SUM_LTE','field'=>'_computed.total_nilai_retur','value'=>5000000],
                    $this->noneIn('_computed.idmsalasan_list',[11,33,34,35,36,56,61,62,63,64,66,68]),
                ]),'APPROVED');
            $edges[] = $this->e($vid,$n['BMH'],$n['RRM'],'APPROVE','BMH_APPROVE_RRM','BMH Approve → RRM',200);

            // RRM
            $edges[] = $this->e($vid,$n['RRM'],$n['END'],'REJECT','RRM_REJECT','RRM Tolak',100,null,'REJECTED');
            $edges[] = $this->e($vid,$n['RRM'],$n['END'],'APPROVE','RRM_APPROVE_END_P3','RRM Approve — Selesai (Poin 3)',110,
                $this->and([
                    $this->between('_computed.total_nilai_retur',5000001,15000000),
                    $this->noneIn('_computed.idmsalasan_list',[11,33,34,35,36,56,61,62,63,64,66,68]),
                ]),'APPROVED');
            $edges[] = $this->e($vid,$n['RRM'],$n['NRM'],'APPROVE','RRM_APPROVE_NRM','RRM Approve → NRM',200);

            // NRM
            $edges[] = $this->e($vid,$n['NRM'],$n['END'],'REJECT','NRM_REJECT','NRM Tolak',100,null,'REJECTED');
            $edges[] = $this->e($vid,$n['NRM'],$n['PMM'],'APPROVE','NRM_APPROVE_PMM','NRM Approve → PMM (Poin 6)',110,
                $this->anyIn('_computed.idmsalasan_list',[61,68]));
            $edges[] = $this->e($vid,$n['NRM'],$n['PKG'],'APPROVE','NRM_APPROVE_PKG','NRM Approve → Packaging (Poin 7)',120,
                $this->anyIn('_computed.idmsalasan_list',[11,33,34,35,36]));
            $edges[] = $this->e($vid,$n['NRM'],$n['CEO'],'APPROVE','NRM_APPROVE_CEO_P1','NRM Approve → CEO (Poin 1)',130,
                ['op'=>'SUM_GT','field'=>'_computed.total_nilai_retur','value'=>25000000]);
            $edges[] = $this->e($vid,$n['NRM'],$n['END'],'APPROVE','NRM_APPROVE_END','NRM Approve — Selesai (Poin 2/5)',200,
                null,'APPROVED',true);

            // PMM → PD
            $edges[] = $this->e($vid,$n['PMM'],$n['END'],'REJECT','PMM_REJECT','PMM Tolak',100,null,'REJECTED');
            $edges[] = $this->e($vid,$n['PMM'],$n['PD'],'APPROVE','PMM_APPROVE_PD','PMM Approve → PD',200);

            // PD → CEO
            $edges[] = $this->e($vid,$n['PD'],$n['END'],'REJECT','PD_REJECT','PD Tolak',100,null,'REJECTED');
            $edges[] = $this->e($vid,$n['PD'],$n['CEO'],'APPROVE','PD_APPROVE_CEO','PD Approve → CEO',200);

            // PKG → CEO
            $edges[] = $this->e($vid,$n['PKG'],$n['END'],'REJECT','PKG_REJECT','Packaging Tolak',100,null,'REJECTED');
            $edges[] = $this->e($vid,$n['PKG'],$n['CEO'],'APPROVE','PKG_APPROVE_CEO','Packaging Approve → CEO',200);

            // CEO → END
            $edges[] = $this->e($vid,$n['CEO'],$n['END'],'REJECT','CEO_REJECT','CEO Tolak',100,null,'REJECTED');
            $edges[] = $this->e($vid,$n['CEO'],$n['END'],'APPROVE','CEO_APPROVE','CEO Approve — Selesai',200,null,'APPROVED');

            foreach ($edges as $data) {
                TblFlowTransition::create($data);
            }
            $this->command->info('  Edges: '.count($edges).' dibuat.');

            $this->command->info('');
            $this->command->info('  ✅  Flow V2 selesai (id='.$vid.').');
            $this->command->info('  → Builder: /workflow/flow-version/'.$vid.'/builder');
        });
    }

    private function e(int $vid,$from,$to,string $action,string $code,string $name,
                        int $priority,?array $cond=null,?string $finalStatus=null,bool $isDefault=false): array
    {
        return [
            'idtblflow_version'   => $vid,
            'idtblflow_step_from' => $from->idtblflow_step,
            'idtblflow_step_to'   => $to->idtblflow_step,
            'transition_code'     => $code,
            'transition_name'     => $name,
            'transition_type'     => 'NORMAL',
            'action_code'         => $action,
            'priority_no'         => $priority,
            'condition_json'      => $cond,
            'final_status'        => $finalStatus,
            'is_default'          => $isDefault ? 1 : 0,
            'is_active'           => 1,
        ];
    }

    private function anyIn(string $field, array $values): array
    { return ['op'=>'ANY_IN','field'=>$field,'value'=>$values]; }

    private function noneIn(string $field, array $values): array
    { return ['op'=>'NONE_IN','field'=>$field,'value'=>$values]; }

    private function between(string $field, float $min, float $max): array
    {
        return ['logic'=>'AND','conditions'=>[
            ['op'=>'SUM_GTE','field'=>$field,'value'=>$min],
            ['op'=>'SUM_LTE','field'=>$field,'value'=>$max],
        ]];
    }

    private function and(array $conditions): array
    { return ['logic'=>'AND','conditions'=>$conditions]; }
}
