<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\TblUser;
use App\Models\TblRole;

/**
 * SfaUsersAndBranchMapSeeder
 *
 * 1. Insert semua user SFA ke tbluser (BMH, RRM, NRM, PMM, PD, PKG, CEO)
 * 2. Isi tblapprover_branch_map (branch -> BMH + RRM)
 *
 * Jalankan: php artisan db:seed --class=SfaUsersAndBranchMapSeeder
 */
class SfaUsersAndBranchMapSeeder extends Seeder
{
    private array $employees = [
        // BMH (group 7)
        ['id'=>'11010090','name'=>'BUDIMAN OEY',                    'group'=>'BMH','branch'=>'1430'],
        ['id'=>'11040010','name'=>'SUMARJOKO',                       'group'=>'BMH','branch'=>'1434'],
        ['id'=>'11070030','name'=>'AGUSTIN DEWI',                    'group'=>'BMH','branch'=>'1426'],
        ['id'=>'11080038','name'=>'ANDREY LUNARDY',                  'group'=>'BMH','branch'=>'1401'],
        ['id'=>'11100006','name'=>'HOSEA HENDRAWANTO PUTRO',         'group'=>'BMH','branch'=>'1413'],
        ['id'=>'11110104','name'=>'YAHYA BATISTA WIJAYA',            'group'=>'BMH','branch'=>'1420'],
        ['id'=>'11110107','name'=>'HENY LAY JANTARA',                'group'=>'BMH','branch'=>'1402'],
        ['id'=>'11110115','name'=>'YOSEPHANY FIRDIAN SAPUTRA',       'group'=>'BMH','branch'=>'1418'],
        ['id'=>'11110247','name'=>'SLAMET SANTOSO',                  'group'=>'BMH','branch'=>'1401'],
        ['id'=>'11110263','name'=>'DANANG BUDHI PERMANA',            'group'=>'BMH','branch'=>'1412'],
        ['id'=>'11120378','name'=>'SUPRIYONO',                       'group'=>'BMH','branch'=>'1419'],
        ['id'=>'11120414','name'=>'SUNDOKO',                         'group'=>'BMH','branch'=>'1425'],
        ['id'=>'11120460','name'=>'FEMY SUHARYONO',                  'group'=>'BMH','branch'=>'1416'],
        ['id'=>'11120640','name'=>'ERWAN PERMATA ANANDA',            'group'=>'BMH','branch'=>'1422'],
        ['id'=>'11130010','name'=>'ZAINAL',                          'group'=>'BMH','branch'=>'1401'],
        ['id'=>'11130130','name'=>'W.E. ANANGGA PURBANCANA',         'group'=>'BMH','branch'=>'1424'],
        ['id'=>'11131256','name'=>'MULDANI',                         'group'=>'BMH','branch'=>'1401'],
        ['id'=>'11140351','name'=>'WIKANTI SIH WILUJENG',            'group'=>'BMH','branch'=>'1401'],
        ['id'=>'11140491','name'=>'HENDO',                           'group'=>'BMH','branch'=>'1428'],
        ['id'=>'11150116','name'=>'DWI HARYANTO',                    'group'=>'BMH','branch'=>'1403'],
        ['id'=>'11150185','name'=>'SEPTIAN PRADIATMOKO',             'group'=>'BMH','branch'=>'1427'],
        ['id'=>'11160066','name'=>'HANDRY GUNAWAN',                  'group'=>'BMH','branch'=>'1408'],
        ['id'=>'11160106','name'=>'IMELDA SEPTANPIA',                'group'=>'BMH','branch'=>'1401'],
        ['id'=>'11160170','name'=>'RAYMOND STEVEN',                  'group'=>'BMH','branch'=>'1432'],
        ['id'=>'11170262','name'=>'TONY YUNIARTO',                   'group'=>'BMH','branch'=>'1414'],
        ['id'=>'11170285','name'=>'ISMAWARJONO RAMADHAN',            'group'=>'BMH','branch'=>'1433'],
        ['id'=>'11170513','name'=>'FAHRI AULIA',                     'group'=>'BMH','branch'=>'1405'],
        ['id'=>'11180158','name'=>'NUR ARIFIN',                      'group'=>'BMH','branch'=>'1430'],
        ['id'=>'11190054','name'=>'JEFFRY SEPTIAN PUTRA',            'group'=>'BMH','branch'=>'1406'],
        ['id'=>'11190236','name'=>'VERI CAHYONO',                    'group'=>'BMH','branch'=>'1415'],
        ['id'=>'11190309','name'=>'LINTONG LANRO',                   'group'=>'BMH','branch'=>'1442'],
        ['id'=>'11200050','name'=>'RICHARD WIJAYA',                  'group'=>'BMH','branch'=>'1402'],
        ['id'=>'11210021','name'=>'ANGGRAINI',                       'group'=>'BMH','branch'=>'1446'],
        ['id'=>'11210036','name'=>'ADE MARTHA',                      'group'=>'BMH','branch'=>'1407'],
        ['id'=>'11220140','name'=>'SUGENG SAMPOERNA',                'group'=>'BMH','branch'=>'1404'],
        ['id'=>'11220176','name'=>'I WAYAN SUMARNA',                 'group'=>'BMH','branch'=>'1408'],
        ['id'=>'11230007','name'=>'ICE TRISNAWATI TOGATOROP',        'group'=>'BMH','branch'=>'1405'],
        ['id'=>'11240338','name'=>'YONGKI KURNIADI',                 'group'=>'BMH','branch'=>'1409'],
        ['id'=>'11240411','name'=>'HENGKY',                          'group'=>'BMH','branch'=>'1427'],
        ['id'=>'11240425','name'=>'RICKY',                           'group'=>'BMH','branch'=>'1405'],
        ['id'=>'11240433','name'=>'BERNADUS TRAJU BINTARSA',         'group'=>'BMH','branch'=>'1403'],
        ['id'=>'11250080','name'=>'BARTOLOMEUS RESPATI DWIJOKONGKO', 'group'=>'BMH','branch'=>'1417'],
        ['id'=>'12160513','name'=>'LIKE BUDIHARJO',                  'group'=>'BMH','branch'=>'1420'],
        ['id'=>'12182197','name'=>'JOHANES JOSHUA POEDJIANTO',       'group'=>'BMH','branch'=>'1402'],
        ['id'=>'12182403','name'=>'HANDOKO',                         'group'=>'BMH','branch'=>'1401'],
        ['id'=>'12210023','name'=>'VISHNU DYNIA RAMANDITA',          'group'=>'BMH','branch'=>'1404'],
        ['id'=>'12220497','name'=>'RAHELMI ZULKARNAIN AKBAR',        'group'=>'BMH','branch'=>'1439'],
        // RRM (group 9) — 5 unique
        ['id'=>'11020031','name'=>'AGUS WIDJAJA',       'group'=>'RRM','branch'=>'1403'],
        ['id'=>'11030021','name'=>'MOH. CARNO ADINATA', 'group'=>'RRM','branch'=>'1401'],
        ['id'=>'11960002','name'=>'CHANDRA DJOENAEDI',  'group'=>'RRM','branch'=>'1407'],
        ['id'=>'11980008','name'=>'ABDUL MUNIR',        'group'=>'RRM','branch'=>'1413'],
        ['id'=>'11990027','name'=>'ZENNIES ADITYA',     'group'=>'RRM','branch'=>'1404'],
        // NRM (group 10)
        ['id'=>'11990056','name'=>'JULIUS KURATA',          'group'=>'NRM','branch'=>'1101'],
        // CEO — NPK asli dari data: 1030018 (bukan 11030018)
        ['id'=>'1030018', 'name'=>'KRIS RIANTO ADIDARMA',  'group'=>'CEO','branch'=>'1101'],
        // PD (group 18)
        ['id'=>'11000081','name'=>'JUDIATIN RACHMIARTI KUSUMAH',   'group'=>'PD','branch'=>'1101'],
        ['id'=>'11030005','name'=>'EDY LINARDI',                   'group'=>'PD','branch'=>'1101'],
        ['id'=>'11030007','name'=>'I GEDE EKA PANJI SUBERATA',     'group'=>'PD','branch'=>'1101'],
        ['id'=>'11890014','name'=>'SOERONO HANDOYO',                'group'=>'PD','branch'=>'1101'],
        ['id'=>'11970049','name'=>'MULIANA LOGITO',                 'group'=>'PD','branch'=>'1101'],
        ['id'=>'12200641','name'=>'MOHAMED BATCHA SHAIK DAWOOD',   'group'=>'PD','branch'=>'1101'],
        // PMM (group 19)
        ['id'=>'11000079','name'=>'YUSTINUS JEFFRY GANI',          'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11050021','name'=>'ARIF KURNIAWAN H. S.',           'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11070019','name'=>'ARDI MARLIAN OKTANDRIANTO',      'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11060003','name'=>'RUDIANA WINATA',                 'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11030011','name'=>'YUDIANTA HALIM',                 'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11120089','name'=>'MICHAEL BUDI KARTOATMOJO',       'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11110094','name'=>'DENNY FIRMANSYAH',               'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11110217','name'=>'HENDRIKUS YANUAR GIJANTO',       'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11170218','name'=>'KIKIS YULIANTI',                 'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11170314','name'=>'AMELIA DEWI',                    'group'=>'PMM','branch'=>'1101'],
        ['id'=>'11920015','name'=>'ACENG MUHAEMIN',                 'group'=>'PMM','branch'=>'1101'],
        ['id'=>'12210038','name'=>'SUHARSONO LEGOWO',               'group'=>'PMM','branch'=>'1101'],
        // Packaging (group 20)
        ['id'=>'11130476','name'=>'HENDRI GUNAWAN',                 'group'=>'PKG','branch'=>'1101'],
    ];

    private array $bmhToRrm = [
        '11010090'=>'11020031','11040010'=>'11030021','11070030'=>'11980008',
        '11100006'=>'11980008','11110104'=>'11990027','11110107'=>'11020031',
        '11110115'=>'11030021','11110247'=>'11030021','11110263'=>'11980008',
        '11120378'=>'11020031','11120414'=>'11020031','11120460'=>'11960002',
        '11120640'=>'11990027','11130130'=>'11020031','11140351'=>'11030021',
        '11140491'=>'11990027','11150116'=>'11020031','11150185'=>'11030021',
        '11160106'=>'11030021','11160170'=>'11030021','11170262'=>'11980008',
        '11170285'=>'11980008','11170513'=>'11960002','11180158'=>'11020031',
        '11190054'=>'11990027','11190236'=>'11980008','11190309'=>'11960002',
        '11200050'=>'11020031','11210021'=>'11960002','11210036'=>'11960002',
        '11220140'=>'11990027','11220176'=>'11990027','11230007'=>'11960002',
        '11240338'=>'11030021','11240411'=>'11030021','11240425'=>'11960002',
        '11240433'=>'11020031','11250080'=>'11960002','12210023'=>'11990027',
        '12220497'=>'11020031',
    ];

    private array $branchNames = [
        '1101'=>'PUSAT',       '1401'=>'TANGERANG',   '1402'=>'SIDOARJO',
        '1403'=>'SEMARANG',    '1404'=>'BANDUNG',     '1405'=>'MEDAN',
        '1406'=>'CIREBON',     '1407'=>'PALEMBANG',   '1408'=>'DENPASAR',
        '1409'=>'PONTIANAK',   '1412'=>'BANJARMASIN', '1413'=>'MAKASAR',
        '1414'=>'BALIKPAPAN',  '1415'=>'MANADO',      '1416'=>'BATAM',
        '1417'=>'LAMPUNG',     '1418'=>'BOGOR',        '1419'=>'SOLO',
        '1420'=>'MATARAM',     '1422'=>'KARAWANG',    '1424'=>'JEMBER',
        '1425'=>'KEDIRI',      '1426'=>'SAMARINDA',   '1427'=>'DKJ TIMUR',
        '1428'=>'TASIKMALAYA', '1430'=>'PURWOKERTO',  '1432'=>'DKJ BARAT',
        '1433'=>'KENDARI',     '1434'=>'SERANG',       '1439'=>'SURABAYA',
        '1442'=>'JAMBI',       '1446'=>'PADANG',
    ];

    public function run(): void
    {
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  SFA Users & Branch Map Seeder');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $approverRole = TblRole::where('role_code', 'APPROVER')->first();
        if (! $approverRole) {
            $this->command->error('Role APPROVER tidak ditemukan!');
            return;
        }

        // ── 1. Insert users ─────────────────────────────────────────────
        $this->command->info('  Inserting users...');
        $inserted = 0; $skipped = 0;

        foreach ($this->employees as $emp) {
            $existing = TblUser::where('user_ref', $emp['id'])->first();
            if ($existing) { $skipped++; continue; }

            $user = TblUser::create([
                'user_ref'             => $emp['id'],
                'full_name'            => $emp['name'],
                'email'                => strtolower(
                    preg_replace('/[^a-z0-9\.@]/', '',
                        str_replace([' ', '(', ')'], ['.', '', ''], strtolower($emp['name']))
                    )
                ) . '@propanraya.com',
                'password'             => Hash::make(bin2hex(random_bytes(16))),
                'must_change_password' => 1,
                'is_active'            => 1,
            ]);

            // Assign role APPROVER
            DB::table('tbluser_role')->insertOrIgnore([
                'idtbluser'  => $user->idtbluser,
                'idtblrole'  => $approverRole->idtblrole,
                'created_at' => now(),
            ]);

            $inserted++;
        }
        $this->command->info("  Users: {$inserted} dibuat, {$skipped} sudah ada.");

        // ── 2. Isi tblapprover_branch_map ────────────────────────────────
        $this->command->info('  Building branch map...');
        DB::table('tblapprover_branch_map')->truncate();

        $mapRows = 0;
        foreach ($this->employees as $emp) {
            if ($emp['group'] !== 'BMH') continue;

            $rrmRef = $this->bmhToRrm[$emp['id']] ?? null;

            DB::table('tblapprover_branch_map')->insert([
                'idtblbranch'  => $emp['branch'],
                'branch_name'  => $this->branchNames[$emp['branch']] ?? $emp['branch'],
                'bmh_user_ref' => $emp['id'],
                'rrm_user_ref' => $rrmRef,
                'is_active'    => 1,
                'created_at'   => now(),
            ]);
            $mapRows++;
        }
        $this->command->info("  Branch map: {$mapRows} rows inserted.");

        // ── Summary ─────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('  ✅  Selesai.');
        $this->command->info('  Password di-generate secara acak (must_change_password=1).');
        $this->command->info('  Gunakan fitur Reset Password di menu Master → User untuk set password awal.');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
