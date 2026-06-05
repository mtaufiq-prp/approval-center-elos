<?php

namespace Tests\Feature\Approval;

use App\Jobs\SendCallbackJob;
use ReflectionMethod;
use Tests\TestCase;

/**
 * #C1: SSRF guard berbasis ALLOWLIST agar callback internal (10.x) berfungsi,
 * sementara loopback & metadata/link-local tetap diblokir.
 */
class CallbackSsrfTest extends TestCase
{
    private function blockedReason(string $url): ?string
    {
        $job = new SendCallbackJob(1);
        $m = new ReflectionMethod(SendCallbackJob::class, 'blockedReason');
        return $m->invoke($job, $url);
    }

    public function test_ip_in_cidr_matching(): void
    {
        $this->assertTrue(SendCallbackJob::ipInCidr('10.50.0.4', '10.0.0.0/8'));
        $this->assertTrue(SendCallbackJob::ipInCidr('172.16.5.5', '172.16.0.0/12'));
        $this->assertTrue(SendCallbackJob::ipInCidr('192.168.1.9', '192.168.0.0/16'));
        $this->assertFalse(SendCallbackJob::ipInCidr('8.8.8.8', '10.0.0.0/8'));
        $this->assertFalse(SendCallbackJob::ipInCidr('192.168.1.1', '10.0.0.0/8'));
        $this->assertTrue(SendCallbackJob::ipInCidr('1.2.3.4', '1.2.3.4'));   // exact
        $this->assertFalse(SendCallbackJob::ipInCidr('1.2.3.4', '1.2.3.5'));
    }

    public function test_internal_target_allowed_when_in_allowlist(): void
    {
        config(['approval_center.callback.allowed_cidrs' => ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']]);

        $this->assertNull($this->blockedReason('http://10.50.0.4/approval/callback'),
            'Target internal 10.x harus DIIZINKAN (sebelumnya diblokir → hub-and-spoke putus).');
        $this->assertNull($this->blockedReason('http://192.168.10.20/cb'));
    }

    public function test_loopback_and_metadata_always_blocked(): void
    {
        config(['approval_center.callback.allowed_cidrs' => ['10.0.0.0/8']]);

        $this->assertNotNull($this->blockedReason('http://127.0.0.1/cb'));
        $this->assertNotNull($this->blockedReason('http://localhost/cb'));
        $this->assertNotNull($this->blockedReason('http://169.254.169.254/latest/meta-data/'),
            'Metadata/link-local harus selalu diblokir.');
    }

    public function test_out_of_allowlist_blocked(): void
    {
        config(['approval_center.callback.allowed_cidrs' => ['10.0.0.0/8']]);

        $this->assertNotNull($this->blockedReason('http://8.8.8.8/cb'),
            'IP publik di luar allowlist harus diblokir.');
    }

    public function test_empty_allowlist_allows_public_but_still_blocks_loopback(): void
    {
        config(['approval_center.callback.allowed_cidrs' => []]);

        $this->assertNull($this->blockedReason('http://8.8.8.8/cb'));
        $this->assertNotNull($this->blockedReason('http://127.0.0.1/cb'));
        $this->assertNotNull($this->blockedReason('http://169.254.169.254/'));
    }
}
