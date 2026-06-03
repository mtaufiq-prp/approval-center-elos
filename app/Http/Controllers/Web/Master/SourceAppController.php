<?php

namespace App\Http\Controllers\Web\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\SourceAppRequest;
use App\Models\TblSourceApp;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD Source App.
 *
 * Soft-deactivation: is_active = 0 (tidak hard delete).
 * Audit: setiap create/update/activate/deactivate masuk tblaudit_event
 * via AuditTrailService.
 */
class SourceAppController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblSourceApp::query();

        if ($s = trim((string) $request->input('search'))) {
            $q->where(function ($w) use ($s) {
                $w->where('app_code', 'like', "%{$s}%")
                  ->orWhere('app_name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $q->where('is_active', $request->input('is_active'));
        }

        $items = $q->orderBy('app_code')->paginate(15)->withQueryString();

        return view('master.source_app.index', compact('items'));
    }

    public function create(): View
    {
        return view('master.source_app.create');
    }

    public function store(SourceAppRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        $row = TblSourceApp::create($data);
        $this->audit->recordCreated($row, "Source App {$row->app_code} dibuat.");

        return redirect()->route('master.source-app.index')
            ->with('status', "Source App {$row->app_code} berhasil dibuat.");
    }

    public function edit(TblSourceApp $source_app): View
    {
        return view('master.source_app.edit', ['item' => $source_app]);
    }

    public function update(SourceAppRequest $request, TblSourceApp $source_app): RedirectResponse
    {
        $original = $source_app->getOriginal();
        $data     = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? $source_app->is_active);

        $source_app->fill($data);
        $source_app->save();

        if ($source_app->wasChanged()) {
            $this->audit->recordUpdated($source_app, $original, "Source App {$source_app->app_code} diubah.");
        }

        return redirect()->route('master.source-app.index')
            ->with('status', "Source App {$source_app->app_code} berhasil diubah.");
    }

    /**
     * Soft-deactivate. Tidak hard delete karena banyak FK ke tabel ini.
     */
    public function destroy(TblSourceApp $source_app): RedirectResponse
    {
        if (! $source_app->is_active) {
            // Reactivate
            $source_app->is_active = true;
            $source_app->save();
            $this->audit->recordActivated($source_app, "Source App {$source_app->app_code} diaktifkan.");
            return redirect()->back()->with('status', "Source App {$source_app->app_code} diaktifkan.");
        }

        $source_app->is_active = false;
        $source_app->save();
        $this->audit->recordDeactivated($source_app, "Source App {$source_app->app_code} dinonaktifkan.");

        return redirect()->back()->with('status', "Source App {$source_app->app_code} dinonaktifkan.");
    }
}
