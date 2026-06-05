<?php

namespace Tests\Feature\Auth;

use App\Models\TblRole;
use App\Models\TblUser;
use App\Models\TblUserRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Daftarkan route demo HANYA untuk test (tidak diekspos di produksi).
     * Memakai middleware nyata 'role' agar autorisasi benar-benar teruji.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'role:ADMIN_APPROVAL'])
            ->get('/admin-only-demo', fn () => response('ok'));

        Route::middleware(['web', 'auth', 'role:APPROVER,ADMIN_APPROVAL'])
            ->get('/approver-only-demo', fn () => response('ok'));
    }

    private function makeUserWithRole(string $userRef, ?string $roleCode = null): TblUser
    {
        $user = TblUser::create([
            'user_ref'             => $userRef,
            'full_name'            => "Test $userRef",
            'password'             => Hash::make('rahasia123'),
            'is_active'            => 1,
            'must_change_password' => 0,
        ]);

        if ($roleCode) {
            $role = TblRole::firstOrCreate(
                ['role_code' => $roleCode],
                ['role_name' => $roleCode, 'is_active' => 1]
            );
            TblUserRole::create([
                'idtbluser' => $user->idtbluser,
                'idtblrole' => $role->idtblrole,
            ]);
        }

        return $user;
    }

    /** @test */
    public function user_dengan_role_admin_bisa_akses_route_admin_only(): void
    {
        $admin = $this->makeUserWithRole('ROLE_TEST_ADMIN', 'ADMIN_APPROVAL');

        $this->actingAs($admin)
             ->get('/admin-only-demo')
             ->assertOk();
    }

    /** @test */
    public function user_dengan_role_approver_tidak_bisa_akses_admin_only(): void
    {
        $approver = $this->makeUserWithRole('ROLE_TEST_APPROVER', 'APPROVER');

        $this->actingAs($approver)
             ->get('/admin-only-demo')
             ->assertForbidden();
    }

    /** @test */
    public function user_tanpa_role_tidak_bisa_akses_admin_only(): void
    {
        $user = $this->makeUserWithRole('ROLE_TEST_NOROLE');

        $this->actingAs($user)
             ->get('/admin-only-demo')
             ->assertForbidden();
    }

    /** @test */
    public function user_admin_atau_approver_bisa_akses_approver_only(): void
    {
        $admin = $this->makeUserWithRole('ROLE_TEST_ADMIN2', 'ADMIN_APPROVAL');
        $this->actingAs($admin)
             ->get('/approver-only-demo')
             ->assertOk();

        // Test sebagai approver
        $approver = $this->makeUserWithRole('ROLE_TEST_APPROVER2', 'APPROVER');
        $this->actingAs($approver)
             ->get('/approver-only-demo')
             ->assertOk();
    }

    /** @test */
    public function user_auditor_tidak_bisa_akses_approver_only(): void
    {
        $auditor = $this->makeUserWithRole('ROLE_TEST_AUDITOR', 'AUDITOR');

        $this->actingAs($auditor)
             ->get('/approver-only-demo')
             ->assertForbidden();
    }

    /** @test */
    public function guest_diarahkan_ke_login_saat_akses_route_terlindungi(): void
    {
        $this->get('/admin-only-demo')
             ->assertRedirect(route('login'));
    }

    /** @test */
    public function role_nonaktif_tidak_lulus_middleware(): void
    {
        $user = TblUser::create([
            'user_ref'             => 'ROLE_INACTIVE_USER',
            'full_name'            => 'Inactive Role',
            'password'             => Hash::make('rahasia123'),
            'is_active'            => 1,
            'must_change_password' => 0,
        ]);

        $role = TblRole::create([
            'role_code' => 'ROLE_TEMPORARY_DISABLED',
            'role_name' => 'Temp Disabled',
            'is_active' => 0,
        ]);

        TblUserRole::create([
            'idtbluser' => $user->idtbluser,
            'idtblrole' => $role->idtblrole,
        ]);

        $this->assertFalse($user->hasAnyRole('ROLE_TEMPORARY_DISABLED'));
    }
}
