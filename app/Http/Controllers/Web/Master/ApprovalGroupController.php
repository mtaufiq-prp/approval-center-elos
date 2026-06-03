<?php

namespace App\Http\Controllers\Web\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ApprovalGroupRequest;
use App\Models\TblApprovalGroup;
use App\Models\TblApprovalGroupMember;
use App\Models\TblUser;
use App\Services\AuditTrailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CRUD Approval Group + Member.
 *
 * Member dikelola via add/remove endpoint khusus, BUKAN sync masal,
 * agar audit per-member jelas (siapa ditambah/dikeluarkan kapan).
 */
class ApprovalGroupController extends Controller
{
    public function __construct(private AuditTrailService $audit) {}

    public function index(Request $request): View
    {
        $q = TblApprovalGroup::withCount('members');
        if ($s = trim((string) $request->input('search'))) {
            $q->where(fn($w) => $w->where('group_code', 'like', "%$s%")->orWhere('group_name', 'like', "%$s%"));
        }
        if ($request->filled('is_active') && $request->input('is_active') !== 'all') {
            $q->where('is_active', $request->input('is_active'));
        }
        $items = $q->orderBy('group_code')->paginate(15)->withQueryString();
        return view('master.approval_group.index', compact('items'));
    }

    public function create(): View { return view('master.approval_group.create'); }

    public function store(ApprovalGroupRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $row = TblApprovalGroup::create($data);
        $this->audit->recordCreated($row, "Approval Group {$row->group_code} dibuat.");
        return redirect()->route('master.approval-group.edit', $row->idtblapproval_group)
            ->with('status', "Group dibuat. Tambahkan member.");
    }

    public function edit(TblApprovalGroup $approval_group): View
    {
        $members = TblApprovalGroupMember::where('idtblapproval_group', $approval_group->idtblapproval_group)
            ->with('user')->get();

        $existingUserIds = $members->pluck('idtbluser')->toArray();
        $availableUsers  = TblUser::where('is_active', 1)
            ->whereNotIn('idtbluser', $existingUserIds)
            ->orderBy('user_ref')->limit(500)->get();

        return view('master.approval_group.edit', [
            'item'           => $approval_group,
            'members'        => $members,
            'availableUsers' => $availableUsers,
        ]);
    }

    public function update(ApprovalGroupRequest $request, TblApprovalGroup $approval_group): RedirectResponse
    {
        $original = $approval_group->getOriginal();
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? $approval_group->is_active);
        $approval_group->fill($data); $approval_group->save();
        if ($approval_group->wasChanged()) {
            $this->audit->recordUpdated($approval_group, $original, "Approval Group {$approval_group->group_code} diubah.");
        }
        return back()->with('status', "Group diubah.");
    }

    public function destroy(TblApprovalGroup $approval_group): RedirectResponse
    {
        if ($approval_group->is_active) {
            $approval_group->is_active = false; $approval_group->save();
            $this->audit->recordDeactivated($approval_group);
        } else {
            $approval_group->is_active = true; $approval_group->save();
            $this->audit->recordActivated($approval_group);
        }
        return back()->with('status', "Status group diubah.");
    }

    /**
     * Tambah satu user sebagai member group.
     */
    public function addMember(Request $request, TblApprovalGroup $approval_group): RedirectResponse
    {
        $data = $request->validate([
            'idtbluser'   => ['required', 'integer', 'exists:tbluser,idtbluser'],
            'priority_no' => ['nullable', 'integer', 'min:0'],
        ]);

        $exists = TblApprovalGroupMember::where('idtblapproval_group', $approval_group->idtblapproval_group)
            ->where('idtbluser', $data['idtbluser'])->exists();

        if ($exists) {
            return back()->with('error', 'User sudah menjadi member group ini.');
        }

        DB::transaction(function () use ($approval_group, $data) {
            $member = TblApprovalGroupMember::create([
                'idtblapproval_group' => $approval_group->idtblapproval_group,
                'idtbluser'           => $data['idtbluser'],
                'priority_no'         => $data['priority_no'] ?? 0,
                'is_active'           => 1,
            ]);

            $user = TblUser::find($data['idtbluser']);
            $this->audit->recordEvent(
                entityType: 'tblapproval_group',
                entityId:   $approval_group->idtblapproval_group,
                eventCode:  'GROUP_MEMBER_ADDED',
                message:    "User {$user->user_ref} ditambahkan ke group {$approval_group->group_code}.",
                newValues:  ['idtbluser' => $user->idtbluser, 'priority_no' => $member->priority_no],
            );
        });

        return back()->with('status', 'Member ditambahkan.');
    }

    public function removeMember(TblApprovalGroup $approval_group, int $idtblapproval_group_member): RedirectResponse
    {
        $member = TblApprovalGroupMember::where('idtblapproval_group', $approval_group->idtblapproval_group)
            ->where('idtblapproval_group_member', $idtblapproval_group_member)
            ->with('user')->firstOrFail();

        $userRef = optional($member->user)->user_ref ?? '?';
        $member->delete();

        $this->audit->recordEvent(
            entityType: 'tblapproval_group',
            entityId:   $approval_group->idtblapproval_group,
            eventCode:  'GROUP_MEMBER_REMOVED',
            message:    "User {$userRef} dikeluarkan dari group {$approval_group->group_code}.",
        );

        return back()->with('status', 'Member dikeluarkan.');
    }
}
