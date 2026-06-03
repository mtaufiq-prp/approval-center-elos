<?php

namespace App\Http\Controllers\Web\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ApiClientRequest;
use App\Models\TblApiClient;
use App\Models\TblSourceApp;
use App\Services\ApiClientSecretService;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD API Client.
 *
 * Catatan keamanan KRITIS:
 *  - client_secret plaintext HANYA ditampilkan SEKALI saat create / rotate.
 *  - Plaintext disimpan di session flash one-time, lalu hilang.
 *  - Tidak masuk audit (AuditTrailService otomatis redact field
 *    'client_secret_hash' & 'client_secret').
 *  - is_active = 0 = revoke; soft, tidak hard delete (audit/forensik).
 */
class ApiClientController extends Controller
{
    public function __construct(
        private AuditTrailService      $audit,
        private ApiClientSecretService $secretService,
    ) {}

    public function index(Request $request): View
    {
        $q = TblApiClient::with('sourceApp');

        if ($s = trim((string) $request->input('search'))) {
            $q->where(function ($w) use ($s) {
                $w->where('client_key', 'like', "%{$s}%")
                  ->orWhereHas('sourceApp', fn($sq) => $sq->where('app_code', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('idtblsource_app')) {
            $q->where('idtblsource_app', (int) $request->input('idtblsource_app'));
        }

        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $q->where('is_active', $request->input('is_active'));
        }

        $items     = $q->orderByDesc('idtblapi_client')->paginate(15)->withQueryString();
        $sourceApps = TblSourceApp::orderBy('app_code')->get(['idtblsource_app', 'app_code', 'app_name']);

        return view('master.api_client.index', compact('items', 'sourceApps'));
    }

    public function create(): View
    {
        $sourceApps = TblSourceApp::where('is_active', 1)->orderBy('app_code')->get();
        return view('master.api_client.create', compact('sourceApps'));
    }

    public function store(ApiClientRequest $request): RedirectResponse
    {
        $result = $this->secretService->createWithSecret($request->validated());

        // Audit (tanpa secret plaintext — service AuditTrail otomatis redact)
        $this->audit->recordCreated(
            $result['model'],
            "API Client {$result['client_key']} dibuat (secret generated).",
        );

        // Flash one-time agar plaintext ditampilkan sekali di view berikutnya.
        // Catatan: 'flash' di Laravel HANYA bertahan untuk satu request berikutnya.
        return redirect()
            ->route('master.api-client.show-secret', $result['model']->idtblapi_client)
            ->with('plain_secret', $result['plain_secret'])
            ->with('client_key',   $result['client_key'])
            ->with('status', 'API Client berhasil dibuat. Simpan secret yang ditampilkan — tidak dapat dilihat ulang.');
    }

    /**
     * Halaman tampil-sekali untuk plaintext secret.
     * Tidak ada cara melihat ulang secret setelah meninggalkan halaman ini.
     */
    public function showSecret(int $idtblapi_client): View|RedirectResponse
    {
        $client = TblApiClient::with('sourceApp')->findOrFail($idtblapi_client);

        $plain = session('plain_secret');
        $key   = session('client_key', $client->client_key);

        if (! $plain) {
            // Direct access tanpa flash → redirect ke index, tidak ada
            // jalan untuk melihat ulang plaintext.
            return redirect()->route('master.api-client.index')
                ->with('warning', 'Plaintext secret tidak tersedia. Lakukan Rotate untuk membuat secret baru.');
        }

        return view('master.api_client.show_secret', [
            'item'         => $client,
            'plain_secret' => $plain,
            'client_key'   => $key,
        ]);
    }

    public function edit(TblApiClient $api_client): View
    {
        $sourceApps = TblSourceApp::orderBy('app_code')->get();
        return view('master.api_client.edit', ['item' => $api_client, 'sourceApps' => $sourceApps]);
    }

    public function update(ApiClientRequest $request, TblApiClient $api_client): RedirectResponse
    {
        $original = $api_client->getOriginal();
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? $api_client->is_active);

        $api_client->fill($data);
        $api_client->save();

        if ($api_client->wasChanged()) {
            $this->audit->recordUpdated($api_client, $original, "API Client {$api_client->client_key} diubah.");
        }

        return redirect()->route('master.api-client.index')
            ->with('status', 'API Client diubah.');
    }

    /**
     * Rotate secret: generate baru, encrypt, simpan, tampilkan sekali.
     */
    public function rotateSecret(TblApiClient $api_client): RedirectResponse
    {
        $result = $this->secretService->rotateSecret($api_client);

        $this->audit->recordEvent(
            entityType: 'tblapi_client',
            entityId:   $api_client->idtblapi_client,
            eventCode:  'API_SECRET_ROTATED',
            message:    "API Client {$api_client->client_key} secret dirotasi.",
        );

        return redirect()
            ->route('master.api-client.show-secret', $api_client->idtblapi_client)
            ->with('plain_secret', $result['plain_secret'])
            ->with('client_key',   $api_client->client_key)
            ->with('status', 'Secret berhasil dirotasi. Simpan secret yang ditampilkan — tidak dapat dilihat ulang.');
    }

    /**
     * Revoke (soft) — is_active = 0.
     */
    public function destroy(TblApiClient $api_client): RedirectResponse
    {
        if (! $api_client->is_active) {
            $this->secretService->activate($api_client);
            $this->audit->recordActivated($api_client, "API Client {$api_client->client_key} diaktifkan.");
            return redirect()->back()->with('status', 'API Client diaktifkan kembali.');
        }

        $this->secretService->revoke($api_client);
        $this->audit->recordEvent(
            entityType: 'tblapi_client',
            entityId:   $api_client->idtblapi_client,
            eventCode:  'API_CLIENT_REVOKED',
            message:    "API Client {$api_client->client_key} di-revoke.",
        );

        return redirect()->back()->with('status', 'API Client di-revoke.');
    }
}
