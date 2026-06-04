<?php

namespace Tests\Unit;

use App\Services\AssigneeResolverService;
use App\Services\ConditionEvaluatorService;
use App\Services\FlowEngineService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Mengunci logika pemetaan status terminal engine (#80, #85):
 * keputusan REJECT/RETURN tidak boleh berakhir APPROVED.
 */
class FlowTerminalStatusTest extends TestCase
{
    private function engine(): FlowEngineService
    {
        return new FlowEngineService(
            new ConditionEvaluatorService(),
            new AssigneeResolverService(new ConditionEvaluatorService()),
        );
    }

    private function call(string $method, mixed $arg): string
    {
        $m = new ReflectionMethod(FlowEngineService::class, $method);
        return $m->invoke($this->engine(), $arg);
    }

    // ── terminalStatusForAction ─────────────────────────────────────────────

    public function test_reject_action_maps_to_rejected(): void
    {
        $this->assertSame('REJECTED', $this->call('terminalStatusForAction', 'REJECT'));
    }

    public function test_return_action_maps_to_returned_not_approved(): void
    {
        $this->assertSame('RETURNED', $this->call('terminalStatusForAction', 'RETURN'));
    }

    public function test_cancel_action_maps_to_cancelled(): void
    {
        $this->assertSame('CANCELLED', $this->call('terminalStatusForAction', 'CANCEL'));
    }

    public function test_approve_action_maps_to_completed(): void
    {
        $this->assertSame('COMPLETED', $this->call('terminalStatusForAction', 'APPROVE'));
        $this->assertSame('COMPLETED', $this->call('terminalStatusForAction', 'AUTO_APPROVE'));
    }

    public function test_null_action_defaults_to_completed(): void
    {
        $this->assertSame('COMPLETED', $this->call('terminalStatusForAction', null));
    }

    // ── mapRequestStatus (final status → request_status ENUM) ───────────────

    public function test_request_status_completed_maps_to_approved(): void
    {
        $this->assertSame('APPROVED', $this->call('mapRequestStatus', 'COMPLETED'));
        $this->assertSame('APPROVED', $this->call('mapRequestStatus', 'APPROVED'));
    }

    public function test_request_status_rejected_returned_cancelled_passthrough(): void
    {
        $this->assertSame('REJECTED',  $this->call('mapRequestStatus', 'REJECTED'));
        $this->assertSame('RETURNED',  $this->call('mapRequestStatus', 'RETURNED'));
        $this->assertSame('CANCELLED', $this->call('mapRequestStatus', 'CANCELLED'));
    }

    // ── mapInstanceStatus (final status → instance_status ENUM) ─────────────

    public function test_instance_status_approved_and_returned_become_completed(): void
    {
        // instance_status ENUM tidak punya APPROVED/RETURNED → COMPLETED
        $this->assertSame('COMPLETED', $this->call('mapInstanceStatus', 'APPROVED'));
        $this->assertSame('COMPLETED', $this->call('mapInstanceStatus', 'RETURNED'));
        $this->assertSame('COMPLETED', $this->call('mapInstanceStatus', 'COMPLETED'));
    }

    public function test_instance_status_rejected_cancelled_error_passthrough(): void
    {
        $this->assertSame('REJECTED',  $this->call('mapInstanceStatus', 'REJECTED'));
        $this->assertSame('CANCELLED', $this->call('mapInstanceStatus', 'CANCELLED'));
        $this->assertSame('ERROR',     $this->call('mapInstanceStatus', 'ERROR'));
    }
}
