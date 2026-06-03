<?php

namespace Tests\Feature\Auth;

use App\Models\TblUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcePasswordChangeTest extends TestCase
{
    use DatabaseTransactions;

    private function userMustChange(): TblUser
    {
        return TblUser::create([
            'user_ref'             => 'PWCHG_USER_' . uniqid(),
            'full_name'            => 'Must Change',
            'password'             => Hash::make('old-password'),
            'is_active'            => 1,
            'must_change_password' => 1,
        ]);
    }

    private function userNormal(): TblUser
    {
        return TblUser::create([
            'user_ref'             => 'NORMAL_USER_' . uniqid(),
            'full_name'            => 'Normal',
            'password'             => Hash::make('rahasia123'),
            'is_active'            => 1,
            'must_change_password' => 0,
        ]);
    }

    /** @test */
    public function user_must_change_diarahkan_ke_change_password_saat_akses_home(): void
    {
        $user = $this->userMustChange();

        $this->actingAs($user)
             ->get('/')
             ->assertRedirect(route('password.change'));
    }

    /** @test */
    public function user_must_change_tetap_bisa_akses_halaman_change_password(): void
    {
        $user = $this->userMustChange();

        $this->actingAs($user)
             ->get(route('password.change'))
             ->assertOk();
    }

    /** @test */
    public function user_must_change_tetap_bisa_logout(): void
    {
        $user = $this->userMustChange();

        $this->actingAs($user)
             ->post(route('logout'))
             ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** @test */
    public function user_normal_bisa_akses_home(): void
    {
        $user = $this->userNormal();

        $this->actingAs($user)
             ->get('/')
             ->assertOk();
    }

    /** @test */
    public function ganti_password_berhasil_mereset_flag_must_change_password(): void
    {
        $user = $this->userMustChange();

        $response = $this->actingAs($user)->post(route('password.change'), [
            'current_password'      => 'old-password',
            'password'              => 'BaruKuat#2026',
            'password_confirmation' => 'BaruKuat#2026',
        ]);

        $response->assertRedirect(route('home'));

        $user->refresh();
        $this->assertFalse((bool) $user->must_change_password);
        $this->assertNotNull($user->password_changed_at);
        $this->assertTrue(Hash::check('BaruKuat#2026', $user->password));
    }

    /** @test */
    public function ganti_password_dengan_current_password_salah_ditolak(): void
    {
        $user = $this->userMustChange();

        $this->actingAs($user)
             ->post(route('password.change'), [
                 'current_password'      => 'WRONG',
                 'password'              => 'BaruKuat#2026',
                 'password_confirmation' => 'BaruKuat#2026',
             ])
             ->assertSessionHasErrors('current_password');

        $this->assertTrue((bool) $user->fresh()->must_change_password);
    }

    /** @test */
    public function password_baru_tidak_boleh_sama_dengan_lama(): void
    {
        $user = $this->userMustChange();

        $this->actingAs($user)
             ->post(route('password.change'), [
                 'current_password'      => 'old-password',
                 'password'              => 'old-password',
                 'password_confirmation' => 'old-password',
             ])
             ->assertSessionHasErrors('password');
    }

    /** @test */
    public function password_baru_minimal_8_karakter_dengan_simbol(): void
    {
        $user = $this->userMustChange();

        $this->actingAs($user)
             ->post(route('password.change'), [
                 'current_password'      => 'old-password',
                 'password'              => 'simple1',
                 'password_confirmation' => 'simple1',
             ])
             ->assertSessionHasErrors('password');
    }
}
