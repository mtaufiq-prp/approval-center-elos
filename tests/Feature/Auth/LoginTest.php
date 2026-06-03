<?php

namespace Tests\Feature\Auth;

use App\Models\TblRole;
use App\Models\TblUser;
use App\Models\TblUserRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Test login.
 *
 * Catatan: kami pakai DatabaseTransactions (bukan RefreshDatabase) karena
 * schema utama berasal dari file SQL eksternal, bukan migration Laravel.
 * Pastikan database test sudah berisi schema lengkap sebelum jalankan test.
 */
class LoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Pastikan 4 role utama ada (idempotent)
        foreach (['ADMIN_APPROVAL', 'APPROVER', 'REQUESTER', 'AUDITOR'] as $code) {
            TblRole::firstOrCreate(
                ['role_code' => $code],
                ['role_name' => $code, 'is_active' => 1]
            );
        }
    }

    /** @test */
    public function login_page_dapat_diakses_tamu(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Approval Center');
    }

    /** @test */
    public function user_aktif_bisa_login_dengan_user_ref_dan_password_benar(): void
    {
        $user = TblUser::create([
            'user_ref'             => 'TEST001',
            'full_name'            => 'Tester Satu',
            'email'                => 'tester1@propan.test',
            'password'             => Hash::make('rahasia123'),
            'is_active'            => 1,
            'must_change_password' => 0,
        ]);

        $this->post('/login', [
            'login'    => 'TEST001',
            'password' => 'rahasia123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function user_aktif_bisa_login_dengan_email_dan_password_benar(): void
    {
        TblUser::create([
            'user_ref'             => 'TEST002',
            'full_name'            => 'Tester Dua',
            'email'                => 'tester2@propan.test',
            'password'             => Hash::make('rahasia123'),
            'is_active'            => 1,
            'must_change_password' => 0,
        ]);

        $this->post('/login', [
            'login'    => 'tester2@propan.test',
            'password' => 'rahasia123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated();
    }

    /** @test */
    public function user_nonaktif_tidak_bisa_login(): void
    {
        TblUser::create([
            'user_ref'  => 'INACTIVE01',
            'full_name' => 'Non Aktif',
            'email'     => 'inactive@propan.test',
            'password'  => Hash::make('rahasia123'),
            'is_active' => 0,
        ]);

        $this->post('/login', [
            'login'    => 'INACTIVE01',
            'password' => 'rahasia123',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    /** @test */
    public function user_tanpa_password_tidak_bisa_login(): void
    {
        TblUser::create([
            'user_ref'  => 'NOPW01',
            'full_name' => 'No Password',
            'email'     => 'nopw@propan.test',
            'password'  => null,
            'is_active' => 1,
        ]);

        $this->post('/login', [
            'login'    => 'NOPW01',
            'password' => 'apapun',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    /** @test */
    public function password_salah_tidak_bisa_login(): void
    {
        TblUser::create([
            'user_ref'  => 'TEST003',
            'full_name' => 'Tester Tiga',
            'password'  => Hash::make('benar'),
            'is_active' => 1,
        ]);

        $this->post('/login', [
            'login'    => 'TEST003',
            'password' => 'salah',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    /** @test */
    public function user_bisa_logout(): void
    {
        $user = TblUser::create([
            'user_ref'             => 'TEST004',
            'full_name'            => 'Tester Empat',
            'password'             => Hash::make('rahasia123'),
            'is_active'            => 1,
            'must_change_password' => 0,
        ]);

        $this->actingAs($user)
             ->post('/logout')
             ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** @test */
    public function login_berhasil_mengisi_last_login_at(): void
    {
        $user = TblUser::create([
            'user_ref'             => 'TEST005',
            'full_name'            => 'Tester Lima',
            'password'             => Hash::make('rahasia123'),
            'is_active'            => 1,
            'must_change_password' => 0,
        ]);

        $this->assertNull($user->last_login_at);

        $this->post('/login', [
            'login'    => 'TEST005',
            'password' => 'rahasia123',
        ]);

        $this->assertNotNull($user->fresh()->last_login_at);
    }
}
