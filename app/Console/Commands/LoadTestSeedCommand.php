<?php

namespace App\Console\Commands;

use App\Models\TblApiClient;
use App\Models\TblDocumentType;
use App\Models\TblFlowDefinition;
use App\Models\TblFlowStep;
use App\Models\TblFlowTransition;
use App\Models\TblFlowVersion;
use App\Models\TblRoutingRule;
use App\Models\TblSourceApp;
use App\Models\TblStepAssigneeRule;
use App\Models\TblUser;
use App\Services\ApiClientSecretService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * [DEV ONLY] loadtest:seed
 *
 * Menyiapkan fixture minimal (source app + API client + doc type + flow ACTIVE
 * START→APPROVAL→END + routing rule + approver) untuk uji konkurensi/beban lokal,
 * lalu mencetak CLIENT_KEY / SECRET / DOC_TYPE agar dipakai harness curl.
 *
 * JANGAN dijalankan di produksi. Idempoten via app_code 'LOADTEST'.
 */
class LoadTestSeedCommand extends Command
{
    protected $signature = 'loadtest:seed {--secret=loadtest-secret-12345678}';
    protected $description = '[DEV ONLY] Seed fixture untuk uji konkurensi/beban lokal';

    public function handle(ApiClientSecretService $secrets): int
    {
        if (app()->environment('production')) {
            $this->error('Dilarang di environment production.');
            return self::FAILURE;
        }

        $app = TblSourceApp::firstOrCreate(
            ['app_code' => 'LOADTEST'],
            ['app_name' => 'Load Test App', 'is_active' => 1, 'default_callback_url' => null]
        );

        $docType = TblDocumentType::firstOrCreate(
            ['idtblsource_app' => $app->idtblsource_app, 'doc_code' => 'LT_DOC'],
            ['doc_name' => 'Load Test Doc']
        );

        $def = TblFlowDefinition::firstOrCreate(
            ['flow_code' => 'FLOW_LOADTEST'],
            ['flow_name' => 'Load Test Flow', 'idtblsource_app' => $app->idtblsource_app, 'idtbldocument_type' => $docType->idtbldocument_type]
        );

        $version = TblFlowVersion::where('idtblflow_definition', $def->idtblflow_definition)->first();
        if (! $version) {
            $version = TblFlowVersion::create([
                'idtblflow_definition' => $def->idtblflow_definition,
                'version_no' => 1, 'version_name' => 'v1',
                'status' => TblFlowVersion::STATUS_ACTIVE, 'validation_status' => TblFlowVersion::VALIDATION_VALID,
            ]);
            $vid = $version->idtblflow_version;
            $mk = fn ($code, $type, $order) => TblFlowStep::create([
                'idtblflow_version' => $vid, 'node_code' => $code, 'step_code' => $code,
                'step_name' => $code, 'step_type' => $type, 'gateway_type' => 'NONE',
                'step_order' => $order, 'approval_mode' => 'ANY', 'reject_behavior' => 'END_REJECTED',
            ]);
            $start = $mk('START', 'START', 10);
            $bmh   = $mk('BMH', 'APPROVAL', 20);
            $end   = $mk('END', 'END', 30);
            TblStepAssigneeRule::create([
                'idtblflow_step' => $bmh->idtblflow_step, 'assignee_type' => 'USER',
                'assignee_value' => 'LT_APPROVER', 'priority_no' => 1, 'is_required' => true, 'is_active' => true,
            ]);
            $edge = fn ($from, $to, $action, $code, $p) => TblFlowTransition::create([
                'idtblflow_version' => $vid, 'idtblflow_step_from' => $from->idtblflow_step,
                'idtblflow_step_to' => $to->idtblflow_step, 'transition_code' => $code, 'transition_name' => $code,
                'transition_type' => 'NORMAL', 'action_code' => $action, 'priority_no' => $p, 'is_default' => 0, 'is_active' => 1,
            ]);
            $edge($start, $bmh, 'SUBMIT', 'E_START_BMH', 10);
            $edge($bmh, $end, 'APPROVE', 'E_BMH_END', 10);
        }

        TblRoutingRule::firstOrCreate(
            ['rule_code' => 'RR_LOADTEST'],
            ['idtblsource_app' => $app->idtblsource_app, 'idtbldocument_type' => $docType->idtbldocument_type,
             'rule_name' => 'LT route', 'condition_json' => [], 'idtblflow_definition' => $def->idtblflow_definition,
             'priority_no' => 1, 'is_active' => 1]
        );

        TblUser::firstOrCreate(
            ['user_ref' => 'LT_APPROVER'],
            ['full_name' => 'LT Approver', 'is_active' => 1, 'password' => Hash::make('rahasia123'), 'must_change_password' => 0]
        );

        $plain = (string) $this->option('secret');
        $client = TblApiClient::where('client_key', 'LOADTEST_KEY')->first() ?: new TblApiClient([
            'idtblsource_app' => $app->idtblsource_app, 'client_key' => 'LOADTEST_KEY', 'is_active' => 1,
        ]);
        $client->idtblsource_app = $app->idtblsource_app;
        $client->is_active = 1;
        $client->client_secret_hash = $secrets->encrypt($plain);
        $client->save();

        $this->line('CLIENT_KEY=LOADTEST_KEY');
        $this->line('SECRET=' . $plain);
        $this->line('DOC_TYPE=' . $docType->idtbldocument_type);
        return self::SUCCESS;
    }
}
