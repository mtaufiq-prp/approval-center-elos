<?php

namespace Tests\Support;

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
use Illuminate\Support\Facades\Hash;

/**
 * Helper test: membangun flow approval minimal & dapat dieksekusi engine.
 *
 *   START → (SUBMIT) → BMH [APPROVAL, USER] → (APPROVE) → END
 *
 * Cukup untuk membuktikan: routing START (anti fail-open), approve→APPROVED,
 * reject→REJECTED, return→RETURNED + reopen, callback outbox, immutabilitas final.
 */
trait BuildsApprovalFlow
{
    protected array $flowFixture = [];

    /** Bangun source app + doc type + flow ACTIVE + routing rule + approver. */
    protected function buildMinimalFlow(string $approverRef = 'APPROVER1', ?string $callbackUrl = 'http://10.20.30.40/cb'): array
    {
        $app = TblSourceApp::create([
            'app_code' => 'TST',
            'app_name' => 'Test Source App',
            'is_active' => 1,
            'default_callback_url' => $callbackUrl,
        ]);

        $docType = TblDocumentType::create([
            'idtblsource_app' => $app->idtblsource_app,
            'doc_code'        => 'TST_DOC',
            'doc_name'        => 'Test Document',
        ]);

        $def = TblFlowDefinition::create([
            'flow_code'         => 'FLOW_TST',
            'flow_name'         => 'Test Flow',
            'idtblsource_app'   => $app->idtblsource_app,
            'idtbldocument_type' => $docType->idtbldocument_type,
        ]);

        $version = TblFlowVersion::create([
            'idtblflow_definition' => $def->idtblflow_definition,
            'version_no'           => 1,
            'version_name'         => 'v1',
            'status'               => TblFlowVersion::STATUS_ACTIVE,
            'validation_status'    => TblFlowVersion::VALIDATION_VALID,
        ]);
        $vid = $version->idtblflow_version;

        $start = $this->node($vid, 'START', 'START', 'NONE', 10);
        $bmh   = $this->node($vid, 'BMH', 'APPROVAL', 'NONE', 20, 'ANY', 48);
        $end   = $this->node($vid, 'END', 'END', 'NONE', 30);

        // Approver (USER assignee)
        $approver = TblUser::create([
            'user_ref'  => $approverRef,
            'full_name' => 'Approver ' . $approverRef,
            'is_active' => 1,
            'password'  => Hash::make('rahasia123'),
            'must_change_password' => 0,
        ]);

        TblStepAssigneeRule::create([
            'idtblflow_step' => $bmh->idtblflow_step,
            'assignee_type'  => 'USER',
            'assignee_value' => $approverRef,
            'priority_no'    => 1,
            'is_required'    => true,
            'is_active'      => true,
        ]);

        // Edges
        $this->edge($vid, $start, $bmh, 'SUBMIT', 'E_START_BMH', 10);
        $this->edge($vid, $bmh, $end, 'APPROVE', 'E_BMH_END', 10);

        TblRoutingRule::create([
            'idtblsource_app'      => $app->idtblsource_app,
            'idtbldocument_type'   => $docType->idtbldocument_type,
            'rule_code'            => 'RR_TST',
            'rule_name'            => 'Default route',
            'condition_json'       => [],
            'idtblflow_definition' => $def->idtblflow_definition,
            'priority_no'          => 1,
            'is_active'            => 1,
        ]);

        return $this->flowFixture = compact('app', 'docType', 'def', 'version', 'start', 'bmh', 'end', 'approver');
    }

    /** Buat approval request SUBMITTED untuk fixture flow (belum start engine). */
    protected function makeApprovalRequest(array $fx, ?int $submitterId = null, ?string $docRef = null): \App\Models\TblApprovalRequest
    {
        return \App\Models\TblApprovalRequest::create([
            'idtblsource_app'            => $fx['app']->idtblsource_app,
            'idtbldocument_type'         => $fx['docType']->idtbldocument_type,
            'source_request_id'          => $docRef ?? ('DOC-' . uniqid()),
            'source_request_no'          => 'DOC-NO',
            'idtblflow_version_selected' => $fx['version']->idtblflow_version,
            'callback_url'               => $fx['app']->default_callback_url,
            'context_json'               => ['amount' => 100],
            'payload_json'               => [],
            'request_status'             => 'SUBMITTED',
            'title'                      => 'Test request',
            'idtbluser_submitter'        => $submitterId,
            'submitted_at'               => now(),
        ]);
    }

    /** Buat API client dengan secret diketahui (untuk uji HMAC). */
    protected function makeApiClient(TblSourceApp $app, string $clientKey, string $plainSecret, array $attrs = []): TblApiClient
    {
        $client = new TblApiClient(array_merge([
            'idtblsource_app' => $app->idtblsource_app,
            'client_key'      => $clientKey,
            'is_active'       => 1,
        ], $attrs));
        // client_secret_hash tidak fillable → set langsung (encrypted).
        $client->client_secret_hash = app(ApiClientSecretService::class)->encrypt($plainSecret);
        $client->save();

        return $client;
    }

    private function node(int $vid, string $code, string $type, string $gw, int $order, string $mode = 'ANY', ?int $sla = null): TblFlowStep
    {
        return TblFlowStep::create([
            'idtblflow_version' => $vid,
            'node_code'         => $code,
            'step_code'         => $code,
            'step_name'         => $code,
            'step_type'         => $type,
            'gateway_type'      => $gw,
            'step_order'        => $order,
            'approval_mode'     => $mode,
            'reject_behavior'   => 'END_REJECTED',
            'sla_hours'         => $sla,
        ]);
    }

    private function edge(int $vid, TblFlowStep $from, TblFlowStep $to, string $action, string $code, int $priority): TblFlowTransition
    {
        return TblFlowTransition::create([
            'idtblflow_version'   => $vid,
            'idtblflow_step_from' => $from->idtblflow_step,
            'idtblflow_step_to'   => $to->idtblflow_step,
            'transition_code'     => $code,
            'transition_name'     => $code,
            'transition_type'     => 'NORMAL',
            'action_code'         => $action,
            'priority_no'         => $priority,
            'is_default'          => 0,
            'is_active'           => 1,
        ]);
    }
}
